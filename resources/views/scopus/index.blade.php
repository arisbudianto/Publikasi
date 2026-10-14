@extends('layout')
@section('title','Daftar sumber Scopus')
@section('content')
<p class="eyebrow">SUMBER SCOPUS · SNAPSHOT {{ $snapshot ?: 'BELUM TERSEDIA' }}</p>
<h1>Daftar sumber Scopus</h1>
<p>Telusuri daftar sumber yang diunggah dari Scopus Source Title List edisi {{ $snapshot ?: 'belum diimpor' }}. Status Active atau Inactive dan cakupan merupakan isi snapshot, bukan jaminan indeksasi artikel atau status hari ini. “Inactive” tidak selalu berarti “Discontinued by Scopus”. Periksa kembali di <a href="https://www.elsevier.com/products/scopus/content" rel="noopener noreferrer">sumber Elsevier</a> sebelum mengirim naskah.</p>
<form class="search" method="get">
    <label>Nama sumber, penerbit, ISSN, atau Source Record ID<input name="q" maxlength="120" value="{{ $q }}" placeholder="Nama jurnal atau ISSN"></label>
    <label>Jenis sumber<select name="type"><option value="Journal" @selected($type === 'Journal')>Journal</option><option value="Book Series" @selected($type === 'Book Series')>Book Series</option><option value="Trade Journal" @selected($type === 'Trade Journal')>Trade Journal</option></select></label>
    <label>Status snapshot<select name="status"><option value="">Semua</option><option value="Active" @selected($status === 'Active')>Active</option><option value="Inactive" @selected($status === 'Inactive')>Inactive</option></select></label>
    <label><input type="checkbox" name="discontinued" value="1" @checked($discontinued)> Hanya yang ditandai discontinued</label>
    <button>Cari sumber Scopus</button>
</form>
<p>{{ number_format($sources->total(), 0, ',', '.') }} hasil. <a href="{{ route('sources.index') }}">Lihat sumber SINTA</a> · <a href="{{ route('scopus.index', ['discontinued' => 1]) }}">Lihat yang ditandai discontinued</a></p>
<div class="cards">
@forelse($sources as $source)
    <article class="card">
        <p class="tag">{{ $source->source_type }} · {{ $source->status }} @if($source->discontinued) · Ditandai discontinued @endif</p>
        <h2>{{ $source->source_title }}</h2>
        <p>{{ $source->publisher ?: 'Penerbit tidak tercatat' }}</p>
        <p>ISSN {{ $source->issn ?: 'tidak tercatat' }} · EISSN {{ $source->eissn ?: 'tidak tercatat' }} · ID {{ $source->source_record_id }}</p>
        <p>Cakupan dalam snapshot: {{ $source->coverage ?: 'tidak tercatat' }}</p>
        <p>ASJC: {{ $source->asjc_codes ?: 'tidak tercatat' }} · snapshot {{ $source->snapshot_month }}</p>
    </article>
@empty <p>Belum ada hasil untuk filter ini.</p> @endforelse
</div>
{{ $sources->links('pagination') }}
@endsection
