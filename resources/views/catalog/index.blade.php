@extends('layout')
@section('title', 'Katalog jurnal terkurasi')
@section('content')
<section class="hero"><div><p class="eyebrow">KATALOG TERBUKA · PENDIDIKAN</p><h1>Kenali jurnal sebelum menentukan tujuan submit.</h1><p>Telusuri bidang, peringkat, indeksasi, bahasa, dan biaya beserta sumber informasinya.</p></div><div class="hero-note"><b>{{ $journals->total() }}</b><span>jurnal dalam hasil pencarian</span><small>Data terstruktur ditampilkan sesuai skema dan status verifikasinya.</small></div></section>
<div class="notice">Katalog ini memuat {{ $journals->total() }} jurnal dengan scope terkurasi. Calon jurnal baru ditinjau dari data sumber dan <a href="{{ route('accreditations.index') }}">keputusan akreditasi SINTA</a>. Data impor belum menjadi rekomendasi scope terkurasi.</div>
@auth @if(auth()->user()->is_admin)<p><a href="{{ route('admin.agent.index') }}">Tinjau usulan jurnal baru dan pembaruan dari agen</a></p>@endif @endauth
<div class="directory-layout">
    <aside>@include('catalog._directory-filters', ['filterAction'=>route('catalog.index'), 'ids'=>$selectedIds, 'noApc'=>false])</aside>
    <section class="directory-results"><section class="selection" aria-label="Jurnal pilihan"><h2>Bandingkan jurnal <small>{{ $selected->count() }}/3 dipilih</small></h2><p>Pilihan tetap tersimpan saat mencari, memfilter, atau berpindah halaman.</p>@foreach($selected as $item)<span class="selected-item">{{ $item->name }} <a aria-label="Hapus {{ $item->name }} dari pilihan" href="{{ route('catalog.index', array_merge(request()->except('page','ids'), ['ids'=>array_values(array_diff($selectedIds,[$item->id]))])) }}">Hapus</a></span>@endforeach
        @if($selected->isNotEmpty())<div><a class="button" href="{{ route('catalog.compare', array_merge(['ids'=>$selectedIds, 'origin'=>'katalog'], request()->except('ids','page'))) }}">Lihat perbandingan</a> <a href="{{ route('catalog.index', request()->except('ids','page')) }}">Kosongkan pilihan</a></div>@else<p class="muted">Belum ada jurnal dipilih.</p>@endif</section>
        <div class="section-head"><h2>{{ $q ? 'Hasil pencarian' : 'Jurnal dalam katalog' }}</h2><span>{{ $journals->total() }} jurnal</span></div>
        <div class="cards">@forelse($journals as $journal)@include('catalog._directory-card',['section'=>'directory','origin'=>'catalog.index','selectedIds'=>$selectedIds])@empty<div class="empty"><h3>Belum ada jurnal yang cocok dengan filter.</h3><p>Filter aktif: {{ collect($filters)->filter(fn($value,$key)=>$key!=='ids' && $value!==null && $value!=='')->map(fn($value,$key)=>$key.'='.$value)->implode(', ') ?: 'tidak ada' }}.</p><a href="{{ route('catalog.index',['ids'=>$selectedIds]) }}">Hapus semua filter</a></div>@endforelse</div><div class="pagination">{{ $journals->links('pagination') }}</div>
    </section>
</div>
@endsection
