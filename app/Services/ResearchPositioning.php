<?php

namespace App\Services;

use RuntimeException;

class ResearchPositioning
{
    public function __construct(private OpenAlexWorks $openAlex, private DinoikiResearchAi $ai) {}

    public function translate(string $title, string $abstract): string
    {
        return $this->ai->translate("Title:\n{$title}\n\nAbstract:\n{$abstract}");
    }

    public function analyze(string $title, string $abstract, string $query, ?int $yearFrom, ?int $yearTo): array
    {
        $sourceWorks = $this->openAlex->search($query, $yearFrom, $yearTo);
        app(M15Budget::class)->claimAnalysis();
        if ($sourceWorks === []) {
            return [
                'works' => [],
                'analysis' => null,
                'mode' => 'empty',
                'candidate_count' => 0,
                'note' => 'OpenAlex tidak menemukan kandidat dengan filter ini. Hasil kosong bukan bukti bahwa ide riset baru.',
                'title' => $title,
                'searched_at' => now()->utc()->toIso8601String(),
            ];
        }

        $mode = 'semantic';
        $works = $sourceWorks;
        try {
            $vectors = $this->ai->embed(array_merge([$query], array_map(
                fn ($work) => $work['title']."\n".mb_substr($work['abstract'] ?? '', 0, 1200),
                $sourceWorks,
            )));
            if (count($vectors) !== count($sourceWorks) + 1) {
                throw new RuntimeException('Embedding tidak lengkap.');
            }
            $queryVector = array_shift($vectors);
            foreach ($sourceWorks as $index => $work) {
                $work['score'] = max(-1, min(1, array_sum(array_map(fn ($a, $b) => $a * $b, $queryVector, $vectors[$index]))));
                $works[$index] = $work;
            }
            usort($works, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']));
        } catch (\Throwable) {
            $mode = 'fallback';
            foreach ($works as $index => $work) {
                $work['score'] = null;
                $works[$index] = $work;
            }
        }

        $works = array_slice($works, 0, (int) config('m15.result_limit', 10));
        $analysis = null;
        $note = $mode === 'fallback'
            ? 'Pemeringkatan semantik tidak tersedia. Urutan berikut mengikuti relevansi pencarian OpenAlex.'
            : 'Skor menunjukkan kedekatan semantik terhadap kandidat yang ditemukan, bukan ukuran kebaruan, plagiarisme, atau peluang diterima.';
        try {
            $analysis = $this->ai->analyze($query, $works);
            foreach ($works as &$work) {
                $assessment = collect($analysis['work_assessments'] ?? [])->firstWhere('id', $work['id']);
                $work['similarity_reason'] = $assessment['similarity'] ?? null;
                $work['difference_reason'] = $assessment['difference'] ?? null;
            }
            unset($work);
        } catch (\Throwable) {
            $note .= ' Analisis AI gagal atau mencapai batas biaya; daftar publikasi terdekat tetap ditampilkan.';
        }

        $hasAbstract = count(array_filter($works, fn ($work) => $work['abstract_available'])) > 0;
        if (! $hasAbstract) {
            $note .= ' Kandidat yang ditemukan tidak memiliki abstrak, sehingga perbandingan variabel dan metode tidak tersedia.';
        }

        return [
            'works' => $works,
            'analysis' => $analysis,
            'mode' => $mode,
            'candidate_count' => count($sourceWorks),
            'note' => $note,
            'searched_at' => now()->utc()->toIso8601String(),
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'title' => $title,
        ];
    }
}
