@extends('layout')
@section('title', 'Detail pantauan')
@section('content')
<section class="page-heading"><p class="eyebrow">PANTAUAN PRIBADI</p><h1>{{ $current->title_cipher }}</h1>
    <p>Versi {{ $current->number }} · Terakhir diperiksa {{ $topic->last_checked_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? 'Belum' }} WIB. Hasil di bawah adalah kandidat dari metadata OpenAlex yang perlu diperiksa terhadap publikasi asli.</p>
    @if(!$ready)<div class="panel" role="status">Pemeriksaan otomatis belum aktif atau cron belum berjalan dalam 36 jam terakhir.</div>@endif
    @if($ready && $versions->count() < config('m16.max_versions'))<p><a class="button" href="{{ route('monitor.edit', $topic) }}">Buat versi baru</a></p>@endif
    <h2>Temuan sejak pantauan dimulai</h2>
    @forelse($findings as $finding)
        <article class="panel"><h3><a href="{{ $finding->work['url'] }}" target="_blank" rel="noopener noreferrer">{{ $finding->work['title'] }}</a></h3>
            <p>{{ $finding->work['year'] ?? 'Tahun tidak tersedia' }} · Terlihat {{ $finding->seen_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $finding->read_at ? 'Sudah dibaca' : 'Belum dibaca' }}</p>
            @unless($finding->read_at)<form method="post" action="{{ route('monitor.read', [$topic, $finding]) }}">@csrf<button class="secondary">Tandai dibaca</button></form>@endunless
        </article>
    @empty<p>Belum ada temuan baru pada pemeriksaan setelah baseline. Hasil kosong bukan bukti topik belum diteliti.</p>@endforelse
    @if($comparison)
        <h2>Bandingkan versi ide</h2>
        <form method="get" action="{{ route('monitor.show', $topic) }}" class="panel">
            <label>Versi awal <select name="older">@foreach($versions as $version)<option value="{{ $version->number }}" @selected($compareOlder->number === $version->number)>Versi {{ $version->number }}</option>@endforeach</select></label>
            <label>Versi pembanding <select name="newer">@foreach($versions as $version)<option value="{{ $version->number }}" @selected($compareNewer->number === $version->number)>Versi {{ $version->number }}</option>@endforeach</select></label>
            <button class="button">Bandingkan</button>
        </form>
        <h3>Versi {{ $compareOlder->number }} dibanding Versi {{ $compareNewer->number }}</h3>
        <p>{{ $compareOlder->title_cipher }} → {{ $compareNewer->title_cipher }}. Daftar kandidat dapat berubah ketika pantauan diperiksa kembali. Perubahan daftar bukan ukuran kebaruan atau relevansi publikasi.</p>
        @foreach(['added' => 'Bertambah', 'removed' => 'Tidak muncul pada versi baru', 'shared' => 'Muncul pada keduanya'] as $key => $label)
            <h3>{{ $label }} ({{ count($comparison[$key]) }})</h3><ul>@forelse($comparison[$key] as $work)<li><a href="{{ $work['url'] }}" target="_blank" rel="noopener noreferrer">{{ $work['title'] }}</a> · {{ $work['year'] ?? 'Tahun tidak tersedia' }}</li>@empty<li>Tidak ada.</li>@endforelse</ul>
        @endforeach
    @endif
    <h2>Hentikan pantauan</h2><p>Penghapusan menghapus semua versi terenkripsi dan temuan untuk topik ini.</p>
    <form method="post" action="{{ route('monitor.destroy', $topic) }}">@csrf @method('DELETE')<button class="secondary">Hapus topik dan datanya</button></form>
    <p><a href="{{ route('monitor.index') }}">Kembali ke daftar pantauan</a></p>
</section>
@endsection
