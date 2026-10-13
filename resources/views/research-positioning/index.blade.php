@extends('layout')

@section('title', 'Periksa posisi riset')

@section('content')
<section class="page-heading m15-page">
    <p class="eyebrow">PILOT UNTUK AKUN TERDAFTAR</p>
    <h1>Periksa posisi riset</h1>
    <p>Temukan publikasi yang berdekatan dengan ide Anda, lalu tinjau persamaan dan pembeda berdasarkan metadata yang tersedia.</p>

    <div class="m15-note">
        <strong>Gunakan untuk memulai tinjauan literatur, bukan menetapkan kebaruan.</strong>
        <p>Pencarian memakai metadata OpenAlex. Setelah Anda menyetujui, judul dan abstrak dapat dikirim ke Dinoiki untuk penerjemahan, pemeringkatan semantik, dan analisis. Kebijakan retensi penyedia belum diaudit; jangan masukkan naskah rahasia.</p>
        <p>Skor kedekatan bukan persentase kebaruan, ukuran plagiarisme, atau peluang diterima. Abstrak bukan teks lengkap. Hasil sedikit atau kosong tidak membuktikan ide baru.</p>
    </div>

    @if(isset($failure))
        <div class="error" role="alert">{{ $failure }} <a href="{{ route('research-positioning.form') }}">Kembali ke formulir</a></div>
    @endif
    @if(isset($inputErrors))
        <div class="error" role="alert"><strong>Periksa kembali isian.</strong><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if(empty($ready))
        <div class="m15-note" role="status">Pilot belum aktif. Pencarian hanya tersedia setelah pengelola mengaktifkan layanan AI.</div>
    @else
        <form class="m15-form" method="post" action="{{ route('research-positioning.prepare') }}">
            @csrf
            <label for="m15-title">Judul ide riset</label>
            <input id="m15-title" name="title" type="text" minlength="10" maxlength="300" required value="{{ $form['title'] ?? '' }}">

            <label for="m15-abstract">Ringkasan atau abstrak</label>
            <textarea id="m15-abstract" name="abstract" minlength="80" maxlength="5000" rows="9" required>{{ $form['abstract'] ?? '' }}</textarea>

            <div class="m15-fields">
                <label for="m15-field">Bidang (opsional)<input id="m15-field" name="field" type="text" maxlength="120" value="{{ $form['field'] ?? '' }}" placeholder="Contoh: pendidikan vokasi"></label>
                <label for="m15-language">Bahasa input<select id="m15-language" name="language" required><option value="id" @selected(($form['language'] ?? 'id') === 'id')>Bahasa Indonesia</option><option value="en" @selected(($form['language'] ?? '') === 'en')>English</option></select></label>
                <label for="m15-year-from">Tahun awal (opsional)<input id="m15-year-from" name="year_from" type="number" min="1900" max="{{ now()->year + 1 }}" value="{{ $form['year_from'] ?? '' }}"></label>
                <label for="m15-year-to">Tahun akhir (opsional)<input id="m15-year-to" name="year_to" type="number" min="1900" max="{{ now()->year + 1 }}" value="{{ $form['year_to'] ?? '' }}"></label>
            </div>

            <label class="m15-consent"><input type="checkbox" name="consent" value="1" required @checked(($form['consent'] ?? null) === '1')><span>Saya setuju mengirim judul dan abstrak ke OpenAlex dan, untuk terjemahan atau analisis, ke Dinoiki. Saya tidak memasukkan informasi rahasia dan memahami hasil harus diperiksa terhadap publikasi aslinya.</span></label>
            <label class="m15-consent"><input type="checkbox" name="save_history" value="1" @checked(($form['save_history'] ?? null) === '1')><span>Simpan ringkasan hasil ke akun saya. Abstrak tidak disimpan.</span></label>
            <button class="button" type="submit">Periksa posisi</button>
            <p class="m15-privacy">Input tidak disimpan dalam sesi atau log aplikasi. Jika nanti memilih menyimpan hasil, abstrak tetap tidak disimpan.</p>
        </form>
    @endif
</section>
@endsection
