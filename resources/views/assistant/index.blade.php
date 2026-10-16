@extends('layout')
@section('title', 'Tanya publikasi')
@section('content')
<section class="page-heading">
    <p class="eyebrow">ASISTEN PUBLIKASI</p>
    <h1>Tanya tentang jurnal dan publikasi</h1>
    <p>Jawaban merujuk pada katalog jurnal dan panduan yang sudah diterbitkan. Periksa halaman sumber sebelum memilih jurnal.</p>
    <p>Untuk pertanyaan yang memerlukan AI, teks pertanyaan dan potongan sumber terkait dikirim ke penyedia model. Hindari memasukkan naskah atau data pribadi yang belum ingin Anda bagikan.</p>
    @include('assistant._form')
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
        <section class="panel" aria-label="Laporan jawaban">
            <h2>Jawaban ini keliru?</h2>
            <p>Jelaskan bagian yang perlu diperiksa. Laporan masuk antrean kurasi; pertanyaan dan jawaban di atas tidak disimpan bersama laporan.</p>
            @auth
                <form method="post" action="{{ route('proposals.assistant-feedback') }}">@csrf
                    @if($contextJournal)<input type="hidden" name="journal_id" value="{{ $contextJournal->id }}">@endif
                    <label for="feedback">Alasan koreksi</label>
                    <textarea id="feedback" name="feedback" required minlength="10" maxlength="1000" rows="3">{{ old('feedback') }}</textarea>
                    <button type="submit">Kirim laporan</button>
                </form>
            @else
                <p><a href="{{ route('account.login') }}">Login untuk melaporkan jawaban keliru</a>.</p>
            @endauth
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
