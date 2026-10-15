@extends('layout')
@section('title', 'Agen pemantauan jurnal')
@section('content')
<a href="{{ route('admin.curation') }}">Kembali ke kurasi</a>
<h1>Agen pemantauan jurnal</h1>
<p>Sumber harus berada pada domain situs resmi jurnal. Agen hanya mencatat kutipan untuk ditinjau; persetujuan di sini tidak menerbitkan informasi ke katalog.</p>
<form method="post" action="{{ route('admin.agent.store') }}" class="panel">
    @csrf
    <h2>Daftarkan halaman resmi</h2>
    <label>Jurnal<select name="journal_id" required><option value="">Pilih jurnal</option>@foreach($journals as $journal)<option value="{{ $journal->id }}">{{ $journal->name }}</option>@endforeach</select></label>
    <label>Alamat halaman HTTPS<input type="url" name="url" maxlength="1000" required placeholder="https://jurnal.example.org/about/submissions"></label>
    <label>Informasi<select name="field"><option value="scope">Cakupan</option><option value="fee">Biaya</option><option value="time">Waktu review</option><option value="acceptance">Angka penerimaan atau penolakan</option></select></label>
    <button>Daftarkan dalam keadaan jeda</button>
</form>
<h2>Sumber terdaftar</h2>
@forelse($sources as $source)
    <div class="panel"><p>{{ $source->journal_name }} · {{ $source->field }} · {{ $source->enabled ? 'Aktif' : 'Jeda' }}</p>
        <p><a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->url }}</a></p>
        <p>Terakhir diperiksa: {{ $source->last_checked_at ?? 'belum' }} · Berikutnya: {{ $source->next_check_at ?? 'belum dijadwalkan' }}</p>
        <form method="post" action="{{ route('admin.agent.toggle', $source->id) }}">@csrf<input type="hidden" name="enabled" value="{{ $source->enabled ? 0 : 1 }}"><button class="secondary">{{ $source->enabled ? 'Jeda' : 'Aktifkan' }}</button></form>
    </div>
@empty<p>Belum ada halaman yang didaftarkan.</p>@endforelse
<h2>Kutipan menunggu pemeriksaan</h2>
@forelse($candidates as $candidate)
    <article class="panel"><h3>{{ $candidate->journal_name }} · {{ $candidate->field }}</h3><blockquote>{{ $candidate->quote }}</blockquote>
        <p><a href="{{ $candidate->url }}" target="_blank" rel="noopener noreferrer">Buka sumber</a> · Hash halaman: {{ $candidate->content_hash }}</p>
        <p><a href="{{ route('admin.review', $candidate->journal_id) }}">Buka kurasi jurnal</a>. Periksa halaman langsung dan perbandingkan dengan data aktif sebelum menerbitkan perubahan secara manual.</p>
        <form method="post" action="{{ route('admin.agent.review', $candidate->id) }}">@csrf
            <label>Catatan pemeriksaan<textarea name="note" minlength="10" maxlength="2000" required></textarea></label>
            <button name="decision" value="reviewed">Tandai sudah diperiksa</button>
            <button name="decision" value="rejected" class="secondary">Tolak kutipan</button>
        </form>
    </article>
@empty<p>Belum ada kutipan yang menunggu.</p>@endforelse
<h2>Pemeriksaan halaman sumber SINTA</h2>
<p>Catatan halaman sumber SINTA adalah bukti untuk kurasi. Pemeriksaan ini tidak memverifikasi peringkat akreditasi.</p>
@forelse($sintaChecks as $check)
    <p><a href="{{ route('sources.show', $check->journal_source_id) }}">{{ $check->name ?: 'SINTA #'.$check->journal_source_id }}</a> · {{ $check->status }} · {{ $check->error_code ?: $check->created_at }}</p>
@empty<p>Belum ada halaman SINTA yang diperiksa.</p>@endforelse
<h2>Kutipan SINTA menunggu pemeriksaan</h2>
@forelse($sintaCandidates as $candidate)
    <article class="panel"><h3>{{ $candidate->name ?: 'SINTA #'.$candidate->journal_source_id }}</h3>
        <blockquote>{{ $candidate->quote }}</blockquote>
        <p><a href="{{ $candidate->evidence_url }}" target="_blank" rel="noopener noreferrer">Buka halaman bukti</a> · <a href="{{ route('sources.show', $candidate->journal_source_id) }}">Lihat rekaman SINTA</a></p>
        <form method="post" action="{{ route('admin.agent.sinta.review', $candidate->id) }}">@csrf
            <button name="decision" value="reviewed">Tandai sudah diperiksa</button>
            <button name="decision" value="rejected" class="secondary">Tolak kutipan</button>
        </form>
    </article>
@empty<p>Belum ada kutipan SINTA yang menunggu.</p>@endforelse
@endsection
