@extends('layout') @section('title','Hasil Akreditasi SINTA Periode 3 Tahun 2025') @section('content')
<p class="eyebrow">SK DIREKTUR JENDERAL SAINS DAN TEKNOLOGI · 24 JULI 2026</p>
<h1>Hasil akreditasi SINTA</h1>
@include('partials.database-tabs')
<p>Dokumen Periode 3 Tahun 2025 memuat {{ $totalAccreditations }} keputusan untuk SINTA 1 sampai 4. {{ $matchedAccreditations }} baris cocok dengan sumber atau jurnal terkurasi berdasarkan ISSN yang valid. Hasil ini berasal dari SK Nomor 355/DST/D.D1/HM.01.01/2026; masa berlaku mengikuti volume dan nomor yang tercantum, bukan tahun kalender.</p>
<div class="notice">Nama, e-ISSN, penerbit, peringkat, dan periode ditranskripsikan dari dokumen keputusan. Data tidak mengubah label historis pada snapshot SINTA 20 Maret 2025. Baris dengan ISSN kosong atau checksum tidak valid tidak ditautkan otomatis.</div>
<section aria-labelledby="rank-category-title"><h2 id="rank-category-title">Kategori jurnal SINTA 1–4</h2><p>Pilih peringkat untuk melihat keputusan pada kategori tersebut.</p><div class="rank-categories">@foreach(range(1,4) as $value)<a class="rank-card rank-card--sinta-{{ $value }}" href="{{ route('accreditations.index',['rank'=>$value]) }}" aria-label="Filter peringkat SINTA {{ $value }}, {{ (int) ($rankCounts[$value] ?? 0) }} keputusan"><span class="tag">SINTA {{ $value }}</span><strong>{{ number_format((int) ($rankCounts[$value] ?? 0)) }}</strong><span>rekaman keputusan</span></a>@endforeach</div></section>
<form class="search" method="get"><label>Nama jurnal, penerbit, atau e-ISSN<input name="q" maxlength="120" value="{{ $q }}" placeholder="Cari nama jurnal atau e-ISSN"></label><label>Peringkat<select name="rank"><option value="">Semua peringkat SINTA 1–4</option>@foreach(range(1,4) as $value)<option value="{{ $value }}" @selected((string) $rank === (string) $value)>SINTA {{ $value }}</option>@endforeach</select></label><button>Cari keputusan</button></form>
<p>{{ $accreditations->total() }} keputusan ditemukan</p>
<div class="cards accreditation-cards">
@forelse($accreditations as $item)
    <article class="card accreditation-card" data-rank="{{ $item->rank }}">
        <div class="accreditation-card-head">
            <span class="accreditation-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 3 4 6 4-6M8 3H5l4 8m7-8h3l-4 8"/><circle cx="12" cy="16" r="5"/><path d="m10.5 16 1 1 2-2"/></svg></span>
            <div><span class="accreditation-rank">SINTA {{ $item->rank }}</span><span class="accreditation-decision">{{ $item->decision_type }}</span></div>
        </div>
        <h2>{{ $item->journal_name }}</h2>
        <p class="accreditation-publisher">{{ $item->publisher ?: 'Penerbit tidak tercantum' }}</p>
        <p class="accreditation-issn">e-ISSN <strong>{{ $item->e_issn_raw ?: 'tidak tersedia' }}</strong>@if($item->e_issn_raw && ! $item->e_issn_checksum_valid) <span>Checksum tidak valid</span>@endif</p>
        <div class="accreditation-evidence accreditation-scope"><strong>Scope jurnal</strong>
            @if($item->journal?->status === 'published' && filled($item->journal->scope_summary))
                <span>{{ $item->journal->scope_summary }}</span>
                <small><a href="{{ $item->journal->scope_source_url }}" target="_blank" rel="noopener noreferrer">Lihat sumber scope</a> · Diperiksa {{ $item->journal->checked_at?->format('d/m/Y') ?: 'tanggal belum tersedia' }}</small>
            @else
                <span>Scope belum tersedia dalam katalog terkurasi. Periksa situs resmi jurnal sebelum menilai kecocokan naskah.</span>
            @endif
        </div>
        @if($item->journal?->status === 'published')
            @foreach(['fee' => 'Biaya publikasi', 'time' => 'Waktu proses'] as $kind => $label)
                @php($details = $item->journal->observations->where('kind', $kind)->filter(fn ($observation) => filled($observation->value) || ($kind === 'fee' && $observation->amount_minor !== null)))
                @if($details->isNotEmpty())
                    <div class="accreditation-publication"><strong>{{ $label }}</strong>
                        @foreach($details as $observation)
                            <p>{{ $observation->label }} · @if($kind === 'fee' && $observation->amount_minor !== null && $observation->currency){{ $observation->currency }} {{ number_format($observation->amount_minor, 0, ',', '.') }}@else{{ $observation->value }}@endif</p>
                            <small>{{ $observation->verification === 'verified' ? 'Terverifikasi dari sumber utama' : 'Klaim penerbit, belum diverifikasi independen' }} · <a href="{{ $observation->source_url }}" target="_blank" rel="noopener noreferrer">Sumber</a> · Diperiksa {{ $observation->checked_at?->format('d/m/Y') ?: 'tanggal belum tersedia' }}@if($observation->checked_at && $observation->checked_at->lt(today()->subDays(180))) · Perlu diperiksa ulang @endif</small>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif
        <p class="accreditation-acceptance"><strong>Peluang diterima:</strong> Belum dapat dihitung dari peringkat dan scope jurnal.</p>
        @if($item->source || $item->journal)<div class="accreditation-links">@if($item->source)<a href="{{ route('sources.show',$item->source) }}">Lihat rekaman sumber SINTA</a>@endif @if($item->journal)<a href="{{ route('catalog.show',$item->journal) }}">Lihat jurnal terkurasi</a>@endif</div>@endif
    </article>
@empty<p>Tidak ada hasil. Coba nama, penerbit, e-ISSN, atau peringkat lain.</p>@endforelse
</div>
<div class="pagination">{{ $accreditations->links('pagination') }}</div>
@endsection
