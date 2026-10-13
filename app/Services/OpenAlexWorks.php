<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAlexWorks
{
    public function search(string $query, ?int $yearFrom = null, ?int $yearTo = null): array
    {
        $params = [
            'search' => $this->searchTerms($query),
            'per_page' => (int) config('m15.candidate_limit', 50),
            'select' => 'id,doi,title,display_name,publication_year,primary_location,abstract_inverted_index',
        ];
        if ($yearFrom && $yearTo) {
            $params['filter'] = 'publication_year:'.$yearFrom.'-'.$yearTo;
        } elseif ($yearFrom) {
            $params['filter'] = 'publication_year:>'.($yearFrom - 1);
        } elseif ($yearTo) {
            $params['filter'] = 'publication_year:<'.($yearTo + 1);
        }
        if (filled(config('services.openalex.mailto'))) {
            $params['mailto'] = config('services.openalex.mailto');
        }

        try {
            $request = Http::acceptJson()->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false]);
            if (filled(config('services.openalex.key'))) {
                $request = $request->withToken(config('services.openalex.key'));
            }
            $response = $request->get('https://api.openalex.org/works', $params);
            if (! $response->successful() || ! is_array($response->json('results'))) {
                throw new RuntimeException;
            }
        } catch (\Throwable) {
            throw new RuntimeException('Pencarian OpenAlex sedang tidak tersedia. Coba lagi nanti.');
        }

        $reportedCost = $response->json('meta.cost_usd');
        app(M15Budget::class)->recordOpenAlex(is_numeric($reportedCost) ? (float) $reportedCost : 0);

        $items = [];
        foreach ($response->json('results', []) as $work) {
            if (! is_array($work)) {
                continue;
            }
            $id = $work['id'] ?? null;
            $title = trim((string) ($work['display_name'] ?? $work['title'] ?? ''));
            if (! is_string($id) || ! preg_match('~^https://openalex\.org/W\d+$~', $id) || $title === '') {
                continue;
            }

            $doi = $this->doi($work['doi'] ?? null);
            $location = $work['primary_location'] ?? [];
            $source = is_array($location) ? ($location['source'] ?? []) : [];
            $landing = is_array($location) ? ($location['landing_page_url'] ?? null) : null;
            $url = $doi ? 'https://doi.org/'.$doi : (is_string($landing) && str_starts_with($landing, 'https://') ? $landing : $id);
            $abstract = $this->abstract($work['abstract_inverted_index'] ?? null);
            $items[] = [
                'id' => $id,
                'title' => mb_substr($title, 0, 500),
                'doi' => $doi,
                'url' => $url,
                'year' => is_numeric($work['publication_year'] ?? null) ? (int) $work['publication_year'] : null,
                'venue' => is_array($source) ? mb_substr((string) ($source['display_name'] ?? ''), 0, 250) : '',
                'abstract' => $abstract,
                'abstract_available' => $abstract !== '',
            ];
        }

        return array_values(array_unique($items, SORT_REGULAR));
    }

    private function abstract(mixed $index): string
    {
        if (! is_array($index) || $index === []) {
            return '';
        }
        $words = [];
        foreach ($index as $word => $positions) {
            if (! is_array($positions)) {
                continue;
            }
            foreach ($positions as $position) {
                if (is_int($position) && $position >= 0 && $position < 20000) {
                    $words[$position] = (string) $word;
                }
            }
        }
        if ($words === []) {
            return '';
        }
        ksort($words);

        return mb_substr(implode(' ', $words), 0, 5000);
    }

    private function doi(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $doi = preg_replace('~^https?://(?:dx\.)?doi\.org/~i', '', trim($value)) ?? '';

        return preg_match('~^10\.\d{4,9}/[^\s<>"]+$~i', $doi) ? mb_substr($doi, 0, 250) : null;
    }

    private function searchTerms(string $query): string
    {
        $text = mb_substr(trim($query), 0, 300);
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [];
        $stop = ['title', 'abstract', 'study', 'research', 'effect', 'effects', 'impact', 'analysis',
            'the', 'and', 'for', 'with', 'from', 'using', 'into', 'this', 'that', 'in', 'of', 'on',
            'dan', 'yang', 'untuk', 'dengan', 'dalam', 'pada', 'terhadap', 'studi', 'penelitian'];
        $words = array_slice(array_values(array_unique(array_filter($tokens,
            fn ($word) => mb_strlen($word) >= 4 && ! in_array($word, $stop, true),
        ))), 0, 6);
        if ($words === []) {
            $words = array_slice(array_values(array_unique(array_filter($tokens, fn ($word) => mb_strlen($word) >= 3))), 0, 2);
        }
        if (count($words) <= 2) {
            return implode(' ', $words);
        }
        $first = array_slice($words, 0, count($words) > 3 ? 2 : 1);
        $second = array_slice($words, count($first));

        return '('.implode(' OR ', $first).') AND ('.implode(' OR ', $second).')';
    }
}
