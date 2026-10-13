<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class JournalMonitoringAgent
{
    public function __construct(private JournalPageFetcher $fetcher, private JournalEvidenceExtractor $extractor, private JournalAiExtractor $ai) {}

    public function tick(): array
    {
        if (! config('m19.enabled')) {
            return ['status' => 'disabled', 'checked' => 0];
        }
        $sources = DB::table('m19_sources')->where('enabled', true)
            ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
            ->orderBy('next_check_at')->limit((int) config('m19.batch_limit'))->get();
        $result = ['status' => 'ok', 'checked' => 0, 'changed' => 0, 'errors' => 0];
        foreach ($sources as $source) {
            // A due source is claimed before network I/O. Repeated scheduler runs cannot fetch it again this week.
            $claimed = DB::table('m19_sources')->where('id', $source->id)->where('enabled', true)
                ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                ->update(['next_check_at' => now()->addWeek(), 'last_checked_at' => now(), 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $result['checked']++;
            try {
                $page = $this->fetcher->fetch($source->url);
                $hash = hash('sha256', $page['body']);
                $changed = $source->last_hash !== $hash;
                $quotes = $changed ? $this->extractor->extract($page['body'], $source->field) : [];
                $aiStatus = null;
                if ($changed && config('m19.ai_enabled') && $this->ai->ready()) {
                    try {
                        $quotes = array_unique(array_merge($quotes, $this->ai->extract($page['body'], $source->field)));
                        $aiStatus = 'completed';
                    } catch (Throwable) {
                        // The rule extractor remains available if the model or its budget fails.
                        $aiStatus = 'failed';
                    }
                }
                DB::transaction(function () use ($source, $page, $hash, $changed, $quotes, $aiStatus) {
                    $run = DB::table('m19_runs')->insertGetId(['source_id' => $source->id,
                        'status' => $changed ? 'changed' : 'unchanged', 'http_status' => $page['status'],
                        'content_hash' => $hash, 'ai_status' => $aiStatus, 'created_at' => now()]);
                    if ($changed) {
                        foreach (array_slice($quotes, 0, 3) as $quote) {
                            $exists = DB::table('m19_candidates')->where('source_id', $source->id)
                                ->where('status', 'pending')->where('quote', $quote)->exists();
                            if (! $exists) {
                                DB::table('m19_candidates')->insert(['source_id' => $source->id,
                                    'run_id' => $run, 'field' => $source->field, 'url' => $source->url,
                                    'quote' => $quote, 'content_hash' => $hash, 'status' => 'pending',
                                    'created_at' => now(), 'updated_at' => now()]);
                            }
                        }
                    }
                    DB::table('m19_sources')->where('id', $source->id)->update(['last_hash' => $hash]);
                });
                $result['changed'] += (int) $changed;
            } catch (Throwable $e) {
                DB::table('m19_runs')->insert(['source_id' => $source->id, 'status' => 'error',
                    'error_code' => mb_substr($e->getMessage(), 0, 60), 'created_at' => now()]);
                $result['errors']++;
            }
        }

        return $result;
    }
}
