<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Services\JournalPageFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class JournalAgentController extends Controller
{
    public function index()
    {
        return response()->view('admin.agent', [
            'journals' => Journal::where('status', 'published')->orderBy('name')->get(['id', 'name']),
            'sources' => DB::table('m19_sources')->join('journals', 'journals.id', '=', 'm19_sources.journal_id')
                ->select('m19_sources.*', 'journals.name as journal_name')->orderByDesc('m19_sources.id')->limit(50)->get(),
            'candidates' => DB::table('m19_candidates')->join('m19_sources', 'm19_sources.id', '=', 'm19_candidates.source_id')
                ->join('journals', 'journals.id', '=', 'm19_sources.journal_id')
                ->where('m19_candidates.status', 'pending')->select('m19_candidates.*', 'journals.name as journal_name', 'journals.id as journal_id')
                ->orderBy('m19_candidates.id')->limit(50)->get(),
            'sintaChecks' => DB::table('sinta_page_checks as c')->join('journal_sources as j', 'j.id', '=', 'c.journal_source_id')
                ->select('c.id', 'c.status', 'c.error_code', 'c.created_at', 'j.name', 'j.id as journal_source_id')
                ->orderByDesc('c.id')->limit(20)->get(),
            'sintaCandidates' => DB::table('sinta_page_candidates as c')->join('journal_sources as j', 'j.id', '=', 'c.journal_source_id')
                ->join('sinta_page_checks as p', 'p.id', '=', 'c.check_id')
                ->where('c.status', 'pending')->select('c.id', 'c.quote', 'j.name', 'p.url as evidence_url', 'j.id as journal_source_id')
                ->orderBy('c.id')->limit(30)->get(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, JournalPageFetcher $fetcher)
    {
        $data = $request->validate([
            'journal_id' => 'required|integer|exists:journals,id',
            'url' => 'required|url:https|max:1000',
            'field' => ['required', Rule::in(['scope', 'fee', 'time', 'acceptance'])],
        ]);
        $journal = Journal::whereKey($data['journal_id'])->where('status', 'published')->firstOrFail();
        try {
            if ($fetcher->host($data['url']) !== $fetcher->host($journal->official_url)) {
                return back()->withErrors(['url' => 'Domain harus sama dengan situs resmi jurnal.']);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['url' => 'URL sumber tidak valid.']);
        }
        if (DB::table('m19_sources')->where('journal_id', $journal->id)->where('field', $data['field'])->where('url', $data['url'])->exists()) {
            return back()->withErrors(['url' => 'Sumber untuk bidang ini sudah terdaftar.']);
        }
        DB::table('m19_sources')->insert($data + ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Sumber ditambahkan dalam keadaan jeda. Aktifkan setelah alamatnya diperiksa.');
    }

    public function toggle(Request $request, int $source)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $updated = DB::table('m19_sources')->where('id', $source)->update(['enabled' => (bool) $data['enabled'],
            'next_check_at' => $data['enabled'] ? now() : null, 'updated_at' => now()]);
        abort_unless($updated, 404);

        return back()->with('success', $data['enabled'] ? 'Sumber aktif dan menunggu jadwal.' : 'Sumber dijeda.');
    }

    public function review(Request $request, int $candidate)
    {
        $data = $request->validate(['decision' => 'required|in:reviewed,rejected', 'note' => 'required|string|min:10|max:2000']);
        $updated = DB::table('m19_candidates')->where('id', $candidate)->where('status', 'pending')->update([
            'status' => $data['decision'], 'reviewed_by' => $request->user()->id,
            'review_note' => $data['note'], 'updated_at' => now(),
        ]);
        abort_unless($updated, 409);

        return back()->with('success', 'Keputusan disimpan. Informasi katalog tidak berubah.');
    }

    public function reviewSinta(Request $request, int $candidate)
    {
        $data = $request->validate(['decision' => 'required|in:reviewed,rejected']);
        $updated = DB::table('sinta_page_candidates')->where('id', $candidate)->where('status', 'pending')
            ->update(['status' => $data['decision'], 'updated_at' => now()]);
        abort_unless($updated, 409);

        return back()->with('success', 'Keputusan disimpan. Snapshot SINTA dan katalog tidak berubah.');
    }
}
