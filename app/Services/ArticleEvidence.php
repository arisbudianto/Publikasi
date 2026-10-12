<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ArticleEvidence
{
    public function forIssn(string $issn): array
    {
        return Cache::remember('crossref-evidence-v1-'.$issn, now()->addHours(6), function () use ($issn) {
            try {
                $r = Http::acceptJson()->withHeaders(['User-Agent' => 'Publikasi-M04/1.0 (https://publikasi.speakverse.id)'])
                    ->connectTimeout(3)->timeout(5)->withOptions(['allow_redirects' => false])->get('https://api.crossref.org/journals/'.rawurlencode($issn).'/works', ['rows' => 3, 'sort' => 'published', 'order' => 'desc', 'filter' => 'type:journal-article']);
                if (! $r->successful()) {
                    return ['articles' => [], 'status' => 'unavailable'];
                }
                $items = $r->json('message.items');
                if (! is_array($items)) {
                    return ['articles' => [], 'status' => 'unavailable'];
                }
                $out = [];
                foreach (array_slice($items, 0, 3) as $item) {
                    $doi = $item['DOI'] ?? '';
                    $title = $item['title'][0] ?? '';
                    if (! is_string($doi) || ! preg_match('~^10\.\d{4,9}/[^\s]+$~i', $doi) || ! is_string($title) || trim($title) === '' || ! in_array(strtoupper($issn), array_map('strtoupper', $item['ISSN'] ?? []), true)) {
                        continue;
                    }
                    $year = $item['published']['date-parts'][0][0] ?? null;
                    $out[] = ['title' => mb_substr(strip_tags($title), 0, 1000), 'doi' => $doi, 'url' => 'https://doi.org/'.rawurlencode($doi), 'year' => is_int($year) ? $year : null, 'source' => 'Crossref', 'checked_at' => now()->format('Y-m-d')];
                }

                return ['articles' => $out, 'status' => $out ? 'available' : 'empty'];
            } catch (\Throwable $e) {
                return ['articles' => [], 'status' => 'unavailable'];
            }
        });
    }
}
