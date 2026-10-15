<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CuratedJournalDiscovery
{
    public function tick(int $limit = 20): array
    {
        $limit = max(1, min($limit, 20));
        $sources = DB::table('journal_sources as s')
            ->join('journal_accreditations as a', 'a.journal_source_id', '=', 's.id')
            ->leftJoin('curation_leads as l', 'l.journal_source_id', '=', 's.id')
            ->whereNull('l.id')->whereNull('s.journal_id')->whereNull('a.journal_id')->whereNotNull('s.name')
            ->whereNotNull('s.official_url')->where('s.official_url', 'like', 'https://%')
            ->whereBetween('a.rank', [1, 4])->where('a.e_issn_checksum_valid', true)
            ->where('a.match_status', 'matched')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('journals as j')
                    ->where(function ($ids) {
                        $ids->whereColumn('j.issn', 's.p_issn')->orWhereColumn('j.issn', 's.e_issn');
                    });
            })
            ->orderBy('a.rank')->orderBy('s.id')->limit($limit)
            ->get(['s.id as source_id', 'a.id as accreditation_id']);

        $created = 0;
        foreach ($sources as $source) {
            $created += DB::table('curation_leads')->insertOrIgnore([
                'journal_source_id' => $source->source_id,
                'accreditation_id' => $source->accreditation_id,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['eligible' => $sources->count(), 'created' => $created, 'limit' => $limit];
    }
}
