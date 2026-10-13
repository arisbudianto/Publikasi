<?php

namespace App\Services;

use App\Models\M16Finding;
use App\Models\M16Topic;
use App\Models\M16Version;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TopicMonitoring
{
    public function __construct(private OpenAlexWorks $openAlex) {}

    public function ready(): bool
    {
        if (! config('m16.enabled')) {
            return false;
        }
        $runs = DB::table('m16_runs')->where('source', 'cron')->whereNotNull('finished_at')
            ->latest('id')->limit(2)->pluck('finished_at');

        return $runs->count() === 2
            && Carbon::parse($runs[0])->greaterThanOrEqualTo(now()->subHours(config('m16.cron_fresh_hours', 36)))
            && Carbon::parse($runs[1])->diffInMinutes(Carbon::parse($runs[0])) >= 10;
    }

    public function create(int $userId, string $title, string $summary): M16Topic
    {
        if (! $this->ready()) {
            throw new RuntimeException('Pantauan menunggu verifikasi cron cPanel.');
        }
        if (M16Topic::where('user_id', $userId)->count() >= config('m16.max_topics')) {
            throw new RuntimeException('Batas tiga topik pantauan per akun tercapai.');
        }
        $works = $this->search($title, $summary);

        return DB::transaction(function () use ($userId, $title, $summary, $works) {
            $topic = M16Topic::create(['user_id' => $userId, 'current_version' => 1, 'last_checked_at' => now()]);
            $topic->versions()->create(['number' => 1, 'title_cipher' => $title, 'summary_cipher' => $summary, 'works' => $works, 'checked_at' => now()]);

            return $topic;
        });
    }

    public function revise(M16Topic $topic, string $title, string $summary): void
    {
        if (! $this->ready()) {
            throw new RuntimeException('Pantauan menunggu verifikasi cron cPanel.');
        }
        if ($topic->current_version >= config('m16.max_versions')) {
            throw new RuntimeException('Batas lima versi per topik tercapai.');
        }
        $works = $this->search($title, $summary);
        DB::transaction(function () use ($topic, $title, $summary, $works) {
            $locked = M16Topic::whereKey($topic->id)->lockForUpdate()->firstOrFail();
            if ($locked->current_version >= config('m16.max_versions')) {
                throw new RuntimeException('Batas lima versi per topik tercapai.');
            }
            $next = $locked->current_version + 1;
            $locked->versions()->create(['number' => $next, 'title_cipher' => $title, 'summary_cipher' => $summary, 'works' => $works, 'checked_at' => now()]);
            $locked->update(['current_version' => $next, 'last_checked_at' => now()]);
        });
    }

    public function tick(): array
    {
        $run = DB::table('m16_runs')->insertGetId(['source' => 'cron', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $checked = 0;
        $failures = 0;
        try {
            if (config('m16.enabled')) {
                $topics = M16Topic::where(function ($query) {
                    $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subHours(config('m16.interval_hours')));
                })->orderBy('last_checked_at')->limit(config('m16.requests_per_run'))->get();
                foreach ($topics as $topic) {
                    try {
                        $version = $topic->versions()->where('number', $topic->current_version)->firstOrFail();
                        $works = $this->search($version->title_cipher, $version->summary_cipher, $run);
                        $previous = collect($version->works ?? [])->pluck('id')->all();
                        DB::transaction(function () use ($topic, $version, $works, $previous) {
                            foreach ($works as $work) {
                                if (! in_array($work['id'], $previous, true)) {
                                    M16Finding::firstOrCreate(['topic_id' => $topic->id, 'work_id' => $work['id']], ['work' => $work, 'seen_at' => now()]);
                                }
                            }
                            $version->update(['works' => $works, 'checked_at' => now()]);
                            $topic->update(['last_checked_at' => now()]);
                        });
                        $checked++;
                    } catch (\Throwable) {
                        $failures++;
                    }
                }
            }
        } finally {
            DB::table('m16_runs')->where('id', $run)->update(['finished_at' => now(), 'failures' => $failures, 'updated_at' => now()]);
        }

        return ['checked' => $checked, 'failures' => $failures, 'run_id' => $run];
    }

    public function compare(M16Version $older, M16Version $newer): array
    {
        $before = collect($older->works ?? [])->keyBy('id');
        $after = collect($newer->works ?? [])->keyBy('id');

        return ['added' => $after->except($before->keys()->all())->values()->all(),
            'removed' => $before->except($after->keys()->all())->values()->all(),
            'shared' => $after->only($before->keys()->all())->values()->all()];
    }

    private function search(string $title, string $summary, ?int $runId = null): array
    {
        $this->claimRequest($runId);
        $query = mb_substr(trim($title.' '.$summary), 0, 800);
        $works = $this->openAlex->search($query, now()->year - 1);
        $terms = $this->terms($title.' '.$summary);
        $selected = [];
        foreach ($works as $work) {
            $other = $this->terms($work['title'].' '.($work['abstract'] ?? ''));
            $common = count(array_intersect($terms, $other));
            $score = $common / max(1, min(8, count($terms)));
            if ($common < 2 || $score < config('m16.match_threshold')) {
                continue;
            }
            $selected[] = [
                'id' => $work['id'], 'title' => $work['title'], 'doi' => $work['doi'],
                'url' => $work['url'], 'year' => $work['year'], 'venue' => $work['venue'],
                'score' => round($score, 3),
            ];
            if (count($selected) >= 10) {
                break;
            }
        }

        return $selected;
    }

    private function claimRequest(?int $runId): void
    {
        Cache::lock('m16:monthly-cap', 10)->block(5, function () use ($runId) {
            $count = DB::table('m16_runs')->where('started_at', '>=', now()->startOfMonth())
                ->where('started_at', '<', now()->startOfMonth()->addMonth())->sum('requests');
            if ($count >= config('m16.requests_per_month')) {
                throw new RuntimeException('Batas pemeriksaan bulanan tercapai.');
            }
            if ($runId) {
                DB::table('m16_runs')->where('id', $runId)->increment('requests');
            } else {
                DB::table('m16_runs')->insert(['started_at' => now(), 'finished_at' => now(), 'requests' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    private function terms(string $text): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_unique(array_filter($tokens, fn ($token) => mb_strlen($token) >= 4)));
    }
}
