@extends('layout') @section('title','Database sumber jurnal seluruh bidang') @section('content')
<p class="eyebrow">DATABASE JURNAL · SUMBER SINTA</p><h1>Database jurnal seluruh bidang</h1><p>Database memuat {{ $totalSources }} rekaman dari semua bidang: {{ $educationSources }} berlabel Pendidikan dan {{ $otherSources }} bidang lainnya. {{ $sources->total() }} rekaman sesuai pencarian. Data diambil 20 Maret 2025 dan belum diverifikasi ulang. Label SINTA pada snapshot ini bukan status terkini. {{ $sources->total() === $totalSources ? 'Termasuk rekaman yang nama jurnalnya tidak tersedia.' : '' }} Scope lengkap belum tersedia pada dataset.</p><p>Keputusan akreditasi resmi Periode 3 Tahun 2025 memuat {{ $accreditationCount }} jurnal; {{ $matchedAccreditationCount }} cocok dengan database melalui ISSN. <a href="{{ route('accreditations.index') }}">Cari hasil akreditasi</a> · <a href="{{ route('catalog.index') }}">Lima jurnal dalam katalog terkurasi</a></p>
<form class="search" method="get"><label>Nama jurnal, penerbit, ISSN, ID SINTA, atau judul artikel<input name="q" maxlength="120" value="{{ $q }}" placeholder="Nama jurnal, ISSN, ID SINTA, atau judul artikel"></label><button>Cari seluruh database</button></form>
<div class="cards source-cards">
@forelse($sources as $s)
    @php
        $subject = mb_strtolower((string) $s->subject);
        $domain = $s->education ? 'education' : (preg_match('/health|kesehatan|medical|kedokteran/u', $subject) ? 'health' : (preg_match('/econom|ekonomi|bisnis|akuntansi/u', $subject) ? 'economy' : (preg_match('/engineering|teknik|teknologi/u', $subject) ? 'engineering' : 'other')));
    @endphp
    <article class="card source-card" data-domain="{{ $domain }}">
        <div class="source-card-head">
            <span class="source-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6c-2.5-1.8-5.5-2-9-1v13c3.5-1 6.5-.8 9 1 2.5-1.8 5.5-2 9-1V5c-3.5-1-6.5-.8-9 1Z"/><path d="M12 6v13"/></svg></span>
            <p class="source-card-category">{{ $s->education ? 'Pendidikan' : 'Bidang lainnya' }} <span aria-hidden="true">·</span> {{ $s->subject ?: 'Subjek tidak tersedia' }}</p>
        </div>
        <h2><a href="{{ route('sources.show',$s) }}">{{ $s->name ?: 'Nama jurnal tidak tersedia (SINTA #'.$s->source_id.')' }}</a></h2>
        <p class="source-card-publisher">{{ $s->publisher ?: 'Penerbit tidak tersedia' }}</p>
        <div class="source-card-identifiers"><span>ISSN {{ $s->p_issn ?: $s->e_issn ?: 'belum valid' }}</span><span>ID SINTA {{ $s->source_id }}</span></div>
        <div class="source-card-evidence">
            <div class="source-snapshot"><span class="source-evidence-label">Snapshot 20/03/2025</span><strong>{{ $s->claimed_rank ?: 'Label indeks tidak tersedia' }}</strong><small>Label historis, bukan status terkini</small></div>
            @foreach($s->accreditations as $accreditation)
                <div class="source-decision"><span class="source-evidence-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg> Keputusan SK 24/07/2026</span><strong>SINTA {{ $accreditation->rank }}</strong><small>Periode 3 Tahun 2025</small></div>
            @endforeach
        </div>
        <a class="source-card-link" href="{{ route('sources.show',$s) }}">Lihat sumber dan judul artikel <span aria-hidden="true">→</span></a>
    </article>
@empty
    <p class="empty">Tidak ada hasil. Coba sebagian nama, ISSN, atau ID SINTA.</p>
@endforelse
</div>{{ $sources->links('pagination') }}
@endsection
