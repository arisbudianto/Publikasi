<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class ScopusPageMonitor
{
    public function __construct(private JournalPageFetcher $fetcher, private JournalEvidenceExtractor $extractor,
        private JournalScopeLinkFinder $scopeLinks, private CrawledJournalSync $sync) {}

    public function tick(): array
    {
        if (! config('scopus_crawl.enabled')) {
            return ['status' => 'disabled', 'checked' => 0];
        }
        $today = now()->startOfDay();
        $checkedToday = DB::table('scopus_page_checks as c')
            ->join('scopus_page_targets as t', 't.id', '=', 'c.target_id')
            ->where('c.created_at', '>=', $today)->distinct()->pluck('t.scopus_source_id')->all();
        $m19Today = DB::table('m19_runs as r')->join('m19_sources as s', 's.id', '=', 'r.source_id')
            ->where('r.created_at', '>=', $today)->distinct()->count('s.journal_id');
        $sintaToday = DB::table('sinta_page_checks')->where('created_at', '>=', $today)->count();
        $verificationToday = DB::table('scopus_page_targets')->where('verification_checked_at', '>=', $today)->count();
        $limit = max(0, min((int) config('scopus_crawl.daily_limit') - count($checkedToday),
            20 - count($checkedToday) - $m19Today - $sintaToday - $verificationToday));
        $result = ['status' => 'ok', 'checked' => 0, 'changed' => 0, 'errors' => 0, 'candidates' => 0, 'synced' => 0];
        if ($limit === 0) {
            return $result;
        }
        $targets = DB::table('scopus_page_targets as t')
            ->join('scopus_sources as s', 's.id', '=', 't.scopus_source_id')
            ->where('t.enabled', true)->where('s.is_current', true)
            ->where(fn ($q) => $q->whereNull('t.next_check_at')->orWhere('t.next_check_at', '<=', now()))
            ->when($checkedToday, fn ($q) => $q->whereNotIn('t.scopus_source_id', $checkedToday))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('scopus_page_targets as earlier')
                    ->whereColumn('earlier.scopus_source_id', 't.scopus_source_id')
                    ->whereColumn('earlier.id', '<', 't.id')->where('earlier.enabled', true)
                    ->where(fn ($due) => $due->whereNull('earlier.next_check_at')->orWhere('earlier.next_check_at', '<=', now()));
            })
            ->orderBy('t.next_check_at')->orderBy('t.id')->limit($limit)
            ->get(['t.id', 't.url', 't.field', 't.last_hash']);

        foreach ($targets as $target) {
            $claimed = DB::table('scopus_page_targets')->where('id', $target->id)->where('enabled', true)
                ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                ->update(['next_check_at' => now()->addDays((int) config('scopus_crawl.recrawl_days')),
                    'updated_at' => now()]);
            if (! $claimed) continue;
            $result['checked']++;
            try {
                $page = $this->fetcher->fetch($target->url);
                $evidenceUrl = $target->url;
                $quotes = $this->extractor->extract($page['body'], $target->field);
                if ($target->field === 'scope' && $quotes === []
                    && ($scopeUrl = $this->scopeLinks->find($page['body'], $target->url))) {
                    try {
                        $scopePage = $this->fetcher->fetch($scopeUrl);
                        $page = $scopePage;
                        $evidenceUrl = $scopeUrl;
                        $quotes = $this->extractor->extract($page['body'], 'scope');
                    } catch (Throwable) {
                        // Retain the successful homepage check if the scope page fails.
                    }
                }
                $hash = hash('sha256', $page['body']);
                $changed = $hash !== $target->last_hash;
                $sourceId = DB::table('scopus_page_targets')->where('id', $target->id)->value('scopus_source_id');
                $hasScope = DB::table('scopus_sources')->where('id', $sourceId)
                    ->whereNotNull('scope_excerpt')->where('scope_excerpt', '!=', '')->exists();
                $quotes = ($changed || ! $hasScope) ? array_slice($quotes, 0, 3) : [];
                $added = DB::transaction(function () use ($target, $page, $evidenceUrl, $hash, $changed, $quotes) {
                    $check = DB::table('scopus_page_checks')->insertGetId(['target_id' => $target->id,
                        'status' => $changed ? 'changed' : 'unchanged', 'http_status' => $page['status'],
                        'content_hash' => $hash, 'evidence_url' => $evidenceUrl, 'created_at' => now()]);
                    $added = 0;
                    $synced = $quotes && $this->sync->fromScopus($target->id, $quotes[0], $evidenceUrl);
                    if ($synced) DB::table('scopus_page_checks')->where('id', $check)->update(['synced' => true]);
                    foreach ($synced ? [] : $quotes as $quote) {
                        if (DB::table('scopus_page_candidates')->where('target_id', $target->id)
                            ->where('status', 'pending')->where('quote', $quote)->exists()) continue;
                        DB::table('scopus_page_candidates')->insert(['target_id' => $target->id, 'check_id' => $check,
                            'quote' => $quote, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                        $added++;
                    }
                    DB::table('scopus_page_targets')->where('id', $target->id)->update(['last_hash' => $hash]);
                    return [$added, (int) $synced];
                });
                $result['changed'] += (int) $changed;
                $result['candidates'] += $added[0];
                $result['synced'] += $added[1];
            } catch (Throwable $e) {
                DB::table('scopus_page_checks')->insert(['target_id' => $target->id, 'status' => 'error',
                    'error_code' => mb_substr($e->getMessage(), 0, 60), 'created_at' => now()]);
                $result['errors']++;
            }
        }
        return $result;
    }
}
