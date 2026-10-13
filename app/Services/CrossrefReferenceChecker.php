<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CrossrefReferenceChecker
{
    public function __construct(private ReferenceTextParser $parser) {}

    public function check(string $reference): array
    {
        $fields = $this->parser->fields($reference);
        $withDoi = is_string($fields['doi']) && $fields['doi'] !== '';
        $response = $withDoi
            ? $this->metadataByDoi($fields['doi'])
            : $this->searchByReference($reference);

        if ($response['state'] === 'unavailable') {
            return [
                'match_status' => 'unavailable',
                'doi_input' => $fields['doi'],
                'metadata' => null,
                'title_similarity' => null,
                'differences' => [],
                'updates' => [],
                'update_status' => null,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if ($response['state'] === 'not_found' || ! is_array($response['metadata'] ?? null)) {
            return [
                'match_status' => 'not_found',
                'doi_input' => $fields['doi'],
                'metadata' => null,
                'title_similarity' => null,
                'differences' => [],
                'updates' => [],
                'update_status' => null,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $metadata = $this->publicMetadata($response['metadata']);
        if (! $metadata || ! $metadata['doi']) {
            return [
                'match_status' => 'unavailable',
                'doi_input' => $fields['doi'],
                'metadata' => null,
                'title_similarity' => null,
                'differences' => [],
                'updates' => [],
                'update_status' => null,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $similarity = $fields['title'] ? $this->titleSimilarity($fields['title'], $metadata['title']) : null;
        $differences = [];
        if ($fields['title'] && $similarity !== null && $similarity < 90) {
            $differences[] = 'Judul berbeda dari metadata Crossref.';
        }
        if ($fields['year'] && $metadata['year'] && $fields['year'] !== $metadata['year']) {
            $differences[] = 'Tahun berbeda dari metadata Crossref.';
        }
        if ($fields['first_author'] && $metadata['first_author'] && ! $this->sameAuthor($fields['first_author'], $metadata['first_author'])) {
            $differences[] = 'Penulis pertama berbeda dari metadata Crossref.';
        }

        $updates = $this->updates($response['metadata']);
        $isExactDoi = $withDoi && strtolower($fields['doi']) === strtolower($metadata['doi']);

        return [
            'match_status' => $withDoi
                ? ($differences ? 'matched_with_difference' : 'matched')
                : 'possible_match',
            'doi_input' => $fields['doi'],
            'metadata' => $metadata,
            'title_similarity' => $similarity,
            'differences' => $differences,
            'updates' => $updates,
            'update_status' => $isExactDoi ? $this->updateStatus($updates) : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function metadataByDoi(string $doi): array
    {
        $key = 'reference-crossref-doi-v1-'.hash('sha256', strtolower($doi));

        try {
            $metadata = Cache::remember($key, now()->addDays(7), function () use ($doi) {
                $response = $this->request('https://api.crossref.org/works/'.rawurlencode($doi));
                if ($response === null) {
                    throw new \RuntimeException('Crossref temporarily unavailable.');
                }
                if ($response->status() === 404) {
                    return ['_not_found' => true];
                }
                if (! $response->successful() || ! is_array($message = $response->json('message'))) {
                    throw new \RuntimeException('Crossref temporarily unavailable.');
                }

                return $this->cacheableMetadata($message);
            });

            return ! empty($metadata['_not_found'])
                ? ['state' => 'not_found']
                : ['state' => 'found', 'metadata' => $metadata];
        } catch (\Throwable) {
            return ['state' => 'unavailable'];
        }
    }

    private function searchByReference(string $reference): array
    {
        $response = $this->request('https://api.crossref.org/works', [
            'query.bibliographic' => $reference,
            'rows' => 1,
        ]);
        if ($response === null || ! $response->successful()) {
            return ['state' => 'unavailable'];
        }

        $items = $response->json('message.items');
        $metadata = is_array($items) ? ($items[0] ?? null) : null;

        return is_array($metadata)
            ? ['state' => 'found', 'metadata' => $metadata]
            : ['state' => 'not_found'];
    }

    private function request(string $url, array $query = []): ?\Illuminate\Http\Client\Response
    {
        if (! $this->acquireRequestSlot()) {
            return null;
        }

        $mailto = trim((string) config('services.crossref.mailto', ''));
        if ($mailto !== '') {
            $query['mailto'] = $mailto;
        }

        try {
            return Http::acceptJson()
                ->withHeaders(['User-Agent' => 'Publikasi-M13/1.0 (https://publikasi.speakverse.id)'])
                ->connectTimeout(3)
                ->timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->get($url, $query);
        } catch (\Throwable) {
            return null;
        }
    }

    private function acquireRequestSlot(): bool
    {
        $hasPoliteIdentity = trim((string) config('services.crossref.mailto', '')) !== '';
        $interval = $hasPoliteIdentity ? 0.5 : 1.0;
        $deadline = microtime(true) + 5;

        do {
            $slot = (int) floor(microtime(true) / $interval);
            if (Cache::add('crossref-reference-slot-'.$slot, true, 3)) {
                return true;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        return false;
    }

    private function cacheableMetadata(array $metadata): array
    {
        return array_intersect_key($metadata, array_flip([
            'DOI', 'title', 'author', 'published', 'published-print', 'published-online', 'issued',
            'container-title', 'updated-by', 'update-to', 'URL',
        ]));
    }

    private function publicMetadata(array $metadata): ?array
    {
        $doi = $metadata['DOI'] ?? null;
        $title = $metadata['title'][0] ?? null;
        if (! is_string($doi) || preg_match('~^10\.\d{4,9}/[^\s]+$~i', $doi) !== 1 || ! is_string($title) || trim($title) === '') {
            return null;
        }

        $authors = [];
        foreach (array_slice(is_array($metadata['author'] ?? null) ? $metadata['author'] : [], 0, 10) as $author) {
            $name = trim(implode(' ', array_filter([$author['given'] ?? null, $author['family'] ?? null], 'is_string')));
            if ($name !== '') {
                $authors[] = $name;
            }
        }
        $year = $metadata['published']['date-parts'][0][0]
            ?? $metadata['published-print']['date-parts'][0][0]
            ?? $metadata['published-online']['date-parts'][0][0]
            ?? $metadata['issued']['date-parts'][0][0]
            ?? null;

        return [
            'doi' => $doi,
            'doi_url' => 'https://doi.org/'.str_replace('%2F', '/', rawurlencode($doi)),
            'title' => mb_substr(strip_tags($title), 0, 1000),
            'authors' => $authors,
            'first_author' => $metadata['author'][0]['family'] ?? null,
            'year' => is_numeric($year) ? (int) $year : null,
            'journal' => mb_substr(strip_tags((string) ($metadata['container-title'][0] ?? '')), 0, 300),
        ];
    }

    private function updates(array $metadata): array
    {
        $updates = [];
        foreach (is_array($metadata['updated-by'] ?? null) ? $metadata['updated-by'] : [] as $update) {
            $type = strtolower(trim((string) ($update['type'] ?? '')));
            $label = trim((string) ($update['label'] ?? ''));
            $noticeDoi = $update['DOI'] ?? null;
            $date = $update['updated']['date-parts'][0] ?? null;
            if ($type === '' || ! is_string($noticeDoi) || preg_match('~^10\.\d{4,9}/[^\s]+$~i', $noticeDoi) !== 1) {
                continue;
            }
            $updates[] = [
                'type' => $type,
                'label' => $label !== '' ? mb_substr(strip_tags($label), 0, 120) : $type,
                'source' => in_array($update['source'] ?? null, ['publisher', 'retraction-watch'], true) ? $update['source'] : 'crossref',
                'notice_doi' => $noticeDoi,
                'notice_url' => 'https://doi.org/'.str_replace('%2F', '/', rawurlencode($noticeDoi)),
                'date' => is_array($date) ? implode('-', array_map(fn ($part) => str_pad((string) $part, 2, '0', STR_PAD_LEFT), $date)) : null,
            ];
        }

        return $updates;
    }

    private function updateStatus(array $updates): ?string
    {
        $types = array_column($updates, 'type');
        if (in_array('retraction', $types, true)) {
            return 'retracted';
        }
        if (array_intersect($types, ['expression-of-concern', 'expression_of_concern', 'expression of concern'])) {
            return 'expression_of_concern';
        }
        if (array_intersect($types, ['correction', 'erratum', 'corrigendum', 'clarification'])) {
            return 'corrected';
        }

        return null;
    }

    private function titleSimilarity(string $referenceTitle, string $metadataTitle): ?float
    {
        $left = $this->normalize($referenceTitle);
        $right = $this->normalize($metadataTitle);
        if ($left === '' || $right === '') {
            return null;
        }
        similar_text($left, $right, $percent);

        return round($percent, 1);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(strip_tags($value))) ?? '');
    }

    private function sameAuthor(string $referenceAuthor, string $metadataAuthor): bool
    {
        return $this->normalize($referenceAuthor) === $this->normalize($metadataAuthor);
    }
}
