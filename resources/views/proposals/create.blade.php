@extends('layout')
@section('title', 'Usulkan data jurnal')
@section('content')
<p><a href="{{ route('proposals.mine') }}">Kembali ke usulan saya</a></p><h1>Usulkan data jurnal</h1><p>Usulan masuk antrean admin. Data katalog tidak berubah sampai sumber diperiksa dan admin menerbitkan perubahan.</p>
<form class="panel" method="post" action="{{ route('proposals.store') }}">@csrf
@if($journal)<p>Jurnal: <strong>{{ $journal->name }}</strong></p><input type="hidden" name="journal_id" value="{{ $journal->id }}">@else
<label>Jurnal yang sudah ada (ID dari alamat halaman katalog), kosongkan untuk usulan jurnal baru<input name="journal_id" type="number" min="1" value="{{ old('journal_id') }}"></label>@endif
<label>Jenis usulan<select name="field">
@foreach(['new_journal'=>'Jurnal baru', 'index'=>'Indeksasi / peringkat', 'fee'=>'Biaya publikasi', 'time'=>'Waktu proses', 'language'=>'Bahasa terbit', 'frequency'=>'Frekuensi terbit', 'scope'=>'Scope jurnal', 'identifier'=>'ISSN'] as $value => $label)
<option value="{{ $value }}" @selected(old('field', $journal ? 'fee' : 'new_journal') === $value)>{{ $label }}</option>@endforeach
</select></label>
@unless($journal)<label>Nama jurnal baru<input name="journal_name" maxlength="255" value="{{ old('journal_name') }}"></label>@endunless
<label>Informasi yang diusulkan<input name="proposed_value" required minlength="3" maxlength="1000" value="{{ old('proposed_value') }}"></label>
<label>URL sumber HTTPS<input name="source_url" type="url" required maxlength="1000" pattern="https://.*" value="{{ old('source_url') }}"></label>
<label>Catatan lokasi bukti atau konteks<textarea name="note" maxlength="2000" rows="4">{{ old('note') }}</textarea></label>
<button>Kirim usulan</button></form>
@endsection
