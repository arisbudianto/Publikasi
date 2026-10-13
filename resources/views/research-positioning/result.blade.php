@extends('layout')

@section('title', 'Hasil posisi riset')

@section('content')
<section class="page-heading m15-page m15-results" id="m15-results">
    <p class="eyebrow">HASIL PILOT · {{ $result['mode'] === 'semantic' ? 'PEMERINGKATAN SEMANTIK' : 'MODE CADANGAN' }}</p>
    <h1>Posisi riset: {{ $result['title'] ?? '' }}</h1>
    <div class="m15-summary">
        <p>{{ $result['candidate_count'] }} kandidat dari OpenAlex, dianalisis {{ $result['searched_at'] ?? now()->utc()->toIso8601String() }}.</p>
        <p>{{ $result['note'] }}</p>
        @if($saved ?? false)<p role="status">Ringkasan hasil disimpan di <a href="{{ route('account.index') }}">akun Anda</a>. Abstrak tidak disimpan.</p>@endif
        @if($saveFailure ?? false)<p role="status">Hasil tetap tampil, tetapi tidak berhasil disimpan ke akun. Anda bisa menghapus input halaman setelah menutupnya.</p>@endif
    </div>

    @if($result['works'] !== [])
        <div class="m15-actions" aria-label="Ekspor hasil">
            <button type="button" class="button button-secondary" data-m15-export="ris">Ekspor RIS</button>
            <button type="button" class="button button-secondary" data-m15-export="bibtex">Ekspor BibTeX</button>
            <button type="button" class="button button-secondary" data-m15-export="pdf">Cetak / simpan PDF</button>
        </div>

        <section class="m15-section">
            <h2>Publikasi terdekat</h2>
            @foreach($result['works'] as $index => $work)
                <article class="m15-work" data-m15-work data-id="{{ $work['id'] }}" data-title="{{ $work['title'] }}" data-doi="{{ $work['doi'] ?? '' }}" data-year="{{ $work['year'] ?? '' }}" data-venue="{{ $work['venue'] }}" data-url="{{ $work['url'] }}">
                    <p class="m15-work-rank">{{ $index + 1 }}
                        @if($work['score'] !== null)
                            · Kedekatan semantik {{ number_format($work['score'], 3) }}
                        @else
                            · Relevansi pencarian OpenAlex
                        @endif
                    </p>
                    <h3><a href="{{ $work['url'] }}" target="_blank" rel="noopener noreferrer">{{ $work['title'] }}</a></h3>
                    <p>{{ $work['year'] ?? 'Tahun tidak tersedia' }} · {{ $work['venue'] ?: 'Penerbitan/venue tidak tersedia' }}@if($work['doi']) · DOI <a href="https://doi.org/{{ $work['doi'] }}" target="_blank" rel="noopener noreferrer">{{ $work['doi'] }}</a>@endif</p>
                    @if(!empty($work['similarity_reason']))<p><strong>Kesamaan:</strong> {{ $work['similarity_reason'] }}</p>@endif
                    @if(!empty($work['difference_reason']))<p><strong>Perbedaan:</strong> {{ $work['difference_reason'] }}</p>@endif
                    @if(!$work['abstract_available'])<p class="m15-missing">Abstrak tidak tersedia. Kandidat ini tidak digunakan untuk menilai variabel atau metode.</p>@endif
                </article>
            @endforeach
        </section>

        @if($result['analysis'])
            <section class="m15-section">
                <h2>Perbandingan empat dimensi</h2>
                @forelse($result['analysis']['dimensions'] as $dimension)
                    <article class="m15-claim"><h3>{{ ['topic' => 'Topik', 'variables' => 'Variabel', 'methods' => 'Metode', 'context' => 'Konteks'][$dimension['dimension']] }} · {{ ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'][$dimension['level']] }}</h3><p>{{ $dimension['statement'] }}</p>@include('research-positioning._refs', ['ids' => $dimension['refs'], 'works' => $result['works']])</article>
                @empty<p>Belum ada perbandingan dimensi yang memiliki rujukan valid. Tinjau publikasi terdekat secara manual.</p>@endforelse
            </section>

            @foreach([['differentiators' => 'Pembeda yang mungkin'], ['similarity_risks' => 'Risiko kemiripan'], ['strengthening_directions' => 'Arah penguatan']] as $group)
                @php($key = array_key_first($group))
                <section class="m15-section"><h2>{{ $group[$key] }}</h2>
                    @forelse($result['analysis'][$key] as $claim)
                        <article class="m15-claim"><p>{{ $claim['statement'] }}</p>@include('research-positioning._refs', ['ids' => $claim['refs'], 'works' => $result['works']])</article>
                    @empty<p>Tidak ada klaim dengan rujukan kandidat yang dapat divalidasi.</p>@endforelse
                </section>
            @endforeach

            <section class="m15-section m15-draft"><h2>Draf klaim kebaruan</h2>
                @if($result['analysis']['novelty_draft'])<p id="m15-novelty-draft">{{ $result['analysis']['novelty_draft']['statement'] }}</p><button class="button button-secondary" type="button" data-m15-copy>Salin draf</button>@include('research-positioning._refs', ['ids' => $result['analysis']['novelty_draft']['refs'], 'works' => $result['works']])<p class="m15-caveat">Draf ini perlu diperiksa dan ditulis ulang berdasarkan tinjauan literatur lengkap.</p>
                @else<p>Belum cukup bukti terhubung ke kandidat untuk menyusun draf klaim. Periksa literatur lebih luas.</p>@endif
            </section>
        @else
            <div class="m15-note"><strong>Analisis dimensi tidak tersedia.</strong><p>Daftar publikasi terdekat tetap dapat digunakan sebagai titik awal pemeriksaan manual.</p></div>
        @endif
    @else
        <div class="m15-note"><strong>Belum ada kandidat yang cocok.</strong><p>Perluas rentang tahun atau ubah ringkasan pencarian. Hasil kosong tidak membuktikan bahwa ide Anda baru.</p></div>
    @endif

    <div class="m15-note m15-limitations"><strong>Batas cakupan</strong><p>Pencarian ini hanya mencakup metadata yang tersedia di OpenAlex. Abstrak mungkin tidak ada atau tidak lengkap. Kandidat bukan seluruh literatur dan bukan bukti bahwa penelitian lain tidak ada. Buka sumber asli sebelum menyimpulkan.</p></div>
    <p class="m15-next"><a class="button" href="{{ route('research-positioning.form') }}">Periksa ide lain</a></p>
    <p><a href="{{ route('monitor.index') }}">Pantau publikasi baru untuk topik ini</a>. Pantauan memerlukan persetujuan terpisah dan ringkasan ide yang Anda isi ulang.</p>
    @if(!empty($inputAbstract))
        <form class="m15-forward" method="post" action="{{ route('semantic.run') }}">
            @csrf
            <input type="hidden" name="title" value="{{ $form['title'] }}">
            <input type="hidden" name="abstract" value="{{ $inputAbstract }}">
            <label class="m15-consent"><input type="checkbox" name="consent" value="1" required><span>Saya setuju mengirim judul dan abstrak ke Dinoiki untuk pencocokan jurnal semantik.</span></label>
            <button class="button button-secondary" type="submit">Bawa judul dan abstrak ke pencocokan jurnal</button>
        </form>
    @endif
</section>
<script src="{{ asset('m15-export.js') }}?v={{ filemtime(public_path('m15-export.js')) }}" defer></script>
@endsection
