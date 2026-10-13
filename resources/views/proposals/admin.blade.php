@extends('layout')
@section('title', 'Antrean usulan')
@section('content')
<p><a href="{{ route('admin.curation') }}">Kembali ke kurasi</a></p><h1>Antrean usulan</h1>
<p>@foreach(['new'=>'Baru','reviewed'=>'Ditinjau','applied'=>'Diterapkan','rejected'=>'Ditolak'] as $key=>$label)<a href="{{ route('admin.proposals.index', ['status'=>$key]) }}">{{ $label }}</a> · @endforeach</p>
@forelse($proposals as $proposal)
<article class="panel"><h2><a href="{{ route('admin.proposals.show', $proposal) }}">#{{ $proposal->id }} · {{ $proposal->journal_name }}</a></h2><p>{{ $proposal->field }} · {{ $proposal->proposed_value }} · {{ $proposal->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p></article>
@empty<p>Tidak ada usulan dengan status ini.</p>@endforelse
{{ $proposals->links('pagination') }}
@endsection
