@extends('layout')
@section('title', 'Tinjau usulan')
@section('content')
<p><a href="{{ route('admin.proposals.index') }}">Kembali ke antrean</a></p><h1>Usulan #{{ $proposal->id }}</h1>
<div class="panel"><p>Jurnal: {{ $proposal->journal_name }} · Jenis: {{ $proposal->field }} · Status: {{ $proposal->status }}</p>
<p>Nilai usulan: {{ $proposal->proposed_value }}</p><p>Catatan: {{ $proposal->note ?? 'Tidak ada' }}</p>
<p><a href="{{ $proposal->source_url }}" target="_blank" rel="noopener noreferrer">Buka sumber yang diajukan</a></p>
@if($proposal->journal)<p><a href="{{ route('admin.review', $proposal->journal) }}">Tinjau jurnal dalam katalog</a></p>@endif</div>
@if($proposal->status === 'new')
<form method="post" action="{{ route('admin.proposals.review', $proposal) }}" class="panel">@csrf
<label>Keputusan<select name="decision"><option value="reviewed">{{ $proposal->field === 'assistant_feedback' ? 'Laporan telah ditinjau' : 'Telah ditinjau, menunggu penerapan' }}</option><option value="rejected">Tolak usulan</option></select></label>
<label>Catatan keputusan<textarea name="decision_note" required minlength="20" maxlength="2000"></textarea></label><button>Simpan keputusan</button></form>
@elseif($proposal->status === 'reviewed' && $proposal->field !== 'assistant_feedback')
@if($proposal->journal && in_array($proposal->field, \App\Http\Controllers\ProposalController::OBSERVATION_FIELDS, true))
<p><a class="button" href="{{ route('admin.review', ['journal' => $proposal->journal->id, 'proposal_id' => $proposal->id]) }}">Siapkan observasi M10</a></p>
@else
<form method="post" action="{{ route('admin.proposals.applied', $proposal) }}" class="panel">@csrf
<p>Selesaikan tindakan katalog secara manual lebih dulu. Form ini hanya mencatat bahwa tindakan tersebut sudah diperiksa.</p>
<label>ID jurnal yang terkait<input type="number" name="journal_id" min="1" required value="{{ $proposal->journal_id }}"></label>
<label>Bukti tindakan yang sudah dilakukan<textarea name="decision_note" required minlength="20" maxlength="2000"></textarea></label><button>Catat sebagai diterapkan</button></form>
@endif
@endif
<h2>Riwayat keputusan</h2>@foreach($proposal->events->sortBy('id') as $event)<p>{{ $event->created_at }} UTC · {{ $event->from_status ?? 'awal' }} → {{ $event->to_status }} · {{ $event->note }}</p>@endforeach
@endsection
