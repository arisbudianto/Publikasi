<?php

namespace App\Services;

use App\Models\JournalSource;
use App\Models\ScopusSource;
use Illuminate\Support\Str;

class IndexedCandidateMatcher
{
    public function __construct(private BasicMatcher $matcher) {}

    public function match(string $title, string $abstract, string $index = 'all'): array
    {
        $terms = array_slice(array_values(array_unique(array_merge($this->matcher->terms($title), $this->matcher->terms($abstract)))), 0, 12);
        if ($terms === []) return [];
        $raw = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($title.' '.$abstract)), -1, PREG_SPLIT_NO_EMPTY);
        $searchTerms = array_slice(array_values(array_unique(array_merge($terms, array_filter($raw, fn ($word) => strlen($word) >= 4 && $this->matcher->terms($word) !== [])))), 0, 30);

        $results = [];
        if ($index !== 'scopus') {
            // A source is eligible only when linked by ISSN to a published accreditation decision.
            $query = JournalSource::query()->whereNotNull('name')->whereHas('accreditations', fn ($q) => $q->where('match_status', 'matched')->where('e_issn_checksum_valid', true)->whereBetween('rank', [1, 4])->where('valid_to_year', '>=', now()->year));
            $this->whereTerms($query, ['name', 'subject', 'article_titles'], $searchTerms);
            foreach ($query->with(['accreditations' => fn ($q) => $q->where('match_status', 'matched')->where('e_issn_checksum_valid', true)->whereBetween('rank', [1, 4])->where('valid_to_year', '>=', now()->year)->orderByDesc('decree_date')])->orderBy('id')->limit(500)->get() as $source) {
                $evidence = trim($source->subject.' '.implode(' ', array_slice($source->article_titles ?? [], 0, 10)));
                $matches = array_intersect($terms, $this->matcher->terms($evidence));
                if ($matches === []) continue;
                $results[] = ['name' => $source->name, 'issn' => $source->e_issn ?: $source->p_issn, 'publisher' => $source->publisher,
                    'evidence' => 'Subjek dan judul artikel pada data sumber SINTA', 'matches' => array_values($matches),
                    'score' => count($matches), 'sinta' => $source->accreditations->first(), 'scopus' => null,
                    'url' => route('sources.show', $source)];
            }
        }
        if ($index !== 'sinta') {
            $query = ScopusSource::query()->where('is_current', true)->where('source_type', 'Journal')->where('status', 'Active')->where('discontinued', false);
            // The source list has no journal scope: its title is only preliminary topical evidence.
            $this->whereTerms($query, ['source_title'], $searchTerms);
            foreach ($query->orderBy('id')->limit(1000)->get() as $source) {
                $matches = array_intersect($terms, $this->matcher->terms($source->source_title));
                if ($matches === []) continue;
                $results[] = ['name' => $source->source_title, 'issn' => $source->eissn ?: $source->issn, 'publisher' => $source->publisher,
                    'evidence' => 'Nama jurnal pada daftar Scopus; scope belum tersedia', 'matches' => array_values($matches),
                    'score' => count($matches), 'sinta' => null, 'scopus' => $source,
                    'url' => route('scopus.index', ['q' => $source->eissn ?: $source->issn ?: $source->source_title])];
            }
        }

        // Merge the two index labels only on an exact normalized ISSN, never a fuzzy title.
        $merged = [];
        foreach ($results as $result) {
            $key = strtoupper(str_replace('-', '', (string) $result['issn']));
            $key = strlen($key) === 8 ? $key : ($result['sinta'] ? 'sinta:' : 'scopus:').$result['name'];
            if (! isset($merged[$key])) $merged[$key] = $result;
            elseif ($result['scopus']) $merged[$key]['scopus'] = $result['scopus'];
        }
        // Scopus Source Title List does not contain quartiles. Keep those entries
        // separate from verified SINTA ranks instead of inferring Q from status.
        usort($merged, function ($a, $b) {
            $group = static fn ($item) => $item['scopus'] ? 0 : (($item['sinta']?->rank >= 1 && $item['sinta']?->rank <= 4) ? (int) $item['sinta']->rank : 5);

            return ($group($a) <=> $group($b))
                ?: ($b['score'] <=> $a['score'])
                ?: strcmp($a['name'], $b['name']);
        });

        return array_slice($merged, 0, 20);
    }

    private function whereTerms($query, array $columns, array $terms): void
    {
        $query->where(function ($q) use ($columns, $terms) {
            foreach ($terms as $term) {
                $escaped = addcslashes($term, '%_\\');
                foreach ($columns as $column) $q->orWhere($column, 'like', '%'.$escaped.'%');
            }
        });
    }
}
