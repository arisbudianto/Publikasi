@extends('layout')
@section('title', 'Monitoring crawler SINTA dan Scopus')
@section('content')
<a href="{{ route('admin.index') }}">Kembali ke admin</a>
<h1>Monitoring crawler SINTA dan Scopus</h1>
<p>Lihat pemeriksaan halaman jurnal dan hasil sinkronisasi. Perubahan halaman saja belum membuktikan perubahan akreditasi, indeks Scopus, atau quartile. Identitas yang belum cocok tetap dicatat untuk ditinjau.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="get" action="{{ route('admin.scopus-crawl.index') }}" class="panel crawl-filters">
    <h2>Periode dan hasil</h2>
    <div class="crawl-filter-grid">
        <label>Periode<select name="period"><option value="today" @selected($period === 'today')>Hari ini (UTC)</option><option value="7d" @selected($period === '7d')>7 hari</option><option value="30d" @selected($period === '30d')>30 hari</option></select></label>
        <label>Sumber<select name="source"><option value="all" @selected($source === 'all')>SINTA dan Scopus</option><option value="sinta" @selected($source === 'sinta')>SINTA</option><option value="scopus" @selected($source === 'scopus')>Scopus</option></select></label>
        <label>Status<select name="status"><option value="all" @selected($status === 'all')>Semua</option><option value="changed" @selected($status === 'changed')>Berubah</option><option value="unchanged" @selected($status === 'unchanged')>Tanpa perubahan</option><option value="error" @selected($status === 'error')>Gagal</option></select></label>
    </div>
    <button>Tampilkan</button>
</form>
<div class="crawl-summary-grid">
@foreach(['sinta' => 'SINTA', 'scopus' => 'Scopus'] as $kind => $label)
    <section class="panel crawl-summary" aria-label="Ringkasan {{ $label }}">
        <h2>{{ $label }}</h2>
        <p>Jadwal otomatis: <strong>{{ config($kind === 'sinta' ? 'sinta_crawl.enabled' : 'scopus_crawl.enabled') ? 'aktif' : 'jeda' }}</strong> · target Scopus aktif: {{ $kind === 'scopus' ? $activeTargets : 'tidak berlaku' }}</p>
        <div class="crawl-numbers">
            <span><strong>{{ $summary[$kind]['checked'] }}</strong> diperiksa</span>
            <span class="crawl-changed"><strong>{{ $summary[$kind]['changed'] }}</strong> berubah</span>
            <span class="crawl-unchanged"><strong>{{ $summary[$kind]['unchanged'] }}</strong> tetap</span>
            <span class="crawl-error"><strong>{{ $summary[$kind]['error'] }}</strong> gagal</span>
            <span><strong>{{ $summary[$kind]['synced'] }}</strong> tersinkron</span>
        </div>
        <p>Kutipan menunggu tinjauan: <strong>{{ $summary[$kind]['pending'] }}</strong> · terakhir diperiksa: {{ $summary[$kind]['last'] ?: 'belum pernah' }} UTC</p>
    </section>
@endforeach
</div>
<p class="muted">Hitungan pemeriksaan mengikuti periode terpilih. Kutipan menunggu tinjauan dan waktu terakhir menunjukkan seluruh data. SINTA berjalan pukul 04.00 UTC, Scopus 03.30 UTC; keduanya berbagi kuota dengan agen katalog.</p>
<h2>Riwayat pemeriksaan</h2>
<div class="table-wrap"><table class="crawl-history"><thead><tr><th>Waktu UTC</th><th>Sumber</th><th>Jurnal dan halaman</th><th>Hasil</th></tr></thead><tbody>
@forelse($history as $check)
<tr><td>{{ $check->created_at }}</td><td>{{ $check->source }}</td><td><strong>{{ $check->journal_name ?: 'Tanpa nama' }}</strong><br><a href="{{ $check->url }}" target="_blank" rel="noopener noreferrer">Buka halaman diperiksa</a></td><td><span class="crawl-status crawl-status-{{ $check->status }}">{{ match($check->status) {'changed' => 'Berubah', 'unchanged' => 'Tetap', default => 'Gagal'} }}</span> {{ $check->synced ? '· Tersinkron ke jurnal' : '' }}<small>{{ $check->error_code ?: ($check->http_status ? 'HTTP '.$check->http_status : '') }}</small></td></tr>
@empty<tr><td colspan="4">Belum ada pemeriksaan untuk filter ini.</td></tr>@endforelse
</tbody></table></div>
<p class="muted">Menampilkan maksimal 50 pemeriksaan terbaru yang sesuai filter. Kesalahan seperti HTTP 403 atau robots disallowed tidak diulang otomatis pada halaman yang sama sampai jadwal berikutnya.</p>
<h2>Kutipan SINTA menunggu tinjauan</h2>
<p><a href="{{ route('admin.agent.index') }}">Buka agen jurnal untuk meninjau seluruh kutipan SINTA</a>.</p>
@forelse($sintaCandidates as $candidate)
<article class="panel"><h3>{{ $candidate->name ?: 'SINTA #'.$candidate->journal_source_id }}</h3><blockquote>{{ $candidate->quote }}</blockquote><a href="{{ route('admin.agent.index') }}">Tinjau di agen jurnal</a></article>
@empty<p>Belum ada kutipan SINTA yang menunggu.</p>@endforelse
<h2>Target dan kutipan Scopus</h2>
<p>Daftarkan URL resmi jurnal dari snapshot Scopus. Verifikasi identitas halaman sebelum mengaktifkannya. Maksimal {{ config('scopus_crawl.daily_limit') }} jurnal Scopus per hari, dalam kuota gabungan 20 pemeriksaan.</p>
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
@endsection
