<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalSource;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalSourceController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:120']);
        $q = trim((string) $r->query('q', ''));
        $compact = str_replace('-', '', $q);
        $query = JournalSource::where('education', true)->whereNotNull('name')->when($q !== '', fn ($s) => $s->where(function ($s) use ($q, $compact) {
            $s->where('name', 'like', '%'.$q.'%')->orWhere('publisher', 'like', '%'.$q.'%')->orWhere('p_issn', $q)->orWhere('e_issn', $q)->orWhereRaw('REPLACE(p_issn, ?, ?) = ?', ['-', '', $compact])->orWhereRaw('REPLACE(e_issn, ?, ?) = ?', ['-', '', $compact])->orWhereRaw('CAST(article_titles AS CHAR) LIKE ?', ['%'.$q.'%']);
        }));

        return view('sources.index', ['sources' => $query->orderBy('name')->paginate(18)->withQueryString(), 'q' => $q]);
    }

    public function show(JournalSource $source)
    {
        abort_unless($source->education && filled($source->name), 404);
        $ids = array_values(array_filter([$source->p_issn, $source->e_issn]));
        $matches = $ids ? Journal::where('status', 'published')->whereIn('issn', $ids)->get() : collect();

        return view('sources.show', compact('source', 'matches'));
    }

    public function prepare(JournalSource $source)
    {
        abort_unless($source->education && filled($source->name), 404);

        $matchingJournal = Journal::whereIn('issn', array_filter([$source->p_issn, $source->e_issn]))->first();

        return response()->view('sources.prepare', compact('source', 'matchingJournal'))->header('Cache-Control', 'no-store, private');
    }

    public function promote(Request $r, JournalSource $source, Catalog $catalog)
    {
        abort_unless($source->education && filled($source->name), 404);
        abort_if(Journal::whereIn('issn', array_filter([$source->p_issn, $source->e_issn]))->exists(), 409, 'ISSN sudah tercatat di katalog. Buka jurnal yang ada untuk meninjaunya; draft tidak dibuat.');
        $d = $r->validate($catalog->rules() + ['confirm' => 'accepted']);
        unset($d['confirm']);
        $d['status'] = 'draft';
        $j = DB::transaction(function () use ($r, $source, $d, $catalog) {
            $s = JournalSource::lockForUpdate()->findOrFail($source->id);
            abort_if($s->journal_id, 409, 'Sumber ini sudah memiliki draft katalog.');
            $j = $catalog->save($d, null, $r->user()->id);
            $s->update(['journal_id' => $j->id]);

            return $j;
        });

        return redirect()->route('admin.edit', $j)->with('success', 'Draft dibuat. Periksa scope sebelum memublikasikannya.');
    }
}
