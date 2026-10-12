@php
    $today = today();
    $observations = $journal->observations->filter(fn ($observation) => ($observation->effective_from === null || $observation->effective_from->lte($today)) && ($observation->effective_to === null || $observation->effective_to->gte($today)));
    $indexRows = $observations->where('kind', 'index');
    $feeRows = $observations->where('kind', 'fee');
    $currentFeeRows = $feeRows->where('fee_route', 'apc_oa')->where('verification', 'verified')->whereNotNull('amount_minor');
    $isFreeSection = ($section ?? '') === 'free';
    $shownFees = $isFreeSection ? $currentFeeRows->where('amount_minor', 0) : $feeRows;
@endphp
<article class="card directory-card">
    <p class="tag">{{ $journal->subject }}</p>
    <h3><a href="{{ route('catalog.show', $journal) }}">{{ $journal->name }}</a></h3>
    <p>{{ $journal->publisher }}</p>
    <p class="card-identifiers">ISSN {{ $journal->issn }}@foreach($journal->identifiers as $identifier) · {{ strtoupper(str_replace('_', ' ', $identifier->type)) }} {{ $identifier->value }}@endforeach</p>
    <p class="scope">{{ \Illuminate\Support\Str::limit($journal->scope_summary, 180) }}</p>
    @if($indexRows->isNotEmpty())<div class="directory-data"><strong>Peringkat / indeksasi</strong>@foreach($indexRows as $observation)<p><span class="chip">{{ $observation->metric_scheme ?: $observation->label }}@if($observation->rank_value !== null) · {{ $observation->rank_value }}@endif</span> @if($observation->period){{ $observation->period }}@elseif($observation->effective_from || $observation->effective_to){{ $observation->effective_from?->format('Y') }}–{{ $observation->effective_to?->format('Y') }}@endif <span class="verification {{ $observation->verification }}">{{ $observation->verification === 'verified' ? 'Terverifikasi' : 'Klaim penerbit' }}</span></p>@endforeach</div>@endif
    <div class="directory-data"><strong>Biaya</strong>
        @forelse($shownFees as $observation)
            <p>@if($observation->amount_minor !== null && $observation->currency){{ $observation->currency }} {{ number_format($observation->amount_minor, 0, ',', '.') }}@else{{ $observation->value ?: 'Besaran belum diketahui' }}@endif · {{ str_replace('_', ' ', $observation->fee_route ?: 'jalur belum terstruktur') }} <span class="verification {{ $observation->verification }}">{{ $observation->verification === 'verified' ? 'Terverifikasi' : 'Klaim penerbit' }}</span><br><small>Diperiksa {{ $observation->checked_at?->format('d/m/Y') ?: 'tanggal tidak tersedia' }} · <a href="{{ $observation->source_url }}" target="_blank" rel="noopener noreferrer">Sumber ↗</a>@if($observation->checked_at && $observation->checked_at->lt(today()->subDays(180))) <span class="stale-badge">Perlu diperiksa</span>@endif</small></p>
        @empty @if(($section ?? '') === 'unknown')<p>Belum ada besaran APC terverifikasi yang masih berlaku.</p>@else<p>Belum diketahui</p>@endif @endforelse
    </div>
    <div class="card-foot"><span>ISSN {{ $journal->issn }} · diperiksa {{ $journal->checked_at->format('d/m/Y') }} @if($journal->needsReview())<span class="stale-badge">Perlu diperiksa</span>@endif</span><a href="{{ route('catalog.show', $journal) }}">Lihat detail →</a></div>
    <div class="compare-choice">@if(in_array($journal->id, $selectedIds ?? $ids ?? []))<span>Sudah dipilih</span>@elseif(count($selectedIds ?? $ids ?? []) < 3)<a href="{{ route(($origin ?? 'catalog.index'), array_merge(request()->except('ids','page','free_page','unknown_page'), ['ids'=>array_merge($selectedIds ?? $ids ?? [], [$journal->id]), 'origin'=>($origin === 'catalog.no-apc' ? 'tanpa-apc' : 'katalog')])) }}">Pilih untuk dibandingkan</a>@else<span>Maksimal tiga jurnal. Hapus satu pilihan untuk mengganti.</span>@endif</div>
    @auth<form method="post" action="{{ route('account.save', $journal) }}" class="shortlist-action">@csrf<button class="secondary">Simpan shortlist</button></form>@endauth
</article>
