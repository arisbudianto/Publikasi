<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEmbedding;
use Illuminate\Support\Facades\DB;
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
        $query = $this->ai->vectors([mb_substr(strip_tags($title."\n".$abstract."\n".$context), 0, 6000)])[0];
        $scopeTexts = [];
        $scopeHashes = [];
        foreach ($journals as $j) {
            $scope = mb_substr($j->subject."\n".$j->scope_summary, 0, 5500);
            $scopeHashes[$j->id] = hash('sha256', config('semantic.model')."\0".$scope);
            $scopeTexts[$j->id] = $scope;
        }
        $stored = JournalEmbedding::query()->where('model', config('semantic.model'))
            ->whereIn('journal_id', $journals->modelKeys())->get()->keyBy('journal_id');
        $scopes = [];
        $missing = [];
        foreach ($journals as $j) {
            $embedding = $stored->get($j->id);
            if ($embedding && hash_equals($scopeHashes[$j->id], $embedding->scope_hash) && $embedding->dimensions === count($query)) {
                try {
                    $scopes[$j->id] = $embedding->decodedVector();
                    continue;
                } catch (RuntimeException) {
                    // A corrupt stored vector is refreshed from the current scope below.
                }
            }
            $missing[$j->id] = $scopeTexts[$j->id];
        }
        foreach (array_chunk($missing, max(1, (int) config('semantic.embedding_batch_size', 50)), true) as $batch) {
            $vectors = $this->ai->vectors(array_values($batch));
            foreach (array_keys($batch) as $offset => $journalId) {
                $vector = $vectors[$offset];
                if (count($vector) !== count($query)) {
                    throw new RuntimeException('Dimensi embedding tidak konsisten.');
                }
                $scopes[$journalId] = $vector;
                $timestamp = now();
                DB::table('journal_embeddings')->upsert([[
                    'journal_id' => $journalId,
                    'model' => config('semantic.model'),
                    'scope_hash' => $scopeHashes[$journalId],
                    'dimensions' => count($vector),
                    'vector' => JournalEmbedding::encode($vector),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]], ['journal_id', 'model'], ['scope_hash', 'dimensions', 'vector', 'updated_at']);
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
