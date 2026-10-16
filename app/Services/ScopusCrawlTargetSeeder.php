<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScopusCrawlTargetSeeder
{
    public function __construct(private JournalPageFetcher $fetcher) {}

    public function run(int $limit = 50, bool $apply = false, bool $adoptExisting = false): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('limit_out_of_range');
        }
        $normalize = static function (?string $value): ?string {
            $value = strtoupper(str_replace('-', '', trim((string) $value)));
            return preg_match('/^[0-9]{7}[0-9X]$/D', $value) ? $value : null;
        };
        $index = [];
        foreach (DB::table('scopus_sources')->where('is_current', true)->where('discontinued', false)
            ->where('source_type', 'Journal')->get(['id', 'issn', 'eissn']) as $journal) {
            foreach ([$journal->issn, $journal->eissn] as $value) {
                if ($key = $normalize($value)) {
                    $index[$key][$journal->id] = true;
                }
            }
        }
        $existing = DB::table('scopus_page_targets')->where('field', 'scope')
            ->pluck('scopus_source_id')->flip()->all();
        $pairs = [];
        $adopted = 0;
        foreach (DB::table('journal_sources')->where('official_url', 'like', 'https://%')
            ->orderBy('id')->get(['id', 'p_issn', 'e_issn', 'official_url']) as $source) {
            try {
                $this->fetcher->host($source->official_url);
            } catch (RuntimeException) {
                continue;
            }
            $ids = [];
            foreach ([$source->p_issn, $source->e_issn] as $value) {
                if ($key = $normalize($value)) {
                    foreach ($index[$key] ?? [] as $id => $_) {
                        $ids[$id] = true;
                    }
                }
            }
            // A source mapped to multiple Scopus records needs manual identification.
            if (count($ids) !== 1) {
                continue;
            }
            $id = array_key_first($ids);
            if (isset($existing[$id])) {
                if ($apply && $adoptExisting && $adopted < $limit) {
                    $adopted += DB::table('scopus_page_targets')->where('scopus_source_id', $id)
                        ->where('url', $source->official_url)->where('field', 'scope')
                        ->where('enabled', false)->where('auto_verify', false)
                        ->whereNull('last_hash')->whereNull('next_check_at')
                        ->whereNotExists(function ($query) {
                            $query->selectRaw('1')->from('scopus_page_checks as c')
                                ->whereColumn('c.target_id', 'scopus_page_targets.id');
                        })->update(['auto_verify' => true, 'updated_at' => now()]);
                }
                continue;
            }
            if (isset($pairs[$id])) {
                continue;
            }
            $pairs[$id] = ['scopus_source_id' => $id, 'url' => $source->official_url,
                'field' => 'scope', 'enabled' => false, 'auto_verify' => true,
                'created_at' => now(), 'updated_at' => now()];
            if (count($pairs) >= $limit) {
                break;
            }
        }
        if ($apply) {
            foreach ($pairs as $row) {
                DB::table('scopus_page_targets')->insertOrIgnore($row);
            }
        }
        return ['eligible' => count($pairs), 'created' => $apply ? count($pairs) : 0,
            'adopted' => $adopted, 'mode' => $apply ? 'apply' : 'dry-run'];
    }
}
