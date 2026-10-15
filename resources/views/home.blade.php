@extends('layout')
@section('title','Periksa jurnal sebelum submit')
@section('content')
<section class="landing-hero" aria-labelledby="landing-title">
    <div>
        <p class="eyebrow">RUANG KERJA PUBLIKASI ILMIAH</p>
        <h1 id="landing-title">Sudah punya naskah. <span>Jurnal mana yang masuk akal?</span></h1>
        <p class="landing-lead">Cari jurnal, periksa cakupan dan status informasinya, lalu siapkan langkah submit. Setiap data yang belum diverifikasi ditandai agar Anda tahu apa yang masih perlu dicek di situs jurnal.</p>
        <form class="landing-search" action="{{ route('accreditations.index') }}" method="get">
            <label for="journal-query">Cari jurnal pada keputusan akreditasi SINTA</label>
            <div><input id="journal-query" name="q" maxlength="120" placeholder="Misalnya: pendidikan vokasi" required><button type="submit">Cari jurnal</button></div>
        </form>
        <p class="landing-search-note">Hasil SINTA mengacu pada keputusan akreditasi resmi 24 Juli 2026. <a href="{{ route('scopus.index') }}">Cari sumber Scopus</a> dari snapshot September 2026. Status saat ini perlu diperiksa ulang.</p>
    </div>
    <div class="landing-preview" aria-label="Contoh langkah pemeriksaan">
        <div class="preview-top"><span>SEBELUM SUBMIT</span><span>01 / 03</span></div>
        <h2>Periksa satu per satu</h2>
        <ol><li><strong>Cakupan jurnal</strong><span>Apakah topik naskah sesuai dengan aims dan scope?</span></li><li><strong>Biaya dan indeksasi</strong><span>Buka sumber resmi dan lihat tanggal informasinya.</span></li><li><strong>Persyaratan submit</strong><span>Catat dokumen dan ketentuan penulis.</span></li></ol>
        <a href="{{ route('catalog.index') }}">Buka katalog terkurasi <span aria-hidden="true">→</span></a>
    </div>
</section>

<section class="landing-metrics" aria-label="Cakupan data">
    <div><strong>{{ number_format($sourceCount, 0, ',', '.') }}</strong><span>rekaman sumber SINTA, belum dikurasi satu per satu</span></div>
    <div><strong>{{ $curatedCount }}</strong><span>jurnal dengan cakupan yang dikurasi</span></div>
    <div><strong>Terbuka</strong><span>sumber dan keterbatasan data ditampilkan pada detail</span></div>
</section>

<section class="landing-section" id="fitur">
    <div class="landing-heading"><p class="eyebrow">MULAI DARI KEBUTUHAN ANDA</p><h2>Pilih langkah yang sedang Anda kerjakan.</h2><p>Alat ini membantu memeriksa informasi. Keputusan submit tetap perlu merujuk situs jurnal dan sumber indeks resmi.</p></div>
    <div class="landing-features">
        <a href="{{ route('accreditations.index') }}"><span>01 · TELUSURI</span><h3>Periksa akreditasi SINTA</h3><p>Cari nama jurnal atau ISSN pada keputusan akreditasi resmi.</p><b>Lihat keputusan <span aria-hidden="true">→</span></b></a>
        <a href="{{ route('scopus.index') }}"><span>SCOPUS · TELUSURI</span><h3>Cari sumber Scopus</h3><p>Periksa status dan cakupan pada daftar sumber September 2026.</p><b>Lihat daftar <span aria-hidden="true">→</span></b></a>
        <a href="{{ route('integrity.form') }}"><span>02 · PERIKSA</span><h3>Cek sebelum submit</h3><p>Gunakan daftar periksa untuk menilai transparansi jurnal dan tanda yang perlu diperhatikan.</p><b>Periksa jurnal <span aria-hidden="true">→</span></b></a>
        <a href="{{ route('match.form') }}"><span>03 · COCOKKAN</span><h3>Bandingkan naskah dan jurnal</h3><p>Mulai dari judul dan abstrak untuk melihat kandidat yang sesuai dengan cakupan terkurasi.</p><b>Coba pencocokan <span aria-hidden="true">→</span></b></a>
    </div>
</section>

<section class="landing-band"><div><p class="eyebrow">BACA SEBELUM MEMUTUSKAN</p><h2>Biaya belum tercatat bukan berarti gratis.</h2><p>Begitu juga label indeks pada snapshot lama bukan jaminan status hari ini. Periksa bukti, tanggal, dan tautan sumber pada detail jurnal.</p></div><div class="landing-band-links"><a href="{{ route('catalog.no-apc') }}">Jurnal tanpa APC terverifikasi <span aria-hidden="true">→</span></a><a href="{{ route('scopus.index', ['discontinued' => 1]) }}">Lihat penanda discontinued Scopus <span aria-hidden="true">→</span></a></div></section>

<section class="landing-bottom"><div><p class="eyebrow">LANJUTKAN DI AKUN ANDA</p><h2>Simpan jurnal dan pantau proses submit.</h2><p>Masuk untuk membuka menu kerja di sebelah kiri, menyimpan shortlist, dan mengelola riwayat Anda.</p></div><div><a class="button" href="{{ route('account.register') }}">Buat akun</a><a class="landing-login" href="{{ route('account.login') }}">Sudah punya akun? Masuk</a></div></section>
@endsection
