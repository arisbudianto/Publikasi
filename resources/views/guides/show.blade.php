@extends('layout')
@section('title', $guide->title)
@section('content')
<article class="page-heading"><p><a href="{{ route('guides.index') }}">Kembali ke panduan</a></p><h1>{{ $guide->title }}</h1>
<p>Terbit {{ $guide->published_at->timezone('Asia/Jakarta')->format('d/m/Y') }} · Ditinjau {{ $guide->reviewed_at->format('d/m/Y') }}</p>
<div class="panel body-text" style="white-space:pre-line">{{ $guide->body }}</div>
<p>Informasi jurnal tertentu tetap harus diperiksa pada <a href="{{ route('catalog.index') }}">katalog</a> dan sumber yang tercantum di sana.</p>
</article>
@endsection
