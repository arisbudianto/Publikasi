<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalAccreditation;
use Illuminate\Support\Facades\DB;

class JournalAccreditationLinker
{
    public function preview(): array
    {
        $map = $this->journalMap();
        $counts = ['unlinked' => 0, 'linkable' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'invalid_issn' => 0, 'missing_issn' => 0];
        foreach (JournalAccreditation::query()->whereNull('journal_id')->get(['id', 'e_issn_raw', 'e_issn_checksum_valid']) as $row) {
            $counts['unlinked']++;
            $issn = strtoupper(str_replace('-', '', (string) $row->e_issn_raw));
            if ($issn === '') {
                $counts['missing_issn']++;
            } elseif (! $row->e_issn_checksum_valid) {
                $counts['invalid_issn']++;
            } elseif (count($map[$issn] ?? []) > 1) {
                $counts['ambiguous']++;
            } elseif (count($map[$issn] ?? []) === 1) {
                $counts['linkable']++;
            } else {
                $counts['unmatched']++;
            }
        }

        return $counts;
    }

    public function apply(): array
    {
        $map = $this->journalMap();
        $linked = 0;
        DB::transaction(function () use ($map, &$linked) {
            foreach (JournalAccreditation::query()->whereNull('journal_id')->where('e_issn_checksum_valid', true)->lockForUpdate()->get() as $row) {
                $issn = strtoupper(str_replace('-', '', (string) $row->e_issn_raw));
                $journals = $map[$issn] ?? [];
                if (count($journals) !== 1) {
                    continue;
                }
                $row->update(['journal_id' => $journals[0], 'match_status' => 'matched']);
                $linked++;
            }
        });

        return ['linked' => $linked] + $this->preview();
    }

    private function journalMap(): array
    {
        $map = [];
        foreach (Journal::query()->with('identifiers')->get(['id', 'issn']) as $journal) {
            foreach (array_merge([$journal->issn], $journal->identifiers->pluck('value')->all()) as $value) {
                $issn = strtoupper(str_replace('-', '', (string) $value));
                if ($issn !== '') {
                    $map[$issn][$journal->id] = true;
                }
            }
        }

        return array_map(fn ($ids) => array_keys($ids), $map);
    }
}
