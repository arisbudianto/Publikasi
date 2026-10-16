<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class ScopusTargetVerifier
{
    public function __construct(private JournalPageFetcher $fetcher) {}

    public function tick(int $limit = 5): array
    {
        if (! config('scopus_crawl.enabled')) return ['status' => 'disabled', 'checked' => 0];
        $today = now()->startOfDay();
        $verifiedToday = DB::table('scopus_page_targets')->where('verification_checked_at', '>=', $today)->count();
        $m19Today = DB::table('m19_runs as r')->join('m19_sources as s', 's.id', '=', 'r.source_id')
            ->where('r.created_at', '>=', $today)->distinct()->count('s.journal_id');
        $sintaToday = DB::table('sinta_page_checks')->where('created_at', '>=', $today)->count();
        $scopusToday = DB::table('scopus_page_checks')->where('created_at', '>=', $today)->count();
        $limit = max(0, min(10, $limit - $verifiedToday,
            20 - $m19Today - $sintaToday - $scopusToday - $verifiedToday));
        $result = ['status' => 'ok', 'checked' => 0, 'activated' => 0, 'rejected' => 0];
        $targets = DB::table('scopus_page_targets as t')->join('scopus_sources as s', 's.id', '=', 't.scopus_source_id')
            ->where('t.enabled', false)->where('t.auto_verify', true)
            ->where('s.is_current', true)->where('s.discontinued', false)->where('s.source_type', 'Journal')
            ->where(fn ($q) => $q->whereNull('t.next_check_at')->orWhere('t.next_check_at', '<=', now()))
            ->orderBy('t.id')->limit($limit)->get(['t.id', 't.url', 's.source_title', 's.issn', 's.eissn']);

        foreach ($targets as $target) {
            $claimed = DB::table('scopus_page_targets')->where('id', $target->id)
                ->where('enabled', false)->where('auto_verify', true)
                ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                ->update(['next_check_at' => now()->addDays(7), 'verification_checked_at' => now(), 'updated_at' => now()]);
            if (! $claimed) continue;
            $result['checked']++;
            try {
                $source = $this->matchingSintaSource($target);
                if (! $source) throw new \RuntimeException('source_identity_mismatch');
                $page = $this->fetcher->fetch($target->url);
                if (! $this->pageIdentifiesJournal($page['body'], $target, $source)) {
                    throw new \RuntimeException('page_identity_mismatch');
                }
                $result['activated'] += DB::table('scopus_page_targets')->where('id', $target->id)
                    ->where('enabled', false)->where('auto_verify', true)
                    ->update(['enabled' => true, 'auto_verify' => false, 'verification_error' => null,
                        'next_check_at' => now(), 'updated_at' => now()]);
            } catch (Throwable $e) {
                DB::table('scopus_page_targets')->where('id', $target->id)->where('auto_verify', true)
                    ->update(['verification_error' => mb_substr($e->getMessage(), 0, 60), 'updated_at' => now()]);
                $result['rejected']++;
            }
        }
        return $result;
    }

    private function matchingSintaSource(object $target): ?object
    {
        $issns = array_values(array_unique(array_filter([$this->issn($target->issn), $this->issn($target->eissn)])));
        if (! $issns) return null;
        $sources = DB::table('journal_sources')->where('official_url', $target->url)
            ->get(['name', 'p_issn', 'e_issn'])
            ->filter(fn ($s) => count(array_intersect($issns, array_filter([
                $this->issn($s->p_issn), $this->issn($s->e_issn),
            ]))) > 0);
        return $sources->count() === 1 ? $sources->first() : null;
    }

    private function pageIdentifiesJournal(string $html, object $target, object $source): bool
    {
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match);
        $title = $this->text($match[1] ?? '');
        $names = [$target->source_title, $source->name];
        $nameMatches = collect($names)->contains(function ($name) use ($title) {
            $expected = $this->text($name);
            return mb_strlen($expected) >= 6 && mb_strlen($title) >= 6
                && (str_contains($title, $expected) || str_contains($expected, $title));
        });
        if (! $nameMatches) return false;
        $body = $this->text($html);
        foreach ([$target->issn, $target->eissn] as $value) {
            if (($issn = $this->issn($value)) && preg_match('/(?<![0-9])'.substr($issn, 0, 4).'[\s-]?'.substr($issn, 4).'(?![0-9])/i', $body)) {
                return true;
            }
        }
        return false;
    }

    private function issn(?string $value): ?string
    {
        $value = strtoupper(str_replace('-', '', trim((string) $value)));
        return preg_match('/^[0-9]{7}[0-9X]$/D', $value) ? $value : null;
    }

    private function text(string $html): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'))));
    }
}
