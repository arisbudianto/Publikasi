<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\M18Proposal;
use App\Services\ObservationReview;
use App\Services\StructuredObservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurationController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate(['review' => 'nullable|in:all,due,claims']);
        $filter = $data['review'] ?? 'all';
        $query = Journal::query()->withCount('observations');
        if ($filter === 'due') {
            $query->where(fn ($b) => $b->where('checked_at', '<', today()->subDays(180))->orWhereHas('observations', fn ($o) => $o->where('checked_at', '<', today()->subDays(180))));
        }
        if ($filter === 'claims') {
            $query->whereHas('observations', fn ($b) => $b->where('verification', 'publisher_claim'));
        }
        $journals = $query->orderByRaw("CASE WHEN status = 'draft' THEN 0 WHEN status = 'published' THEN 1 ELSE 2 END")
            ->orderBy('checked_at')->paginate(20)->withQueryString();

        return response()->view('admin.curation', [
            'journals' => $journals,
            'filter' => $filter,
            'draftCount' => Journal::where('status', 'draft')->count(),
            'publishedCount' => Journal::where('status', 'published')->count(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Journal $journal)
    {
        $observations = $journal->allObservations()->orderByDesc('id')->paginate(20)->withQueryString();
        $audits = DB::table('catalog_audits')->where('journal_id', $journal->id)->orderByDesc('id')->limit(30)->get();
        $proposal = null;
        if ($request->query('proposal_id')) {
            $proposal = M18Proposal::whereKey($request->integer('proposal_id'))
                ->where('journal_id', $journal->id)->where('status', 'reviewed')->firstOrFail();
            abort_unless(in_array($proposal->field, ProposalController::OBSERVATION_FIELDS, true), 404);
        }

        return response()->view('admin.review', compact('journal', 'audits', 'observations', 'proposal'))->header('Cache-Control', 'no-store, private');
    }

    public function publish(Request $r, Journal $journal, ObservationReview $service)
    {
        $data = validator(StructuredObservation::normalize($r->all()), StructuredObservation::rules())->validate();
        unset($data['confirmed']);
        $data['label'] = trim($data['label']);
        $proposalId = $r->validate(['proposal_id' => 'nullable|integer|exists:m18_proposals,id'])['proposal_id'] ?? null;
        DB::transaction(function () use ($journal, $data, $r, $service, $proposalId) {
            $proposal = $proposalId ? M18Proposal::whereKey($proposalId)->lockForUpdate()->firstOrFail() : null;
            if ($proposal) {
                abort_unless($proposal->journal_id === $journal->id && $proposal->status === 'reviewed'
                    && $proposal->field === $data['kind'], 422);
            }
            $observation = $service->publish($journal, $data, $r->user()->id);
            if ($proposal) {
                $proposal->update(['status' => 'applied', 'reviewed_by' => $r->user()->id,
                    'applied_observation_id' => $observation->id]);
                $proposal->events()->create(['actor_id' => $r->user()->id, 'from_status' => 'reviewed',
                    'to_status' => 'applied', 'note' => 'Observasi '.$observation->id.' diterbitkan setelah pemeriksaan admin.',
                    'created_at' => now()]);
            }
        });

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
