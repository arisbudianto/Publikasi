<?php

namespace App\Services;

use App\Models\ScopusSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScopusSourceDataset
{
    public const HEADERS = ['source_record_id', 'source_title', 'issn', 'eissn', 'status', 'coverage', 'discontinued', 'source_type', 'publisher', 'asjc_codes'];

    public function preview(string $path, string $month): array
    {
        if (! preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $month) || $month > now()->format('Y-m')) {
            throw ValidationException::withMessages(['snapshot_month' => 'Bulan snapshot harus YYYY-MM dan tidak di masa depan.']);
        }
        if (($latest = ScopusSource::max('snapshot_month')) && $month < $latest) {
            throw ValidationException::withMessages(['snapshot_month' => 'Snapshot lebih lama daripada data yang sudah diimpor.']);
        }
        if (! is_file($path) || filesize($path) > 30 * 1024 * 1024) {
            throw new RuntimeException('CSV tidak ditemukan atau melebihi 30 MB.');
        }
        $file = fopen($path, 'rb');
        $header = fgetcsv($file, 0, ',', '"', '');
        if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        if ($header !== self::HEADERS) {
            fclose($file);
            throw ValidationException::withMessages(['csv' => 'Header CSV tidak sesuai format sumber Scopus.']);
        }
        $rows = []; $errors = []; $line = 1;
        while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null]) continue;
            if ($line > 60000 || count($values) !== 10) {
                $errors[] = ['row' => $line, 'reason' => 'Jumlah kolom atau baris tidak sesuai.'];
                continue;
            }
            $row = array_combine(self::HEADERS, array_map('trim', $values));
            if (! ctype_digit($row['source_record_id']) || ! (int) $row['source_record_id'] ||
                $row['source_title'] === '' || mb_strlen($row['source_title']) > 500 ||
                ! in_array($row['status'], ['Active', 'Inactive'], true) ||
                ! in_array($row['source_type'], ['Journal', 'Book Series', 'Trade Journal'], true) ||
                ! in_array($row['discontinued'], ['0', '1'], true) ||
                mb_strlen($row['coverage']) > 500 || mb_strlen($row['publisher']) > 500 || mb_strlen($row['asjc_codes']) > 500 ||
                ! $this->identifierValid($row['issn']) || ! $this->identifierValid($row['eissn']) ||
                isset($rows[$row['source_record_id']])) {
                $errors[] = ['row' => $line, 'reason' => 'Nilai, panjang, ISSN, atau ID sumber duplikat/tidak valid.'];
                continue;
            }
            foreach (['issn', 'eissn', 'coverage', 'publisher', 'asjc_codes'] as $key) {
                $row[$key] = $row[$key] === '' ? null : $row[$key];
            }
            $rows[$row['source_record_id']] = $row;
        }
        fclose($file);
        return ['rows' => array_values($rows), 'errors' => array_slice($errors, 0, 100), 'error_count' => count($errors), 'sha256' => hash_file('sha256', $path), 'snapshot_month' => $month];
    }

    private function identifierValid(string $value): bool
    {
        return $value === '' || (bool) preg_match('/^\d{7}[\dX]$/i', $value);
    }

    public function import(array $rows, string $month): array
    {
        if (count($rows) < 1000) {
            throw ValidationException::withMessages(['csv' => 'Snapshot terlalu kecil untuk mengganti katalog sumber.']);
        }
        DB::transaction(function () use ($rows, $month) {
            $evidence = ScopusSource::where('is_current', true)->whereNotNull('scope_excerpt')
                ->get(['source_record_id', 'issn', 'eissn', 'scope_excerpt', 'scope_source_url', 'scope_checked_at'])
                ->keyBy('source_record_id');
            ScopusSource::where('is_current', true)->update(['is_current' => false]);
            $now = now();
            foreach (array_chunk($rows, 500) as $chunk) {
                $records = array_map(function ($row) use ($month, $now, $evidence) {
                    $record = array_merge($row, ['snapshot_month' => $month, 'is_current' => true,
                        'created_at' => $now, 'updated_at' => $now]);
                    $old = $evidence->get($row['source_record_id']);
                    $sameIdentity = $old && $old->issn === $row['issn'] && $old->eissn === $row['eissn'];
                    $record += ['scope_excerpt' => $sameIdentity ? $old->scope_excerpt : null,
                        'scope_source_url' => $sameIdentity ? $old->scope_source_url : null,
                        'scope_checked_at' => $sameIdentity ? $old->scope_checked_at : null];
                    return $record;
                }, $chunk);
                ScopusSource::upsert($records, ['source_record_id', 'snapshot_month'], ['source_title', 'issn', 'eissn', 'status', 'coverage', 'discontinued', 'source_type', 'publisher', 'asjc_codes', 'scope_excerpt', 'scope_source_url', 'scope_checked_at', 'is_current', 'updated_at']);
            }
        });
        return ['imported' => count($rows), 'current' => ScopusSource::where('is_current', true)->count(), 'snapshot_month' => $month];
    }
}
