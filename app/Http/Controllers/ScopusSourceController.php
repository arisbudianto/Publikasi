<?php

namespace App\Http\Controllers;

use App\Models\ScopusSource;
use Illuminate\Http\Request;

class ScopusSourceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'status' => 'nullable|in:Active,Inactive',
            'type' => 'nullable|in:Journal,Book Series,Trade Journal',
            'discontinued' => 'nullable|in:1',
        ]);
        $q = trim($data['q'] ?? '');
        $type = $data['type'] ?? 'Journal';
        $status = $data['status'] ?? '';
        $query = ScopusSource::where('is_current', true)->where('source_type', $type);
        if ($status !== '') $query->where('status', $status);
        if (isset($data['discontinued'])) $query->where('discontinued', true);
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('source_title', 'like', '%'.$q.'%')
                    ->orWhere('publisher', 'like', '%'.$q.'%')
                    ->orWhere('issn', preg_replace('/[^0-9X]/i', '', $q))
                    ->orWhere('eissn', preg_replace('/[^0-9X]/i', '', $q));
                if (ctype_digit($q)) $builder->orWhere('source_record_id', $q);
            });
        }

        return view('scopus.index', [
            'sources' => $query->orderBy('source_title')->paginate(20)->withQueryString(),
            'q' => $q, 'type' => $type, 'status' => $status,
            'discontinued' => isset($data['discontinued']),
            'snapshot' => ScopusSource::where('is_current', true)->value('snapshot_month'),
        ]);
    }
}
