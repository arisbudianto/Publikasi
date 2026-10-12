<?php

namespace App\Services;

use App\Models\JournalDiscontinuedSource;

class DiscontinuedSourceLookup
{
    public function forJournals(iterable $journals): array
    {
        $journals = collect($journals)->filter()->unique('id')->values();
        $issnsByJournal = [];
        foreach ($journals as $journal) {
            $journal->loadMissing('identifiers');
            $issnsByJournal[$journal->id] = collect([$journal->issn])
                ->merge($journal->identifiers->pluck('value'))->unique()->values();
        }
        $allIssns = collect($issnsByJournal)->flatten()->unique()->all();
        $byIssn = JournalDiscontinuedSource::where('is_current', true)->whereIn('issn', $allIssns)->get()->keyBy('issn');

        $result = [];
        foreach ($issnsByJournal as $journalId => $issns) {
            $result[$journalId] = $issns->map(fn ($issn) => $byIssn->get($issn))->filter()->values();
        }

        return $result;
    }
}
