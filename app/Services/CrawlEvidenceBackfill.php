<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CrawlEvidenceBackfill
{
    public function __construct(private CrawledJournalSync $sync) {}

    public function run(int $limit = 50, bool $apply = false): array
    {
        if ($limit < 1 || $limit > 100) throw new \InvalidArgumentException('limit_out_of_range');
        $since = now()->subDays(60);
        $sinta = DB::table('sinta_page_candidates as p')
            ->join('sinta_page_checks as c', 'c.id', '=', 'p.check_id')
            ->where('p.status', 'pending')->where('c.status', 'changed')
            ->where('c.created_at', '>=', $since)->orderBy('p.id')->limit($limit)
            ->get(['p.id', 'p.journal_source_id', 'p.quote', 'p.check_id', 'c.url']);
        $scopus = DB::table('scopus_page_candidates as p')
            ->join('scopus_page_checks as c', 'c.id', '=', 'p.check_id')
            ->join('scopus_page_targets as t', 't.id', '=', 'p.target_id')
            ->where('p.status', 'pending')->where('c.status', 'changed')
            ->where('c.created_at', '>=', $since)->where('t.field', 'scope')
            ->orderBy('p.id')->limit(max(0, $limit - $sinta->count()))
            ->get(['p.id', 'p.target_id', 'p.quote', 'p.check_id', 'c.evidence_url', 't.url']);
        $result = ['candidates' => $sinta->count() + $scopus->count(), 'synced' => 0,
            'mode' => $apply ? 'apply' : 'dry-run'];
        if (! $apply) return $result;

        foreach ($sinta as $row) {
            if (! $this->validScopeQuote($row->quote)) continue;
            DB::transaction(function () use ($row, &$result) {
                if (! $this->sync->fromSinta($row->journal_source_id, $row->url, $row->quote)) return;
                DB::table('sinta_page_candidates')->where('id', $row->id)->where('status', 'pending')
                    ->update(['status' => 'synced', 'updated_at' => now()]);
                DB::table('sinta_page_checks')->where('id', $row->check_id)->update(['synced' => true]);
                $result['synced']++;
            });
        }
        foreach ($scopus as $row) {
            if (! $this->validScopeQuote($row->quote)) continue;
            DB::transaction(function () use ($row, &$result) {
                if (! $this->sync->fromScopus($row->target_id, $row->quote, $row->evidence_url ?: $row->url)) return;
                DB::table('scopus_page_candidates')->where('id', $row->id)->where('status', 'pending')
                    ->update(['status' => 'synced', 'updated_at' => now()]);
                DB::table('scopus_page_checks')->where('id', $row->check_id)->update(['synced' => true]);
                $result['synced']++;
            });
        }
        return $result;
    }

    private function validScopeQuote(string $quote): bool
    {
        return mb_strlen($quote) >= 20 && mb_strlen($quote) <= 600
            && (bool) preg_match('/\b(scope|aims|focus|cakupan|ruang lingkup)\b/iu', $quote)
            && (bool) preg_match('/[.!?;:]/u', $quote);
    }
}
