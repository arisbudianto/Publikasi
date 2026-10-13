@extends('layout')
@section('title', 'Versi baru ide riset')
@section('content')
<section class="page-heading"><p class="eyebrow">PANTAUAN PRIBADI</p><h1>Versi baru ide riset</h1>
    @if(!empty($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
    @if(!empty($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('monitor.revise', $topic) }}" class="panel m15-form">@csrf
        <label>Judul versi baru<input name="title" required minlength="10" maxlength="300" value="{{ $form['title'] }}"></label>
        <label>Ringkasan versi baru<textarea name="summary" required minlength="80" maxlength="1200" rows="6">{{ $form['summary'] }}</textarea></label>
        <label class="m15-consent"><input type="checkbox" name="consent" value="1" required><span>Saya setuju menyimpan versi baru dalam bentuk terenkripsi dan mengirimkannya ke OpenAlex. Perubahan daftar publikasi menunjukkan perubahan hasil pencarian, bukan ukuran kebaruan.</span></label>
        <button class="button">Simpan dan bandingkan</button>
    </form><p><a href="{{ route('monitor.show', $topic) }}">Kembali ke topik</a></p>
</section>
@endsection
