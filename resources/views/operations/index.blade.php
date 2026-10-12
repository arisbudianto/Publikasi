@extends('layout') @section('title','Operasional produksi') @section('content')
<p class="eyebrow">M09 · ADMIN</p><h1>Operasional produksi</h1><p>Pemeriksaan terbatas pada runtime aplikasi. Waktu pemeriksaan {{ $health['checked_at'] }} (UTC).</p>
<div class="panel table-wrap"><table><thead><tr><th>Pemeriksaan</th><th>Hasil</th></tr></thead><tbody>@foreach($health['checks'] as $key=>$status)<tr><td>{{ $key }}</td><td>{{ $status }}</td></tr>@endforeach</tbody></table></div>
<p>Antrean: {{ $health['counts']['queued_jobs'] ?? 'Tidak diketahui' }}. Gagal: {{ $health['counts']['failed_jobs'] ?? 'Tidak diketahui' }}.</p><p>AI {{ $health['ai_enabled']?'aktif':'nonaktif' }}; key {{ $health['ai_key_set']?'tersedia':'belum tersedia' }}. Pembayaran opsional {{ $health['payments_enabled']?'aktif, pencatatan manual':'nonaktif' }}.</p>
<p>Backup dan uji restore, pengiriman email, scheduler, serta koneksi penyedia AI tidak diuji oleh halaman ini. Jangan menyamakan PASS runtime dengan kesiapan seluruh layanan.</p><p><a href="{{ route('admin.payments') }}">Pencatatan pembayaran opsional</a> · <a href="{{ route('admin.index') }}">Kembali ke katalog</a></p>
@endsection
