@extends('layout')
@section('title', 'Tanya publikasi')
@section('content')
<section class="page-heading">
    <p class="eyebrow">ASISTEN PUBLIKASI</p>
    <h1>Tanya tentang jurnal dan publikasi</h1>
    <p>Jawaban merujuk pada katalog terkurasi dan panduan yang sudah diterbitkan. Periksa halaman sumber sebelum memilih jurnal.</p>
    <p>Untuk pertanyaan yang memerlukan AI, teks pertanyaan dan potongan sumber terkait dikirim ke penyedia model. Hindari memasukkan naskah atau data pribadi yang belum ingin Anda bagikan.</p>
    <form method="post" action="{{ route('assistant.answer') }}" class="panel">
        @csrf
        <label for="question">Pertanyaan</label>
        <textarea id="question" name="question" rows="4" minlength="12" maxlength="500" required placeholder="Contoh: Apa cakupan jurnal pendidikan dasar ini dan bagaimana memeriksa biaya publikasinya?">{{ old('question', $question ?? '') }}</textarea>
        <button type="submit">Cari jawaban</button>
    </form>
    @isset($notice)<p class="notice" role="status">{{ $notice }}</p>@endisset
    @if(!empty($sentences))
        <section class="panel" aria-label="Jawaban asisten"><h2>Jawaban</h2>
            @foreach($sentences as $sentence)
                <p>{{ $sentence['text'] }}
                    @foreach($sentence['source_ids'] as $id)
                        @php($source = collect($sources)->firstWhere('id', $id))
                        <a href="{{ $source['url'] }}">[{{ $source['title'] }}]</a>
                    @endforeach
                </p>
            @endforeach
        </section>
    @endif
    @if(!empty($sources))
        <section><h2>Sumber terkait</h2>
            @foreach($sources as $source)
                <article class="panel"><h3><a href="{{ $source['url'] }}">{{ $source['title'] }}</a></h3><p>{{ $source['excerpt'] }}</p></article>
            @endforeach
        </section>
    @endif
</section>
@endsection
