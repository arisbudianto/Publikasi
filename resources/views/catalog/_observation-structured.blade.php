@php
$structuredDetails = array_filter([
    $o->metric_scheme ? 'Skema '.$o->metric_scheme : null,
    $o->rank_value !== null ? 'Peringkat '.$o->rank_value : null,
    $o->amount_minor !== null ? 'Nilai '.$o->amount_minor.' unit minor '.$o->currency : null,
    $o->fee_route ? 'Jalur biaya '.str_replace('_', ' ', $o->fee_route) : null,
    $o->waiver_available !== null ? 'Waiver '.($o->waiver_available ? 'tersedia' : 'tidak tersedia') : null,
    $o->language ? 'Bahasa '.$o->language : null,
    $o->frequency_per_year !== null ? $o->frequency_per_year.' terbitan per tahun' : null,
    $o->effective_from ? 'Mulai '.$o->effective_from->format('d/m/Y') : null,
    $o->effective_to ? 'Sampai '.$o->effective_to->format('d/m/Y') : null,
]);
@endphp
@if($structuredDetails)
<p class="muted">Data terstruktur: {{ implode(' · ', $structuredDetails) }}</p>
@endif
