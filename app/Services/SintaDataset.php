<?php

namespace App\Services;

use App\Models\JournalSource;
use App\Rules\ValidIssn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SintaDataset
{
    public function preview(): array
    {
        $root = database_path('datasets/sinta-20250320');
        $manifest = json_decode(file_get_contents($root.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];
        $seen = [];
        foreach ($manifest['parts'] as $part) {
            if (! preg_match('/^part-\d{3}\.json$/', $part['file'])) {
                throw new RuntimeException('Nama part tidak valid.');
            }
            $raw = file_get_contents($root.'/'.$part['file']);
            if (! hash_equals($part['sha256'], hash('sha256', $raw))) {
                throw new RuntimeException('Checksum dataset tidak sesuai.');
            }
            $batch = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (count($batch) !== $part['count']) {
                throw new RuntimeException('Jumlah part tidak sesuai.');
            }
            foreach ($batch as $row) {
                Validator::make($row, ['source_id' => 'required|integer|min:1', 'name' => 'nullable|string|max:255', 'publisher' => 'nullable|string|max:500', 'subject' => 'nullable|string|max:255', 'claimed_rank' => 'nullable|string|max:30', 'p_issn' => ['nullable', new ValidIssn], 'e_issn' => ['nullable', new ValidIssn], 'official_url' => 'nullable|string|max:1000', 'source_url' => 'required|string|max:1000', 'captured_at' => 'required|date_format:Y-m-d|before_or_equal:today', 'education' => 'required|boolean', 'raw_identifiers' => 'present|array', 'article_titles' => 'present|array|max:10', 'article_titles.*' => 'string|max:2000'])->validate();
                if (isset($seen[$row['source_id']]) || $row['source_url'] !== 'https://sinta.kemdikbud.go.id/journals/profile/'.$row['source_id']) {
                    throw new RuntimeException('Identitas sumber tidak valid atau duplikat.');
                }
                $seen[$row['source_id']] = true;
                $rows[] = $row;
            }
        }
        if (count($rows) !== $manifest['total'] || count(array_filter($rows, fn ($r) => $r['education'])) !== $manifest['education']) {
            throw new RuntimeException('Total dataset tidak sesuai.');
        }
        $existing = JournalSource::whereIn('source_id', array_keys($seen))->count();

        return ['manifest' => $manifest, 'rows' => $rows, 'new' => count($rows) - $existing, 'existing' => $existing];
    }

    public function import(): array
    {
        $p = $this->preview();

        return DB::transaction(function () use ($p) {
            $created = 0;
            foreach ($p['rows'] as $r) {
                $item = JournalSource::firstOrCreate(['source_id' => $r['source_id']], $r + ['dataset_sha256' => $p['manifest']['raw_sha256']]);
                if ($item->wasRecentlyCreated) {
                    $created++;
                } elseif ($item->dataset_sha256 !== $p['manifest']['raw_sha256']) {
                    throw new RuntimeException('Snapshot sumber sudah berbeda; tidak ditimpa.');
                }
            }

            return ['created' => $created, 'skipped' => count($p['rows']) - $created];
        });
    }
}
