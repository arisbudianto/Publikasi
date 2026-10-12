<?php

namespace App\Services;

use App\Models\Journal;
use App\Rules\ValidIssn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Catalog
{
    public const FIELDS = ['name', 'publisher', 'issn', 'official_url', 'subject', 'scope_summary', 'scope_source_url', 'checked_at', 'status'];

    public function rules(?Journal $journal = null): array
    {
        return ['name' => 'required|string|max:255', 'publisher' => 'required|string|max:255', 'issn' => ['required', new ValidIssn, Rule::unique('journals')->ignore($journal?->id), Rule::unique('journal_identifiers', 'value')],
            'official_url' => 'required|url:https|max:1000', 'subject' => 'required|string|max:255', 'scope_summary' => 'required|string|max:5000', 'scope_source_url' => 'required|url:https|max:1000',
            'checked_at' => 'required|date_format:Y-m-d|before_or_equal:today', 'status' => ['required', Rule::in(['draft', 'published', 'archived'])]];
    }

    public function normalize(array $row): array
    {
        foreach ($row as $k => $v) {
            $row[$k] = is_string($v) ? trim($v) : $v;
        } if (isset($row['issn'])) {
            $row['issn'] = strtoupper($row['issn']);
        }

return $row;
    }

    public function save(array $data, ?Journal $journal = null, ?int $actor = null): Journal
    {
        return DB::transaction(function () use ($data, $journal, $actor) {
            $old = $journal?->toArray();
            if ($journal) {
                $journal->fill($data);
                $journal->version++;
                $journal->save();
            } else {
                $journal = Journal::create($data);
            }
            DB::table('catalog_audits')->insert(['user_id' => $actor, 'journal_id' => $journal->id, 'action' => $old ? 'updated' : 'created', 'snapshot' => json_encode(['before' => $old, 'after' => $journal->toArray()]), 'created_at' => now()]);

            return $journal;
        });
    }

    public function preview(string $path): array
    {
        $h = fopen($path, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        if ($header !== self::FIELDS) {
            fclose($h);

            return ['rows' => [], 'errors' => ['Header CSV harus sama dengan template.']];
        }
        while (($values = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $line++;
            if ($line > 501) {
                $errors[] = 'Maksimal 500 baris per impor.';
                break;
            }
            if (count($values) !== count($header)) {
                $errors[] = "Baris $line: jumlah kolom salah.";

                continue;
            }
            $row = $this->normalize(array_combine($header, $values));
            $v = Validator::make($row, $this->rules());
            if ($v->fails()) {
                foreach ($v->errors()->all() as $e) {
                    $errors[] = "Baris $line: $e";
                }
            }
            if (isset($seen[$row['issn']])) {
                $errors[] = "Baris $line: ISSN duplikat di CSV.";
            }
            $seen[$row['issn']] = true;
            $rows[] = $row;
        }fclose($h);
        if (! $rows) {
            $errors[] = 'CSV tidak memiliki data.';
        }

return compact('rows', 'errors');
    }

    public function import(array $rows, int $actor): void
    {
        DB::transaction(function () use ($rows, $actor) {
            foreach ($rows as $row) {
                $data = Validator::make($row, $this->rules())->validate();
                $this->save($data,null,$actor);
            }
        });
    }
}
