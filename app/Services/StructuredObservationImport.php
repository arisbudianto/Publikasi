<?php

namespace App\Services;

use App\Models\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StructuredObservationImport
{
    public function __construct(private ObservationReview $review) {}

    public function preview(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['rows' => [], 'errors' => ['File CSV tidak dapat dibaca.']];
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        if ($header !== StructuredObservation::FIELDS) {
            fclose($handle);

            return ['rows' => [], 'errors' => ['Header CSV harus sama persis dengan template M10.']];
        }

        $rows = [];
        $errors = [];
        $results = [];
        $seen = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($line > 501) {
                $errors[] = 'Maksimal 500 baris data per impor.';
                break;
            }
            if (count($values) !== count($header)) {
                $errors[] = "Baris $line: jumlah kolom tidak sesuai.";
                $results[] = ['line' => $line, 'issn' => '', 'journal' => '', 'kind' => '', 'label' => '', 'status' => 'Ditolak', 'reason' => 'Jumlah kolom tidak sesuai.'];
                continue;
            }

            $row = StructuredObservation::normalize(array_combine($header, $values));
            $issn = strtoupper((string) ($row['journal_issn'] ?? ''));
            $row['journal_issn'] = $issn;
            $validator = Validator::make($row, StructuredObservation::rules(false, true) + [
                'journal_issn' => ['required', 'string', 'max:9', new \App\Rules\ValidIssn],
            ]);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                foreach ($messages as $message) {
                    $errors[] = "Baris $line: $message";
                }
                $results[] = ['line' => $line, 'issn' => $issn, 'journal' => '', 'kind' => $row['kind'] ?? '', 'label' => $row['label'] ?? '', 'status' => 'Ditolak', 'reason' => implode(' ', $messages)];
                continue;
            }

            $matches = Journal::query()->where('issn', $issn)
                ->orWhereHas('identifiers', fn ($query) => $query->where('value', $issn))->get()->unique('id');
            if ($matches->count() !== 1) {
                $message = $matches->isEmpty()
                    ? "Baris $line: ISSN tidak ditemukan pada jurnal kurasi."
                    : "Baris $line: ISSN cocok ke lebih dari satu jurnal dan perlu ditinjau manual.";
                $errors[] = $message;
                $results[] = ['line' => $line, 'issn' => $issn, 'journal' => '', 'kind' => $row['kind'], 'label' => $row['label'], 'status' => $matches->isEmpty() ? 'Ditolak' : 'Konflik', 'reason' => $message];
                continue;
            }
            $journal = $matches->first();
            $identity = $journal->id.'|'.$row['kind'].'|'.mb_strtolower($row['label']);
            if (isset($seen[$identity])) {
                $message = "Baris $line: observasi jurnal, jenis, dan label yang sama muncul lebih dari sekali.";
                $errors[] = $message;
                $results[] = ['line' => $line, 'issn' => $issn, 'journal' => $journal->name, 'kind' => $row['kind'], 'label' => $row['label'], 'status' => 'Konflik', 'reason' => $message];
                continue;
            }
            $seen[$identity] = true;
            $row['journal_id'] = $journal->id;
            unset($row['journal_issn']);
            $rows[] = ['line' => $line, 'journal' => $journal->name, 'issn' => $issn, 'data' => $row];
            $results[] = ['line' => $line, 'issn' => $issn, 'journal' => $journal->name, 'kind' => $row['kind'], 'label' => $row['label'], 'status' => 'Valid', 'reason' => ''];
        }
        fclose($handle);

        if (! $rows) {
            $errors[] = 'CSV tidak memiliki baris data valid.';
        }

        return compact('rows', 'errors', 'results');
    }

    public function import(array $rows, int $actor): int
    {
        DB::transaction(function () use ($rows, $actor) {
            foreach ($rows as $row) {
                $journal = Journal::query()->lockForUpdate()->findOrFail($row['data']['journal_id']);
                $data = $row['data'];
                unset($data['journal_id']);
                $this->review->publish($journal, $data, $actor);
            }
        });

        return count($rows);
    }
}
