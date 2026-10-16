<?php

namespace App\Http\Controllers;

use App\Models\ScopusSource;
use App\Services\JournalPageFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class ScopusCrawlController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:120',
            'period' => 'nullable|in:today,7d,30d',
            'source' => 'nullable|in:all,sinta,scopus',
            'status' => 'nullable|in:all,changed,unchanged,error',
        ]);
        $q = trim((string) $request->query('q', ''));
        $period = $request->query('period', '7d');
        $source = $request->query('source', 'all');
        $status = $request->query('status', 'all');
        $since = match ($period) {
            'today' => now()->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };
        $summary = [];
        foreach (['sinta' => 'sinta_page_checks', 'scopus' => 'scopus_page_checks'] as $kind => $table) {
            $counts = DB::table($table)->where('created_at', '>=', $since)
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
            $summary[$kind] = [
                'checked' => $counts->sum(),
                'changed' => (int) ($counts['changed'] ?? 0),
                'unchanged' => (int) ($counts['unchanged'] ?? 0),
                'error' => (int) ($counts['error'] ?? 0),
                'synced' => DB::table($table)->where('created_at', '>=', $since)->where('synced', true)->count(),
                'last' => DB::table($table)->max('created_at'),
                'pending' => DB::table($kind.'_page_candidates')->where('status', 'pending')->count(),
            ];
        }
        $history = collect();
        if ($source !== 'scopus') {
            $sinta = DB::table('sinta_page_checks as c')->join('journal_sources as j', 'j.id', '=', 'c.journal_source_id')
                ->where('c.created_at', '>=', $since)
                ->when($status !== 'all', fn ($query) => $query->where('c.status', $status))
                ->select('c.id', 'c.status', 'c.synced', 'c.http_status', 'c.error_code', 'c.created_at', 'c.url',
                    'j.name as journal_name', 'c.journal_source_id')
                ->orderByDesc('c.id')->limit(50)->get()
                ->map(fn ($row) => (object) [...(array) $row, 'source' => 'SINTA']);
            $history = $history->concat($sinta);
        }
        if ($source !== 'sinta') {
            $scopus = DB::table('scopus_page_checks as c')->join('scopus_page_targets as t', 't.id', '=', 'c.target_id')
                ->join('scopus_sources as s', 's.id', '=', 't.scopus_source_id')
                ->where('c.created_at', '>=', $since)
                ->when($status !== 'all', fn ($query) => $query->where('c.status', $status))
                ->select('c.id', 'c.status', 'c.synced', 'c.http_status', 'c.error_code', 'c.created_at',
                    's.source_title as journal_name', 's.id as scopus_source_id')
                ->selectRaw('COALESCE(c.evidence_url, t.url) as url')
                ->orderByDesc('c.id')->limit(50)->get()
                ->map(fn ($row) => (object) [...(array) $row, 'source' => 'Scopus']);
            $history = $history->concat($scopus);
        }
        $history = $history->sortByDesc('created_at')->take(50)->values();
        $matches = collect();
        if ($q !== '') {
            $matches = ScopusSource::where('is_current', true)->where('source_type', 'Journal')
                ->where(function ($query) use ($q) {
                    $query->where('source_title', 'like', '%'.addcslashes($q, '%_\\').'%')
                        ->orWhere('issn', strtoupper(str_replace('-', '', $q)))
                        ->orWhere('eissn', strtoupper(str_replace('-', '', $q)));
                })->orderBy('source_title')->limit(20)->get();
        }
        return response()->view('admin.scopus-crawl', [
            'q' => $q, 'matches' => $matches, 'period' => $period, 'source' => $source,
            'status' => $status, 'summary' => $summary, 'history' => $history,
            'activeTargets' => DB::table('scopus_page_targets')->where('enabled', true)->count(),
            'targets' => DB::table('scopus_page_targets as t')->join('scopus_sources as s', 's.id', '=', 't.scopus_source_id')
                ->select('t.*', 's.source_title', 's.is_current')->orderByDesc('t.id')->limit(50)->get(),
            'candidates' => DB::table('scopus_page_candidates as c')
                ->join('scopus_page_targets as t', 't.id', '=', 'c.target_id')
                ->join('scopus_page_checks as p', 'p.id', '=', 'c.check_id')
                ->join('scopus_sources as s', 's.id', '=', 't.scopus_source_id')
                ->where('c.status', 'pending')->select('c.*', 't.field', 's.source_title')
                ->selectRaw('COALESCE(p.evidence_url, t.url) as url')
                ->orderBy('c.id')->limit(50)->get(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, JournalPageFetcher $fetcher)
    {
        $data = $request->validate([
            'scopus_source_id' => 'required|integer|exists:scopus_sources,id',
            'url' => 'required|url:https|max:1000',
            'field' => ['required', Rule::in(['scope', 'fee', 'time', 'acceptance'])],
        ]);
        ScopusSource::whereKey($data['scopus_source_id'])->where('is_current', true)
            ->where('source_type', 'Journal')->firstOrFail();
        try {
            $fetcher->host($data['url']);
        } catch (RuntimeException) {
            return back()->withErrors(['url' => 'Alamat HTTPS jurnal tidak valid.']);
        }
        if (DB::table('scopus_page_targets')->where($data)->exists()) {
            return back()->withErrors(['url' => 'Halaman untuk bidang ini sudah terdaftar.']);
        }
        DB::table('scopus_page_targets')->insert($data + ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Halaman didaftarkan dalam keadaan jeda. Periksa situs resmi sebelum mengaktifkannya.');
    }

    public function toggle(Request $request, int $target)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $updated = DB::table('scopus_page_targets')->where('id', $target)->update([
            'enabled' => (bool) $data['enabled'],
            'next_check_at' => $data['enabled'] ? now() : null,
            'updated_at' => now(),
        ]);
        abort_unless($updated, 404);
        return back()->with('success', $data['enabled'] ? 'Pemantauan diaktifkan.' : 'Pemantauan dijeda.');
    }

    public function review(Request $request, int $candidate)
    {
        $data = $request->validate(['decision' => 'required|in:reviewed,rejected', 'note' => 'required|string|min:10|max:2000']);
        $updated = DB::table('scopus_page_candidates')->where('id', $candidate)->where('status', 'pending')
            ->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id,
                'review_note' => $data['note'], 'updated_at' => now()]);
        abort_unless($updated, 409);
        return back()->with('success', 'Tinjauan disimpan. Data indeks dan katalog tidak berubah.');
    }
}
