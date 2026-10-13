@extends('layout')
@section('title', 'Kelola panduan')
@section('content')
<p><a href="{{ route('admin.curation') }}">Kembali ke kurasi</a></p><h1>Kelola panduan</h1><p><a class="button" href="{{ route('admin.guides.create') }}">Tulis panduan</a></p>
@forelse($guides as $guide)
<article class="panel"><h2><a href="{{ route('admin.guides.edit', $guide) }}">{{ $guide->title }}</a></h2><p>{{ $guide->published_at ? 'Terbit' : 'Draf' }} · Ditinjau {{ $guide->reviewed_at?->format('d/m/Y') ?? 'Belum' }}</p></article>
@empty<p>Belum ada artikel panduan.</p>@endforelse
{{ $guides->links('pagination') }}
@endsection
