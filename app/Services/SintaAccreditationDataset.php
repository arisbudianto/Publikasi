<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalAccreditation;
use App\Models\JournalSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SintaAccreditationDataset
{
    public function preview(): array
    {
        $root = database_path('datasets/sinta-accreditation-2025-period-3');
        $manifest = json_decode(file_get_contents($root.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];
        $seen = [];
        $rankCounts = array_fill_keys(array_map('strval', range(1, 6)), 0);
        $validIssnCount = 0;
        $invalidIssnCount = 0;

        foreach ($manifest['parts'] as $part) {
            if (! preg_match('/^part-\d{3}\.json$/', $part['file'])) {
                throw new RuntimeException('Nama bagian dataset akreditasi tidak valid.');
            }
            $raw = file_get_contents($root.'/'.$part['file']);
            if (! hash_equals($part['sha256'], hash('sha256', $raw))) {
                throw new RuntimeException('Checksum bagian dataset akreditasi tidak sesuai.');
            }
            $batch = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (count($batch) !== $part['count']) {
                throw new RuntimeException('Jumlah bagian dataset akreditasi tidak sesuai.');
            }

            foreach ($batch as $row) {
                Validator::make($row, [
                    'item_key' => 'required|string|max:80',
                    'rank' => 'required|integer|between:1,6',
                    'journal_name' => 'required|string|max:255',
                    'publisher' => 'nullable|string|max:1000',
                    'e_issn_raw' => ['nullable', 'string', 'regex:/^\d{7,8}[X]?$/'],
                    'e_issn_checksum_valid' => 'required|boolean',
                    'decision_type' => 'required|string|max:40',
                    'previous_rank' => 'nullable|integer|between:1,6',
                    'valid_from' => 'required|array:volume,issue,year',
                    'valid_from.volume' => 'required|integer|min:1|max:999',
                    'valid_from.issue' => 'required|integer|min:1|max:99',
                    'valid_from.year' => 'required|integer|between:1900,2200',
                    'valid_to' => 'required|array:volume,issue,year',
                    'valid_to.volume' => 'required|integer|min:1|max:999',
                    'valid_to.issue' => 'required|integer|min:1|max:99',
                    'valid_to.year' => 'required|integer|between:1900,2200',
                    'decision_text' => 'required|string|max:1000',
                    'source_page' => 'required|integer|between:1,244',
                ])->validate();

                if (isset($seen[$row['item_key']]) || ! str_starts_with($row['item_key'], '355-2026:'.$row['rank'].':')) {
                    throw new RuntimeException('Kunci keputusan akreditasi duplikat atau tidak sesuai.');
                }
                $seen[$row['item_key']] = true;
                $rankCounts[(string) $row['rank']]++;
                $expectedChecksum = $this->validIssn($row['e_issn_raw']);
                if ($expectedChecksum !== $row['e_issn_checksum_valid']) {
                    throw new RuntimeException('Status checksum e-ISSN tidak cocok dengan nilainya.');
                }
                $validIssnCount += (int) $expectedChecksum;
                $invalidIssnCount += (int) ($row['e_issn_raw'] !== null && ! $expectedChecksum);
                $rows[] = $row;
            }
        }

        if (count($rows) !== $manifest['record_count'] || $rankCounts !== $manifest['rank_counts'] || $validIssnCount !== $manifest['eissn_checksum_valid'] || $invalidIssnCount !== $manifest['eissn_checksum_invalid']) {
            throw new RuntimeException('Total dataset akreditasi tidak sesuai manifest.');
        }

        $existing = JournalAccreditation::whereIn('item_key', array_keys($seen))->count();
        $sourceMap = $this->issnMap(JournalSource::query()->get(['id', 'p_issn', 'e_issn'])->all(), ['p_issn', 'e_issn']);
        $journalMap = $this->issnMap(Journal::query()->get(['id', 'issn'])->all(), ['issn']);
        $matchCounts = ['matched' => 0, 'matched_sources' => 0, 'matched_journals' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'invalid_issn' => 0, 'missing_issn' => 0];
        foreach ($rows as $row) {
            $match = $this->match($row, $sourceMap, $journalMap);
            $matchCounts[$match['status']]++;
            $matchCounts['matched_sources'] += (int) ($match['source_id'] !== null);
            $matchCounts['matched_journals'] += (int) ($match['journal_id'] !== null);
        }

        return ['manifest' => $manifest, 'rows' => $rows, 'new' => count($rows) - $existing, 'existing' => $existing, 'matches' => $matchCounts];
    }

    public function import(): array
    {
        $preview = $this->preview();

        return DB::transaction(function () use ($preview) {
            $sourceMap = $this->issnMap(JournalSource::query()->get(['id', 'p_issn', 'e_issn'])->all(), ['p_issn', 'e_issn']);
            $journalMap = $this->issnMap(Journal::query()->get(['id', 'issn'])->all(), ['issn']);
            $created = 0;
            $linkedSources = 0;
            $linkedJournals = 0;
            $statuses = ['matched' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'invalid_issn' => 0, 'missing_issn' => 0];

            foreach ($preview['rows'] as $row) {
                $match = $this->match($row, $sourceMap, $journalMap);
                $attributes = [
                    'rank' => $row['rank'],
                    'journal_name' => $row['journal_name'],
                    'publisher' => $row['publisher'],
                    'e_issn_raw' => $row['e_issn_raw'],
                    'e_issn_checksum_valid' => $row['e_issn_checksum_valid'],
                    'decision_type' => $row['decision_type'],
                    'previous_rank' => $row['previous_rank'],
                    'valid_from_volume' => $row['valid_from']['volume'],
                    'valid_from_issue' => $row['valid_from']['issue'],
                    'valid_from_year' => $row['valid_from']['year'],
                    'valid_to_volume' => $row['valid_to']['volume'],
                    'valid_to_issue' => $row['valid_to']['issue'],
                    'valid_to_year' => $row['valid_to']['year'],
                    'decision_text' => $row['decision_text'],
                    'source_page' => $row['source_page'],
                    'decree_number' => $preview['manifest']['decree_number'],
                    'decree_date' => $preview['manifest']['decree_date'],
                    'period' => $preview['manifest']['period'],
                    'source_pdf_sha256' => $preview['manifest']['source_pdf_sha256'],
                    'journal_source_id' => $match['source_id'],
                    'journal_id' => $match['journal_id'],
                    'match_status' => $match['status'],
                ];
                $entry = JournalAccreditation::firstOrCreate(['item_key' => $row['item_key']], $attributes);
                if ($entry->wasRecentlyCreated) {
                    $created++;
                    $linkedSources += (int) ($match['source_id'] !== null);
                    $linkedJournals += (int) ($match['journal_id'] !== null);
                    $statuses[$match['status']]++;
                } elseif ($entry->source_pdf_sha256 !== $preview['manifest']['source_pdf_sha256']) {
                    throw new RuntimeException('Dataset keputusan sudah ada dengan fingerprint berbeda; tidak ditimpa.');
                }
            }

            return ['created' => $created, 'skipped' => count($preview['rows']) - $created, 'linked_sources' => $linkedSources, 'linked_journals' => $linkedJournals, 'match_status' => $statuses];
        });
    }

    private function issnMap(array $models, array $fields): array
    {
        $map = [];
        foreach ($models as $model) {
            foreach ($fields as $field) {
                $issn = strtoupper(str_replace('-', '', (string) $model->{$field}));
                if ($issn !== '') {
                    $map[$issn][$model->id] = true;
                }
            }
        }

        return array_map(fn ($ids) => array_keys($ids), $map);
    }

    private function match(array $row, array $sourceMap, array $journalMap): array
    {
        $issn = strtoupper((string) $row['e_issn_raw']);
        if ($issn === '') {
            return ['status' => 'missing_issn', 'source_id' => null, 'journal_id' => null];
        }
        if (! $row['e_issn_checksum_valid']) {
            return ['status' => 'invalid_issn', 'source_id' => null, 'journal_id' => null];
        }
        $sources = $sourceMap[$issn] ?? [];
        $journals = $journalMap[$issn] ?? [];
        if (count($sources) > 1 || count($journals) > 1) {
            return ['status' => 'ambiguous', 'source_id' => null, 'journal_id' => null];
        }

        return [
            'status' => $sources || $journals ? 'matched' : 'unmatched',
            'source_id' => $sources[0] ?? null,
            'journal_id' => $journals[0] ?? null,
        ];
    }

    private function validIssn(?string $issn): bool
    {
        if (! $issn || ! preg_match('/^\d{7}[\dX]$/', $issn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $issn[$i] * (8 - $i);
        }
        $sum += $issn[7] === 'X' ? 10 : (int) $issn[7];

        return $sum % 11 === 0;
    }
}
