<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class JournalMetadataSourceSeeder
{
    public function __construct(private JournalPageFetcher $fetcher) {}

    public function run(): array
    {
        $created = 0;
        $skipped = 0;
        $observations = DB::table('journal_observations as o')
            ->join('journals as j', 'j.id', '=', 'o.journal_id')
            ->where('j.status', 'published')->whereNull('o.retired_at')
            ->where('o.verification', 'publisher_claim')
            ->whereIn('o.kind', ['fee', 'time', 'language', 'frequency'])
            ->select('o.journal_id', 'o.kind', 'o.source_url', 'j.official_url')
            ->distinct()->get();

        foreach ($observations as $row) {
            try {
                if ($this->fetcher->host($row->official_url) !== $this->fetcher->host($row->source_url)) {
                    $skipped++;
                    continue;
                }
            } catch (\Throwable) {
                $skipped++;
                continue;
            }
            if (DB::table('m19_sources')->where('journal_id', $row->journal_id)
                ->where('field', $row->kind)->where('url', $row->source_url)->exists()) continue;
            DB::table('m19_sources')->insert([
                'journal_id' => $row->journal_id, 'field' => $row->kind,
                'url' => $row->source_url, 'enabled' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
