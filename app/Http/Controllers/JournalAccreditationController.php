<?php

namespace App\Http\Controllers;

use App\Models\JournalAccreditation;
use App\Services\JournalDirectory;
use Illuminate\Http\Request;

class JournalAccreditationController extends Controller
{
    public function index(Request $request, JournalDirectory $directory)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'rank' => 'nullable|integer|between:1,4']);
        $q = trim($data['q'] ?? '');
        $compact = strtoupper(str_replace('-', '', $q));
        $accreditations = JournalAccreditation::query()
            ->with([
                'source:id,name,source_id',
                'journal:id,name,issn,scope_summary,scope_source_url,checked_at,status',
                'journal.observations' => fn ($observations) => $directory->currentObservations($observations)
                    ->whereIn('kind', ['fee', 'time'])->orderByDesc('checked_at'),
            ])
            ->whereBetween('rank', [1, 4])
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q, $compact) {
                $query->where('journal_name', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhere('publisher', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhere('e_issn_raw', $compact);
            }))
            ->when($data['rank'] ?? null, fn ($query, $rank) => $query->where('rank', $rank))
            ->orderBy('rank')->orderBy('journal_name')->paginate(25)->withQueryString();
        $rankCounts = JournalAccreditation::query()
            ->selectRaw('rank, COUNT(*) as total')
            ->whereBetween('rank', [1, 4])
            ->groupBy('rank')
            ->pluck('total', 'rank')
            ->all();

        return view('accreditations.index', [
            'accreditations' => $accreditations,
            'q' => $q,
            'rank' => $data['rank'] ?? '',
            'rankCounts' => $rankCounts,
            'totalAccreditations' => JournalAccreditation::whereBetween('rank', [1, 4])->count(),
            'matchedAccreditations' => JournalAccreditation::whereBetween('rank', [1, 4])->where('match_status', 'matched')->count(),
        ]);
    }
}
