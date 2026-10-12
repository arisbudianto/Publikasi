<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Services\ObservationReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurationController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate(['review' => 'nullable|in:all,due,claims']);
        $filter = $data['review'] ?? 'due';
        $query = Journal::query()->withCount('observations');
        if ($filter === 'due') {
            $query->where(fn ($b) => $b->where('checked_at', '<', today()->subDays(180))->orWhereHas('observations', fn ($o) => $o->where('checked_at', '<', today()->subDays(180))));
        }
        if ($filter === 'claims') {
            $query->whereHas('observations', fn ($b) => $b->where('verification', 'publisher_claim'));
        }
        $journals = $query->orderBy('checked_at')->paginate(20)->withQueryString();

        return response()->view('admin.curation', ['journals' => $journals, 'filter' => $filter])->header('Cache-Control', 'no-store, private');
    }

    public function show(Journal $journal)
    {
        $observations = $journal->allObservations()->orderByDesc('id')->paginate(20)->withQueryString();
        $audits = DB::table('catalog_audits')->where('journal_id', $journal->id)->orderByDesc('id')->limit(30)->get();

        return response()->view('admin.review', compact('journal', 'audits', 'observations'))->header('Cache-Control', 'no-store, private');
    }

    public function publish(Request $r, Journal $journal, ObservationReview $service)
    {
        $data = $r->validate(['kind' => ['required', Rule::in(['index', 'fee', 'time'])], 'label' => 'required|string|max:120', 'value' => ['required', 'string', 'max:255', Rule::notIn(['unknown', 'Belum diketahui', 'belum diketahui'])], 'verification' => ['required', Rule::in(['publisher_claim', 'verified'])], 'source_url' => 'required|url:https|max:1000', 'checked_at' => 'required|date_format:Y-m-d|before_or_equal:today', 'period' => 'nullable|string|max:120', 'review_note' => 'required|string|min:20|max:3000', 'confirmed' => 'accepted']);
        unset($data['confirmed']);
        $data['label'] = trim($data['label']);
        $service->publish($journal, $data, $r->user()->id);

        return back()->with('success', 'Observasi baru diterbitkan. Versi sebelumnya tetap tersimpan.');
    }

    public function retire(Request $r, Journal $journal, int $observation, ObservationReview $service)
    {
        $data = $r->validate(['reason' => 'required|string|min:10|max:1000']);
        $service->retire($journal, $observation, $r->user()->id, $data['reason']);

        return back()->with('success','Observasi ditarik dari katalog; riwayat tetap tersimpan.');
    }

    public function refreshArticles(Request $r, Journal $journal)
    {
        \Illuminate\Support\Facades\Cache::forget('crossref-evidence-v1-'.$journal->issn);
        $evidence=app(\App\Services\ArticleEvidence::class)->forIssn($journal->issn);
        DB::table('catalog_audits')->insert(['user_id'=>$r->user()->id,'journal_id'=>$journal->id,'action'=>'articles_refreshed','snapshot'=>json_encode(['source'=>'Crossref','status'=>$evidence['status'],'count'=>count($evidence['articles']),'checked_at'=>now()->toIso8601String()]),'created_at'=>now()]);
        return back()->with('success',$evidence['status']==='unavailable'?'Crossref tidak tersedia. Metadata belum dapat dimutakhirkan.':'Metadata Crossref dimuat ulang: '.count($evidence['articles']).' artikel. Ini bukan verifikasi indeksasi jurnal.');
    }
}
