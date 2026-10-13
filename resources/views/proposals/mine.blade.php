@extends('layout')
@section('title', 'Usulan saya')
@section('content')
<h1>Usulan saya</h1><p><a class="button" href="{{ route('proposals.create') }}">Usulkan jurnal baru</a></p>
@forelse($proposals as $proposal)
<article class="panel"><h2>{{ $proposal->journal_name }}</h2><p>{{ $proposal->field }} · Status: {{ ['new'=>'Baru', 'reviewed'=>'Ditinjau', 'applied'=>'Diterapkan', 'rejected'=>'Ditolak'][$proposal->status] ?? $proposal->status }} · Dikirim {{ $proposal->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
<p>Usulan: {{ $proposal->proposed_value }}</p><p><a href="{{ $proposal->source_url }}" target="_blank" rel="noopener noreferrer">Sumber yang diajukan</a></p>
@if($proposal->decision_note)<p>Catatan admin: {{ $proposal->decision_note }}</p>@endif</article>
@empty<p>Belum ada usulan.</p>@endforelse
{{ $proposals->links('pagination') }}
@endsection
