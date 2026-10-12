<?php

namespace App\Http\Controllers;

use App\Models\JournalAccreditation;
use Illuminate\Http\Request;

class JournalAccreditationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'rank' => 'nullable|integer|between:1,6']);
        $q = trim($data['q'] ?? '');
        $compact = strtoupper(str_replace('-', '', $q));
        $accreditations = JournalAccreditation::query()
            ->with(['source:id,name,source_id', 'journal:id,name,issn'])
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q, $compact) {
                $query->where('journal_name', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhere('publisher', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhere('e_issn_raw', $compact);
            }))
            ->when($data['rank'] ?? null, fn ($query, $rank) => $query->where('rank', $rank))
            ->orderBy('rank')->orderBy('journal_name')->paginate(25)->withQueryString();

        return view('accreditations.index', [
            'accreditations' => $accreditations,
            'q' => $q,
            'rank' => $data['rank'] ?? '',
            'totalAccreditations' => JournalAccreditation::count(),
            'matchedAccreditations' => JournalAccreditation::whereIn('match_status', ['matched'])->count(),
        ]);
    }
}
