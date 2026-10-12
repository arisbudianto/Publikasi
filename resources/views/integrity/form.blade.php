@extends('layout')
@section('title', 'Periksa integritas jurnal')
@section('content')
<p class="eyebrow">PEMERIKSAAN BERBASIS BUKTI · M12</p><h1>Periksa informasi jurnal</h1>
<p class="lead">Gunakan checklist untuk menilai informasi yang dapat Anda verifikasi. Hasil bukan vonis tentang jurnal dan tidak menyatakan jurnal “predator”.</p>
<div class="notice">Jawaban diproses sementara dan tidak disimpan untuk pengunjung. Jika Anda login, Anda dapat memilih untuk menyimpan jawaban ke akun. Teks pencarian tidak disimpan.</div>
@if(isset($inputErrors))<div class="error" role="alert"><strong>Periksa input. Isian tidak disimpan; isi ulang form.</strong><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('integrity.check') }}" class="panel integrity-form" autocomplete="off">@csrf
    <label for="lookup">Nama jurnal persis atau ISSN<input id="lookup" name="lookup" required minlength="2" maxlength="180" placeholder="Nama jurnal atau 1234-567X"></label>
    <p class="muted">Bila jurnal ditemukan di katalog atau daftar discontinued, informasi bersumber akan ditampilkan. Pencarian tidak menemukan jurnal bukan bukti bahwa jurnal bermasalah atau bebas masalah.</p>
    <fieldset><legend>Checklist</legend>
        @foreach($criteria as $key=>$criterion)
            <div class="integrity-question"><p><strong>{{ $loop->iteration }}. {{ $criterion['question'] }}</strong><br><span>{{ $criterion['detail'] }}</span> <a href="{{ $criterion['source'] }}" target="_blank" rel="noopener noreferrer">Cara memeriksa ↗</a></p>
                <div class="integrity-answers"><label><input type="radio" name="answers[{{ $key }}]" value="yes" required> Ya</label><label><input type="radio" name="answers[{{ $key }}]" value="no" required> Tidak</label><label><input type="radio" name="answers[{{ $key }}]" value="unknown" required> Tidak tahu</label></div>
            </div>
        @endforeach
    </fieldset>
    @auth<label class="consent"><input type="checkbox" name="save_assessment" value="1"> Simpan jawaban checklist ini ke akun saya. Teks pencarian tidak ikut disimpan.</label>@endauth
    <p class="muted">Skor hanya menjumlahkan bobot kriteria yang perlu ditinjau. “Tidak tahu” tetap ditandai belum diketahui dan tidak dihitung aman. Versi kriteria: {{ \App\Services\JournalIntegrityEvaluator::VERSION }}.</p>
    <button>Periksa dan tampilkan hasil</button>
</form>
<p><a href="{{ route('integrity.discontinued') }}">Cari daftar Scopus discontinued ↗</a></p>
<section class="panel"><h2>Rujukan kriteria</h2><ul><li><a href="https://thinkchecksubmit.org/journals/" target="_blank" rel="noopener noreferrer">Think. Check. Submit. — checklist jurnal ↗</a></li><li><a href="https://doi.org/10.24318/cope.2019.1.12" target="_blank" rel="noopener noreferrer">COPE/DOAJ/OASPA/WAME — prinsip transparansi ↗</a></li><li><a href="https://publicationethics.org/core-practices" target="_blank" rel="noopener noreferrer">COPE — praktik inti ↗</a></li></ul></section>
@endsection
