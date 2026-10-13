@extends('layout')
@section('title', 'Pantauan topik riset')
@section('content')
<section class="page-heading">
    <p class="eyebrow">AKUN PRIBADI</p><h1>Pantauan topik riset</h1>
    <p>Periksa publikasi yang muncul dalam hasil OpenAlex sejak pemeriksaan sebelumnya. Temuan baru dalam pantauan belum tentu baru terbit atau relevan. Buka publikasi aslinya sebelum menarik kesimpulan.</p>
    @if(!empty($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
    @if(!empty($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(!$ready)<div class="panel" role="status">Pantauan belum aktif. Pemeriksaan otomatis menunggu cron cPanel yang terverifikasi dan diaktifkan pengelola.</div>@endif
    <h2>Topik saya</h2>
    @forelse($topics as $topic)
        @php($current = $topic->versions->firstWhere('number', $topic->current_version))
        <article class="panel"><h3><a href="{{ route('monitor.show', $topic) }}">{{ $current?->title_cipher }}</a></h3>
            <p>Versi {{ $topic->current_version }} · Terakhir diperiksa {{ $topic->last_checked_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? 'Belum' }} WIB · {{ $topic->findings->whereNull('read_at')->count() }} temuan belum dibaca</p>
            <a href="{{ route('monitor.show', $topic) }}">Lihat pantauan dan perubahan versi</a>
        </article>
    @empty<p>Belum ada topik pantauan.</p>@endforelse
    @if($ready && $topics->count() < config('m16.max_topics'))
        <h2>Tambah topik</h2>
        <form method="post" action="{{ route('monitor.store') }}" class="panel m15-form">@csrf
            <label>Judul ide riset<input name="title" required minlength="10" maxlength="300" value="{{ $form['title'] ?? '' }}"></label>
            <label>Ringkasan ide<textarea name="summary" required minlength="80" maxlength="1200" rows="6">{{ $form['summary'] ?? '' }}</textarea></label>
            <label class="m15-consent"><input type="checkbox" name="consent" value="1" required><span>Saya setuju menyimpan judul dan ringkasan dalam bentuk terenkripsi serta mengirimkannya ke OpenAlex untuk pemeriksaan berkala. Saya dapat menghapus topik dan teksnya kapan saja.</span></label>
            <button class="button">Mulai pantau</button>
        </form>
    @endif
</section>
@endsection
