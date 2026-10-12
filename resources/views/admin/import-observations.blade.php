@extends('layout')
@section('title', 'Impor observasi jurnal')
@section('content')
<a class="back" href="{{ route('admin.curation') }}">Kembali ke kurasi</a>
<p class="eyebrow">ADMIN · M10</p>
<h1>Impor observasi terstruktur</h1>
<p>Gunakan jurnal yang sudah ada di katalog kurasi. Data peringkat, biaya, bahasa, dan frekuensi harus memiliki sumber serta tanggal pemeriksaan. Kolom nilai lama tetap dipertahankan untuk tampilan dan riwayat.</p>
<p><a class="button secondary" href="{{ route('admin.observation-import.template') }}">Unduh template CSV</a></p>
<form class="panel" method="post" action="{{ route('admin.observation-import.preview') }}" enctype="multipart/form-data">
    @csrf
    <label>File CSV, maksimum 1 MB dan 500 baris<input type="file" name="csv" accept=".csv,text/csv" required></label>
    <button>Periksa CSV</button>
</form>
@if(isset($preview))
    <section class="panel">
        <h2>Hasil pemeriksaan</h2>
        @if($preview['errors'])
            <p role="alert">Impor belum dapat diterapkan. Perbaiki seluruh masalah berikut, lalu unggah kembali.</p>
            <ul>@foreach($preview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
        @else
            <p>{{ count($preview['rows']) }} baris valid. Preview tersimpan selama 15 menit. Tidak ada data yang ditulis sebelum Anda memilih Terapkan.</p>
            <div class="table-wrap"><table><thead><tr><th>Baris</th><th>Jurnal</th><th>ISSN</th><th>Jenis</th><th>Label</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
                @foreach($preview['rows'] as $row)<tr><td>{{ $row['line'] }}</td><td>{{ $row['journal'] }}</td><td>{{ $row['issn'] }}</td><td>{{ $row['data']['kind'] }}</td><td>{{ $row['data']['label'] }}</td><td>Valid</td><td>Siap diterapkan</td></tr>@endforeach
            </tbody></table></div>
            <form method="post" action="{{ route('admin.observation-import.commit') }}" class="form-actions">@csrf<button>Terapkan semua baris</button></form>
        @endif
    </section>
    @if($preview['errors'] && !empty($preview['results']))
        <div class="panel table-wrap"><table><thead><tr><th>Baris</th><th>Jurnal</th><th>ISSN</th><th>Jenis</th><th>Label</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
            @foreach($preview['results'] as $row)<tr><td>{{ $row['line'] }}</td><td>{{ $row['journal'] ?: 'Belum ditemukan' }}</td><td>{{ $row['issn'] }}</td><td>{{ $row['kind'] }}</td><td>{{ $row['label'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['reason'] }}</td></tr>@endforeach
        </tbody></table></div>
    @endif
@endif
<p class="muted">Jika satu baris tidak valid, tidak ada baris dari file tersebut yang diterapkan. Baris dengan jurnal, jenis, dan label sama dalam satu file ditolak. Pembaruan observasi aktif menyimpan versi sebelumnya dalam riwayat.</p>
@endsection
