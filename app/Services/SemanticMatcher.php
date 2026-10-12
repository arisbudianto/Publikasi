<?php

namespace App\Services;

use App\Models\Journal;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SemanticMatcher
{
    public function __construct(private DinoikiEmbeddings $ai, private ArticleEvidence $evidence) {}

    public function match(string $title, string $abstract, string $context): array
    {
        $journals = Journal::where('status', 'published')->orderBy('id')->limit(config('semantic.catalog_limit') + 1)->get();
        if ($journals->count() > config('semantic.catalog_limit')) {
            throw new RuntimeException('Katalog melebihi batas pemrosesan AI saat ini. Gunakan pencocokan dasar.');
        }
        if ($journals->isEmpty()) {
            return [];
        }
        $texts = [mb_substr(strip_tags($title."\n".$abstract."\n".$context), 0, 6000)];
        $keys = [];
        $scopes = [];
        foreach ($journals as $j) {
            $scope = mb_substr($j->subject."\n".$j->scope_summary, 0, 5500);
            $key = 'semantic-scope-v1-'.hash('sha256', config('semantic.model').$scope);
            $keys[$j->id] = $key;
            $cached = Cache::get($key);
            if (is_array($cached)) {
                $scopes[$j->id] = $cached;
            } else {
                $texts[] = $scope;
            }
        }
        $vectors = $this->ai->vectors($texts);
        $query = array_shift($vectors);
        foreach ($journals as $j) {
            if (! isset($scopes[$j->id])) {
                $scopes[$j->id] = array_shift($vectors);
                Cache::put($keys[$j->id], $scopes[$j->id], now()->addDay());
            }
        }
        $results = [];
        foreach ($journals as $j) {
            $score = $this->ai->similarity($query, $scopes[$j->id]);
            if ($score < 0.3) {
                continue;
            }$results[] = ['journal' => $j, 'similarity' => $score, 'evidence' => ['articles' => [], 'status' => 'empty']];
        }
        usort($results, fn ($a, $b) => ($b['similarity'] <=> $a['similarity']) ?: ($a['journal']->id <=> $b['journal']->id));
        $results = array_slice($results, 0, 3);
        $articleTexts = [];
        $positions = [];
        foreach ($results as $i => &$r) {
            $r['evidence'] = $this->evidence->forIssn($r['journal']->issn);
            foreach ($r['evidence']['articles'] as $n => $article) {
                $positions[] = [$i, $n];
                $articleTexts[] = $article['title'];
            }
        }unset($r);
        if ($articleTexts) {
            try {
                $articleVectors = $this->ai->vectors($articleTexts);
                foreach ($positions as $i => [$r,$n]) {
                    $results[$r]['evidence']['articles'][$n]['similarity'] = $this->ai->similarity($query, $articleVectors[$i]);
                }
                foreach ($results as &$r) {
                    usort($r['evidence']['articles'], fn ($a, $b) => ($b['similarity'] ?? -1) <=> ($a['similarity'] ?? -1));
                }unset($r);
            } catch (RuntimeException $e) {
                foreach ($results as &$r) {
                    $r['evidence']['ranking_unavailable'] = true;
                }unset($r);
            }
        }

        return $results;
    }
}
