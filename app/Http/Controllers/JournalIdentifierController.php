<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Rules\ValidIssn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JournalIdentifierController extends Controller
{
    public function store(Request $request, Journal $journal)
    {
        $request->merge(['value' => strtoupper(trim((string) $request->input('value')))]);
        $data = $request->validate([
            'type' => ['required', Rule::in(['print_issn', 'electronic_issn', 'issn_l'])],
            'value' => ['required', 'string', new ValidIssn, Rule::unique('journal_identifiers', 'value'), Rule::unique('journals', 'issn')->ignore($journal->id), Rule::notIn([$journal->issn])],
            'source_url' => 'required|url:https|max:1000',
            'checked_at' => 'required|date_format:Y-m-d|before_or_equal:today',
        ]);
        $data['value'] = strtoupper($data['value']);
        abort_if($journal->issn === $data['value'], 422, 'ISSN ini sudah tercatat sebagai ISSN utama jurnal.');

        DB::transaction(function () use ($journal, $data, $request) {
            $identifier = $journal->allIdentifiers()->create($data);
            DB::table('catalog_audits')->insert([
                'user_id' => $request->user()->id,
                'journal_id' => $journal->id,
                'action' => 'identifier_added',
                'snapshot' => json_encode(['identifier' => $identifier->toArray()], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'ISSN tambahan ditambahkan ke jurnal.');
    }

    public function retire(Request $request, Journal $journal, int $identifier)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        DB::transaction(function () use ($journal, $identifier, $request, $data) {
            $item = $journal->allIdentifiers()->lockForUpdate()->findOrFail($identifier);
            abort_if($item->retired_at, 404);
            $before = $item->toArray();
            $item->update(['retired_at' => now()]);
            DB::table('catalog_audits')->insert([
                'user_id' => $request->user()->id,
                'journal_id' => $journal->id,
                'action' => 'identifier_retired',
                'snapshot' => json_encode(['before' => $before, 'reason' => $data['reason']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'ISSN tambahan dinonaktifkan; riwayat audit tetap tersimpan.');
    }
}
