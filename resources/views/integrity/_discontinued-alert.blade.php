@if(($discontinuedByJournal[$journal->id] ?? collect())->isNotEmpty())
    @foreach($discontinuedByJournal[$journal->id] as $discontinued)
        <aside class="notice discontinued-alert"><strong>Scopus mencatat sumber ini sebagai discontinued.</strong>
            ISSN {{ $discontinued->issn }} · @if($discontinued->discontinued_at)tanggal pada sumber {{ $discontinued->discontinued_at->format('d/m/Y') }} · @endifcakupan tahun {{ $discontinued->coverage_start_year ?: 'awal tidak tercatat' }}–{{ $discontinued->coverage_end_year ?: 'akhir tidak tercatat' }}.
            @if($discontinued->reason) Alasan pada data sumber: {{ $discontinued->reason }}.@endif
            Daftar diperiksa {{ $discontinued->source_as_of->format('d/m/Y') }}.
            Ini tidak menyatakan artikel sebelum akhir cakupan tidak terindeks. Periksa <a href="{{ $discontinued->source_url }}" target="_blank" rel="noopener noreferrer">daftar resmi Scopus ↗</a> dan halaman sumber untuk periode artikel.
        </aside>
    @endforeach
@endif
