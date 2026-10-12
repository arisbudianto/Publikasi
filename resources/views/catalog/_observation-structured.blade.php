@if($o->metric_scheme || $o->rank_value !== null || $o->amount_minor !== null || $o->fee_route || $o->language || $o->frequency_per_year !== null || $o->effective_from || $o->effective_to)
<p class="muted">Data terstruktur:
    @if($o->metric_scheme) Skema {{ $o->metric_scheme }}@endif
    @if($o->rank_value !== null) · Peringkat {{ $o->rank_value }}@endif
    @if($o->amount_minor !== null) · Nilai {{ $o->amount_minor }} unit minor {{ $o->currency }}@endif
    @if($o->fee_route) · Jalur biaya {{ str_replace('_',' ',$o->fee_route) }}@endif
    @if($o->waiver_available !== null) · Waiver {{ $o->waiver_available ? 'tersedia' : 'tidak tersedia' }}@endif
    @if($o->language) · Bahasa {{ $o->language }}@endif
    @if($o->frequency_per_year !== null) · {{ $o->frequency_per_year }} terbitan per tahun@endif
    @if($o->effective_from) · Mulai {{ $o->effective_from->format('d/m/Y') }}@endif
    @if($o->effective_to) · Sampai {{ $o->effective_to->format('d/m/Y') }}@endif
</p>
@endif
