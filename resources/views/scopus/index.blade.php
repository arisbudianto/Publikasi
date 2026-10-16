@extends('layout')
@section('title','Jelajahi jurnal terindeks Scopus')
@section('content')
<div class="indexed-page">
    <p class="eyebrow">KATALOG TERINDEKS · SNAPSHOT {{ $snapshot ?: 'BELUM TERSEDIA' }}</p>
    <h1>Jelajahi jurnal terindeks</h1>
    <p class="muted">Cari jurnal berdasarkan nama, penerbit, atau ISSN. Data Scopus berasal dari Source Title List September 2026. Status snapshot perlu diperiksa kembali sebelum submit.</p>
    @include('partials.database-tabs')
    <form class="indexed-filters" method="get" action="{{ route('scopus.index') }}">
        <label for="indexed-q">Cari jurnal atau ISSN<input id="indexed-q" name="q" maxlength="120" value="{{ $q }}" placeholder="Nama jurnal, penerbit, atau ISSN"></label>
        <label for="indexed-status">Status snapshot<select id="indexed-status" name="status"><option value="">Semua status</option><option value="Active" @selected($status === 'Active')>Active</option><option value="Inactive" @selected($status === 'Inactive')>Inactive</option></select></label>
        <label for="indexed-type">Jenis sumber<select id="indexed-type" name="type"><option value="Journal" @selected($type === 'Journal')>Jurnal</option><option value="Book Series" @selected($type === 'Book Series')>Book Series</option><option value="Trade Journal" @selected($type === 'Trade Journal')>Trade Journal</option></select></label>
        <label class="indexed-check"><input type="checkbox" name="discontinued" value="1" @checked($discontinued)> Hanya discontinued</label>
        <button type="submit">Terapkan filter</button>
    </form>
    <div class="indexed-summary"><strong>{{ number_format($sources->total(), 0, ',', '.') }}</strong> sumber sesuai filter <span>·</span> Status indeks dan kecocokan scope harus diperiksa pada sumber resmi.</div>
    <div class="table-wrap indexed-table"><table>
        <thead><tr><th scope="col">Jurnal</th><th scope="col">ISSN</th><th scope="col">Scopus</th><th scope="col">Cakupan jurnal</th><th scope="col">Biaya publikasi</th><th scope="col">Estimasi review</th></tr></thead>
        <tbody>
        @forelse($sources as $source)
            <tr>
                <td><strong>{{ $source->source_title }}</strong><small>{{ $source->publisher ?: 'Penerbit tidak tercatat' }} · {{ $source->source_type }}</small>@if($source->discontinued)<span class="indexed-warning">Ditandai discontinued</span>@endif</td>
                <td>{{ $source->issn ?: $source->eissn ?: 'Tidak tercatat' }}@if($source->issn && $source->eissn && $source->issn !== $source->eissn)<small>eISSN {{ $source->eissn }}</small>@endif</td>
                <td><span class="indexed-badge {{ $source->status === 'Active' && ! $source->discontinued ? 'indexed-active' : 'indexed-inactive' }}">{{ $source->status }}@if($source->discontinued) · discontinued @endif</span></td>
                <td>@if($source->scope_excerpt){{ \Illuminate\Support\Str::limit($source->scope_excerpt, 220) }}<small><a href="{{ $source->scope_source_url }}" target="_blank" rel="noopener noreferrer">Sumber jurnal</a> · {{ $source->scope_checked_at?->format('d/m/Y') }}</small>@else<span class="indexed-unavailable">Belum tersedia</span>@endif<small>Cakupan indeks pada snapshot: {{ $source->coverage ?: 'tidak tercatat' }}</small></td>
                <td class="indexed-unavailable">Belum tersedia</td>
                <td class="indexed-unavailable">Belum tersedia</td>
            </tr>
        @empty<tr><td colspan="6">Belum ada hasil untuk filter ini.</td></tr>@endforelse
        </tbody>
    </table></div>
    <p class="indexed-footnote">Kutipan cakupan berasal dari situs jurnal dan belum membuktikan status Scopus saat ini. Status “Inactive” tidak selalu berarti “Discontinued by Scopus”. Data quartile, peluang diterima, biaya, dan waktu review belum tersedia. <a href="https://www.elsevier.com/products/scopus/content" target="_blank" rel="noopener noreferrer">Periksa sumber Elsevier ↗</a></p>
    {{ $sources->links('pagination') }}
</div>
@endsection
