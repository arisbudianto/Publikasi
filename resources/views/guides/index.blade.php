@extends('layout')
@section('title', 'Panduan publikasi')
@section('content')
<section class="page-heading"><p class="eyebrow">LITERASI PUBLIKASI</p><h1>Panduan publikasi</h1>
<p>Artikel ditulis dan ditinjau pengelola. Periksa tanggal tinjau serta sumber pada halaman terkait sebelum mengambil keputusan.</p>
<h2>Tanya tentang jurnal dan publikasi</h2>
<p>Asisten mencari jawaban dari katalog terkurasi dan panduan yang sudah diterbitkan.</p>
@include('assistant._form', ['question' => ''])
@forelse($guides as $guide)
<article class="panel"><h2><a href="{{ route('guides.show', $guide->slug) }}">{{ $guide->title }}</a></h2><p>Terbit {{ $guide->published_at->timezone('Asia/Jakarta')->format('d/m/Y') }} · Ditinjau {{ $guide->reviewed_at->format('d/m/Y') }}</p></article>
@empty<p>Belum ada panduan yang diterbitkan.</p>@endforelse
{{ $guides->links('pagination') }}
</section>
@endsection
