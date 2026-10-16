<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class IndexedCandidateReranker
{
    public function __construct(private DinoikiEmbeddings $ai) {}

    public function rank(string $title, string $abstract, array $candidates): array
    {
        $scopes = [];
        foreach ($candidates as $i => &$candidate) {
            $candidate['method'] = 'keyword';
            $source = $candidate['scopus'] && filled($candidate['scopus']->scope_excerpt)
                && filled($candidate['scopus']->scope_source_url) ? $candidate['scopus'] : $candidate['sinta'];
            if ($source && filled($source->scope_excerpt) && filled($source->scope_source_url)) {
                $scopes[$i] = mb_substr(strip_tags($source->scope_excerpt), 0, 5500);
                $candidate['scope_evidence_url'] = $source->scope_source_url;
            }
        }
        unset($candidate);
        if ($scopes === []) return ['candidates' => $candidates, 'status' => 'no_scope'];

        try {
            $query = $this->ai->vectors([mb_substr(strip_tags($title."\n".$abstract), 0, 6000)])[0];
            $vectors = [];
            $missing = [];
            foreach ($scopes as $i => $scope) {
                $key = 'indexed-scope:'.hash('sha256', config('semantic.model')."\0".$scope);
                $cached = Cache::get($key);
                if (is_array($cached) && count($cached) === count($query)) {
                    $vectors[$i] = $cached;
                } else {
                    $missing[$i] = ['key' => $key, 'scope' => $scope];
                }
            }
            if ($missing) {
                $fresh = $this->ai->vectors(array_column($missing, 'scope'));
                foreach (array_keys($missing) as $offset => $i) {
                    if (! isset($fresh[$offset]) || count($fresh[$offset]) !== count($query)) {
                        throw new RuntimeException('Dimensi embedding kandidat tidak konsisten.');
                    }
                    $vectors[$i] = $fresh[$offset];
                    Cache::put($missing[$i]['key'], $fresh[$offset], now()->addDays(30));
                }
            }
            foreach ($scopes as $i => $_) {
                $candidates[$i]['method'] = 'ai_scope';
                $candidates[$i]['similarity'] = $this->ai->similarity($query, $vectors[$i]);
            }
        } catch (RuntimeException) {
            return ['candidates' => $candidates, 'status' => 'unavailable'];
        }

        // Preserve the existing Scopus-first and SINTA-rank grouping. AI only
        // orders the scoped candidates within each group.
        usort($candidates, function ($a, $b) {
            $group = static fn ($item) => $item['scopus'] ? 0 : (int) $item['sinta']->rank;
            return ($group($a) <=> $group($b))
                ?: (($b['method'] === 'ai_scope') <=> ($a['method'] === 'ai_scope'))
                ?: (($b['similarity'] ?? -1) <=> ($a['similarity'] ?? -1))
                ?: ($b['score'] <=> $a['score']);
        });

        return ['candidates' => $candidates, 'status' => 'ranked'];
    }
}
