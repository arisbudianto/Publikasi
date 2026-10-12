<?php

namespace App\Services;

use App\Models\JournalDiscontinuedSource;
use App\Rules\ValidIssn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScopusDiscontinuedDataset
{
    public const SOURCE_URL = 'https://www.elsevier.com/products/scopus/content';

    public const HEADERS = ['source_title', 'issn', 'discontinued_at', 'coverage_start_year', 'coverage_end_year', 'reason'];

    private const MAX_ROWS = 50000;

    public function preview(string $path, string $sourceAsOf): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $sourceAsOf);
        if (! $date || $date->format('Y-m-d') !== $sourceAsOf || $sourceAsOf > today()->toDateString()) {
            throw ValidationException::withMessages(['source_as_of' => 'Tanggal sumber wajib format YYYY-MM-DD dan tidak boleh di masa depan.']);
        }
        $latest = JournalDiscontinuedSource::max('source_as_of');
        if ($latest && $sourceAsOf < $latest) {
            throw ValidationException::withMessages(['source_as_of' => 'Tanggal daftar lebih lama dari impor terakhir; impor lama tidak boleh menggantikan status terkini.']);
        }
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
            throw new RuntimeException('CSV tidak ditemukan atau melebihi batas 10 MB.');
        }
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('CSV tidak dapat dibaca.');
        }
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        if ($header !== self::HEADERS) {
            fclose($handle);
            throw ValidationException::withMessages(['csv' => 'Header harus berurutan: '.implode(',', self::HEADERS)]);
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($values) !== count(self::HEADERS)) {
                $errors[] = ['row' => $line, 'reason' => 'Jumlah kolom tidak sesuai template.'];

                continue;
            }
            if ($line - 1 > self::MAX_ROWS) {
                $errors[] = ['row' => $line, 'reason' => 'Melebihi batas 50.000 baris.'];
                break;
            }
            $row = array_combine(self::HEADERS, array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), null));
            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
            $row['issn'] = strtoupper((string) $row['issn']);
            $row['coverage_start_year'] = $row['coverage_start_year'] ?: null;
            $row['coverage_end_year'] = $row['coverage_end_year'] ?: null;
            $validator = Validator::make($row, [
                'source_title' => 'required|string|max:500', 'issn' => ['required', 'string', new ValidIssn],
                'discontinued_at' => 'nullable|date_format:Y-m-d|before_or_equal:'.$sourceAsOf,
                'coverage_start_year' => 'nullable|integer|min:1600|max:'.now()->year,
                'coverage_end_year' => 'nullable|integer|min:1600|max:'.now()->year,
                'reason' => 'nullable|string|max:2000',
            ]);
            if ($validator->fails()) {
                $errors[] = ['row' => $line, 'reason' => implode(' ', $validator->errors()->all())];

                continue;
            }
            if (isset($seen[$row['issn']])) {
                $errors[] = ['row' => $line, 'reason' => 'ISSN duplikat dalam file (baris '.$seen[$row['issn']].').'];

                continue;
            }
            if ($row['coverage_start_year'] && $row['coverage_end_year'] && $row['coverage_start_year'] > $row['coverage_end_year']) {
                $errors[] = ['row' => $line, 'reason' => 'Tahun awal cakupan lebih besar daripada tahun akhir.'];

                continue;
            }
            $seen[$row['issn']] = $line;
            $rows[] = $row;
        }
        fclose($handle);

        return ['rows' => $rows, 'errors' => array_slice($errors, 0, 100), 'total' => count($rows) + count($errors), 'sha256' => hash_file('sha256', $path), 'source_as_of' => $sourceAsOf];
    }

    public function import(array $rows, string $sourceAsOf): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['csv' => 'Tidak ada baris valid untuk diimpor.']);
        }
        $now = now();
        DB::transaction(function () use ($rows, $sourceAsOf, $now) {
            JournalDiscontinuedSource::query()->update(['is_current' => false, 'updated_at' => $now]);
            foreach (array_chunk($rows, 500) as $batch) {
                $records = array_map(fn ($row) => array_merge($row, [
                    'source_url' => self::SOURCE_URL, 'source_as_of' => $sourceAsOf,
                    'imported_at' => $now, 'is_current' => true, 'created_at' => $now, 'updated_at' => $now,
                ]), $batch);
                JournalDiscontinuedSource::upsert($records, ['issn', 'source_as_of'], [
                    'source_title', 'discontinued_at', 'coverage_start_year', 'coverage_end_year', 'reason', 'source_url', 'imported_at', 'is_current', 'updated_at',
                ]);
            }
        });

        return ['imported' => count($rows), 'current' => JournalDiscontinuedSource::where('is_current', true)->count(), 'source_as_of' => $sourceAsOf];
    }
}
