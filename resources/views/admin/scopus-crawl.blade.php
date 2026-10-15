@extends('layout')
@section('title', 'Crawler jurnal Scopus')
@section('content')
<a href="{{ route('admin.index') }}">Kembali ke admin</a>
<h1>Crawler jurnal Scopus</h1>
<p>Periksa halaman resmi jurnal dalam snapshot Scopus. Agen mencatat perubahan dan kutipan untuk ditinjau. Halaman penerbit tidak membuktikan status indeks atau quartile Scopus terkini; hasil ini tidak mengubah katalog secara otomatis.</p>
<p>Jadwal: setiap hari 03.30 UTC, maksimal {{ config('scopus_crawl.daily_limit') }} jurnal per hari dengan kuota gabungan 20 pemeriksaan bersama agen lainnya. Status otomatis: <strong>{{ config('scopus_crawl.enabled') ? 'aktif' : 'jeda' }}</strong>.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="get" action="{{ route('admin.scopus-crawl.index') }}" class="panel">
    <h2>Cari jurnal dari snapshot</h2>
    <label>Judul atau ISSN<input name="q" value="{{ $q }}" maxlength="120" required></label>
    <button>Cari</button>
</form>
@foreach($matches as $match)
<article class="panel">
    <h3>{{ $match->source_title }}</h3>
    <p>ISSN {{ $match->issn ?: '—' }} · e-ISSN {{ $match->eissn ?: '—' }} · snapshot {{ $match->snapshot_month }}</p>
    <form method="post" action="{{ route('admin.scopus-crawl.store') }}">@csrf
        <input type="hidden" name="scopus_source_id" value="{{ $match->id }}">
        <label>Alamat halaman resmi HTTPS<input type="url" name="url" maxlength="1000" placeholder="https://jurnal.example.org/about" required></label>
        <label>Informasi<select name="field"><option value="scope">Cakupan</option><option value="fee">Biaya</option><option value="time">Waktu review</option><option value="acceptance">Angka penerimaan atau penolakan</option></select></label>
        <button>Daftarkan dalam keadaan jeda</button>
    </form>
</article>
@endforeach
<h2>Halaman terdaftar</h2>
@forelse($targets as $target)
<article class="panel">
    <h3>{{ $target->source_title }}</h3>
    <p>{{ $target->field }} · {{ $target->enabled ? 'Aktif' : 'Jeda' }} · {{ $target->is_current ? 'Snapshot aktif' : 'Snapshot lama' }}</p>
    <p><a href="{{ $target->url }}" target="_blank" rel="noopener noreferrer">Periksa halaman resmi</a> · berikutnya {{ $target->next_check_at ?: 'belum dijadwalkan' }}</p>
    <form method="post" action="{{ route('admin.scopus-crawl.toggle', $target->id) }}">@csrf
        <input type="hidden" name="enabled" value="{{ $target->enabled ? 0 : 1 }}">
        <button class="secondary">{{ $target->enabled ? 'Jeda' : 'Aktifkan setelah verifikasi URL' }}</button>
    </form>
</article>
@empty<p>Belum ada halaman resmi terdaftar. Cari jurnal dan periksa alamat situs penerbitnya sebelum mendaftar.</p>@endforelse
<h2>Kutipan menunggu tinjauan</h2>
@forelse($candidates as $candidate)
<article class="panel">
    <h3>{{ $candidate->source_title }} · {{ $candidate->field }}</h3>
    <blockquote>{{ $candidate->quote }}</blockquote>
    <p><a href="{{ $candidate->url }}" target="_blank" rel="noopener noreferrer">Buka bukti di situs jurnal</a></p>
    <form method="post" action="{{ route('admin.scopus-crawl.review', $candidate->id) }}">@csrf
        <label>Catatan pemeriksaan<textarea name="note" minlength="10" maxlength="2000" required></textarea></label>
        <button name="decision" value="reviewed">Tandai diperiksa</button>
        <button name="decision" value="rejected" class="secondary">Tolak kutipan</button>
    </form>
</article>
@empty<p>Belum ada kutipan yang menunggu tinjauan.</p>@endforelse
<h2>Riwayat pemeriksaan</h2>
@forelse($checks as $check)
<p>{{ $check->created_at }} · {{ $check->source_title }} · {{ $check->status }} · {{ $check->error_code ?: 'HTTP '.$check->http_status }} · <a href="{{ $check->url }}" target="_blank" rel="noopener noreferrer">Sumber</a></p>
@empty<p>Belum ada pemeriksaan.</p>@endforelse
@endsection
