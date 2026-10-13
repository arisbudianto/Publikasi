@extends('layout')

@section('title', 'Tinjau terjemahan')

@section('content')
<section class="page-heading m15-page">
    <p class="eyebrow">LANGKAH 2 DARI 2</p>
    <h1>Tinjau teks untuk pencarian</h1>
    <p>Terjemahan dibuat dengan AI. Perbaiki istilah atau maksud yang kurang tepat sebelum pencarian dimulai.</p>
    @if(isset($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
    @if(isset($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="m15-original"><h2>Judul asli</h2><p>{{ $form['title'] ?? '' }}</p><h2>Abstrak asli</h2><p>{{ $form['abstract'] ?? '' }}</p></div>
    <form class="m15-form" method="post" action="{{ route('research-positioning.analyze') }}">
        @csrf
        <input type="hidden" name="title" value="{{ $form['title'] ?? '' }}">
        <input type="hidden" name="abstract" value="{{ $form['abstract'] ?? '' }}">
        <input type="hidden" name="field" value="{{ $form['field'] ?? '' }}">
        <input type="hidden" name="year_from" value="{{ $form['year_from'] ?? '' }}">
        <input type="hidden" name="year_to" value="{{ $form['year_to'] ?? '' }}">
        <label for="translated-query">Teks Inggris yang dipakai untuk mencari publikasi</label>
        <textarea id="translated-query" name="translated_query" minlength="20" maxlength="8000" rows="12" required>{{ $translation ?? ($form['translated_query'] ?? '') }}</textarea>
        <label class="m15-consent"><input type="checkbox" name="consent" value="1" required><span>Saya setuju mengirim teks Inggris yang tampil di atas ke OpenAlex dan Dinoiki untuk pencarian, pemeringkatan, dan analisis. Input tidak disimpan oleh aplikasi.</span></label>
        <label class="m15-consent"><input type="checkbox" name="save_history" value="1" @checked(($form['save_history'] ?? null) === '1')><span>Simpan hasil ringkas ke akun saya. Judul, publikasi pembanding, dan analisis disimpan; abstrak tidak disimpan.</span></label>
        <button class="button" type="submit">Cari dan analisis</button>
        <a class="m15-cancel" href="{{ route('research-positioning.form') }}">Batalkan dan hapus isian dari halaman</a>
    </form>
</section>
@endsection
