<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class SintaPageMonitor
{
    public function __construct(private JournalPageFetcher $fetcher, private JournalEvidenceExtractor $extractor,
        private JournalScopeLinkFinder $scopeLinks) {}

    public function tick(): array
    {
        if (! config('sinta_crawl.enabled')) {
            return ['status' => 'disabled', 'checked' => 0];
        }

        $today = now()->startOfDay();
        $m19Today = DB::table('m19_runs')->join('m19_sources', 'm19_sources.id', '=', 'm19_runs.source_id')
            ->where('m19_runs.created_at', '>=', $today)->distinct()->count('m19_sources.journal_id');
        $sintaToday = DB::table('sinta_page_checks')->where('created_at', '>=', $today)->count();
        $limit = max(0, min((int) config('sinta_crawl.daily_limit') - $sintaToday,
            20 - $m19Today - $sintaToday));
        $result = ['status' => 'ok', 'checked' => 0, 'changed' => 0, 'errors' => 0, 'candidates' => 0];
        if ($limit === 0) {
            return $result;
        }
        $sources = DB::table('journal_sources as j')->leftJoin('sinta_page_states as s', 's.journal_source_id', '=', 'j.id')
            ->whereNotNull('j.official_url')->where('j.official_url', '!=', '')
            ->where(fn ($q) => $q->where('j.official_url', 'like', 'https://%')
                ->orWhere('j.official_url', 'like', 'http://%'))
            ->where(fn ($q) => $q->whereNull('s.next_check_at')->orWhere('s.next_check_at', '<=', now()))
            ->orderBy('s.next_check_at')
            ->orderByRaw("CASE WHEN j.official_url LIKE 'https://%' THEN 0 ELSE 1 END")
            ->orderBy('j.id')
            ->limit($limit)->get(['j.id', 'j.official_url', 's.last_hash']);

        foreach ($sources as $source) {
            // Historical SINTA snapshots include HTTP links. Probe the same host
            // and path over HTTPS; never permit a downgrade or follow redirects.
            $url = preg_replace('/^http:\/\//i', 'https://', $source->official_url);
            DB::table('sinta_page_states')->insertOrIgnore(['journal_source_id' => $source->id,
                'created_at' => now(), 'updated_at' => now()]);
            $claimed = DB::table('sinta_page_states')->where('journal_source_id', $source->id)
                ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                ->update(['next_check_at' => now()->addDays((int) config('sinta_crawl.recrawl_days')),
                    'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $result['checked']++;
            try {
                $page = $this->fetcher->fetch($url);
                $quotes = $this->extractor->extract($page['body'], 'scope');
                if ($quotes === [] && ($scopeUrl = $this->scopeLinks->find($page['body'], $url))) {
                    try {
                        $scopePage = $this->fetcher->fetch($scopeUrl);
                        $page = $scopePage;
                        $url = $scopeUrl;
                        $quotes = $this->extractor->extract($page['body'], 'scope');
                    } catch (Throwable) {
                        // The original page was fetched successfully; keep its check.
                    }
                }
                $hash = hash('sha256', $page['body']);
                $changed = $hash !== $source->last_hash;
                if (! $changed) {
                    $quotes = [];
                }
                $added = DB::transaction(function () use ($source, $url, $page, $hash, $changed, $quotes) {
                    $added = 0;
                    $check = DB::table('sinta_page_checks')->insertGetId(['journal_source_id' => $source->id,
                        'url' => $url, 'status' => $changed ? 'changed' : 'unchanged',
                        'http_status' => $page['status'], 'content_hash' => $hash, 'created_at' => now()]);
                    foreach (array_slice($quotes, 0, 3) as $quote) {
                        if (DB::table('sinta_page_candidates')->where('journal_source_id', $source->id)
                            ->where('status', 'pending')->where('quote', $quote)->exists()) {
                            continue;
                        }
                        DB::table('sinta_page_candidates')->insert(['journal_source_id' => $source->id,
                            'check_id' => $check, 'quote' => $quote, 'status' => 'pending',
                            'created_at' => now(), 'updated_at' => now()]);
                        $added++;
                    }
                    DB::table('sinta_page_states')->where('journal_source_id', $source->id)
                        ->update(['last_hash' => $hash, 'updated_at' => now()]);
                    return $added;
                });
                $result['candidates'] += $added;
                $result['changed'] += (int) $changed;
            } catch (Throwable $e) {
                DB::table('sinta_page_checks')->insert(['journal_source_id' => $source->id,
                    'url' => $url, 'status' => 'error',
                    'error_code' => mb_substr($e->getMessage(), 0, 60), 'created_at' => now()]);
                $result['errors']++;
            }
        }

        return $result;
    }
}
