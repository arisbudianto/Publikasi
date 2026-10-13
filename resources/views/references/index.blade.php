@extends('layout')

@section('title', 'Periksa referensi')

@section('content')
<section class="page-heading reference-check">
    <p class="eyebrow">ALAT BANTU</p>
    <h1>Periksa daftar referensi</h1>
    <p>Bandingkan sitasi dengan metadata publik Crossref. Hasil pencarian tanpa DOI adalah kemungkinan kecocokan dan perlu diperiksa kembali.</p>
    <p class="reference-disclosure">Tidak ditemukan di Crossref bukan berarti referensi palsu. Buku, prosiding, atau publikasi tanpa metadata Crossref mungkin tidak muncul.</p>

    <form id="reference-form" class="reference-form">
        @csrf
        <label for="reference-input"><strong>Daftar pustaka</strong></label>
        <textarea id="reference-input" name="references" rows="12" maxlength="30000" placeholder="Tempel daftar referensi, maksimal 100 entri dan 30.000 karakter."></textarea>
        <div class="reference-form-meta"><span id="reference-count">0 entri</span><span>Format APA atau teks bebas</span></div>
        <label class="reference-consent"><input id="reference-consent" type="checkbox"><span>Saya memahami bahwa setiap referensi akan dikirim satu per satu ke Crossref untuk pencarian. Jangan masukkan daftar pustaka yang bersifat rahasia.</span></label>
        <div class="reference-actions"><button class="button" type="submit" id="reference-submit">Periksa referensi</button><span id="reference-progress" role="status" aria-live="polite"></span></div>
        <p class="reference-disclosure">Aplikasi tidak menyimpan teks referensi atau kueri pencarian. Crossref adalah layanan pihak ketiga dan dapat mencatat permintaan yang diterimanya. Metadata DOI publik yang berhasil ditemukan dapat disimpan sementara untuk cache.</p>
    </form>

    <div id="reference-error" class="error" role="alert" hidden></div>
    <section id="reference-results" class="reference-results" hidden aria-labelledby="reference-results-title">
        <div class="reference-results-heading"><div><h2 id="reference-results-title">Hasil pemeriksaan</h2><p id="reference-summary"></p></div><div class="reference-actions"><button class="button button-secondary" id="export-csv" type="button" disabled>Unduh CSV</button><button class="button button-secondary" id="export-ris" type="button" disabled>Unduh RIS</button></div></div>
        <div class="table-wrap"><table><thead><tr><th>#</th><th>Sitasi</th><th>Status</th><th>Metadata Crossref</th><th>Perbedaan / pembaruan</th></tr></thead><tbody id="reference-rows"></tbody></table></div>
    </section>
</section>
<script src="{{ asset('reference-check.js') }}?v={{ filemtime(public_path('reference-check.js')) }}" defer></script>
@endsection
