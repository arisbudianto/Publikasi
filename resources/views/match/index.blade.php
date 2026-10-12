@extends('layout')
@section('title','Pencocokan dasar')
@section('content')
<p class="eyebrow">PENCOCOKAN DASAR · M03</p><h1>Temukan scope yang beririsan.</h1>
<p>Pencocokan kata kunci judul, abstrak, dan konteks dengan scope katalog. Sistem mendukung padanan istilah Indonesia dan Inggris yang terbatas.</p>
<p class="muted">Input diproses sementara, tidak disimpan ke database atau sesi aplikasi dan tidak dikirim ke layanan AI. Hasil bukan penilaian mutu naskah atau peluang penerimaan.</p>
@if(isset($inputErrors))<div class="error" role="alert"><strong>Periksa input. Isi ulang naskah karena input tidak disimpan.</strong><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('match.run') }}" class="panel match-form" autocomplete="off">@csrf
<label for="title">Judul penelitian<input id="title" name="title" minlength="10" maxlength="300" required placeholder="10–300 karakter"></label>
<label for="abstract">Abstrak<textarea id="abstract" name="abstract" minlength="80" maxlength="10000" rows="8" required placeholder="80–10.000 karakter; uraikan tujuan, metode, dan temuan"></textarea></label>
<label for="context">Konteks penelitian (opsional)<textarea id="context" name="context" maxlength="1500" rows="3" placeholder="Contoh: jenjang pendidikan, populasi, metode, atau jenis artikel"></textarea></label>
@auth<label class="consent"><input type="checkbox" name="save_history" value="1"> Simpan judul dan daftar kandidat ke riwayat pribadi. Abstrak dan konteks tidak disimpan.</label>@endauth<button>Jalankan pencocokan</button></form>
@if(isset($results))
<section class="match-results"><h2>Hasil pencocokan · {{ count($results) }} kandidat</h2>
<p>Urutan mengikuti irisan istilah unik: judul berbobot 3, abstrak 1, konteks 1. Hanya kandidat dengan irisan judul atau abstrak ditampilkan, maksimal 10. Aturan {{ \App\Services\BasicMatcher::VERSION }}.</p>
@forelse($results as $rank=>$result) @php($journal=$result['journal'])
<article class="panel"><p class="eyebrow">KANDIDAT {{ $rank+1 }} · {{ $journal->subject }}</p><h3><a href="{{ route('catalog.show',$journal) }}">{{ $journal->name }}</a></h3><p>{{ $journal->scope_summary }}</p>
<ul><li>Irisan judul: {{ implode(', ',$result['title_matches']) ?: 'Tidak ditemukan' }}</li><li>Irisan abstrak: {{ implode(', ',$result['abstract_matches']) ?: 'Tidak ditemukan' }}</li><li>Irisan konteks: {{ implode(', ',$result['context_matches']) ?: ($result['context_checked'] ? 'Tidak ditemukan; perlu diperiksa pada sumber resmi' : 'Konteks tidak diisi') }}</li></ul>
<p class="muted">Irisan kata tidak membuktikan jenjang, populasi, metode, atau jenis artikel diterima. Konteks belum diverifikasi; periksa panduan jurnal. Istilah yang tidak beririsan bukan bukti penolakan scope.</p>
<p class="source"><a href="{{ $journal->scope_source_url }}" target="_blank" rel="noopener noreferrer">Sumber scope resmi ↗</a> · Diperiksa {{ $journal->checked_at->format('d/m/Y') }}</p>
<a href="{{ route('catalog.index',['ids'=>[$journal->id]]) }}">Mulai perbandingan dengan jurnal ini</a>
@auth<form method="post" action="{{ route('account.save',$journal) }}" class="shortlist-action">@csrf<button class="secondary">Simpan shortlist</button></form>@endauth</article>
@empty<div class="empty"><h3>Belum ada kandidat dengan irisan kata kunci.</h3><p>Coba istilah bidang yang lebih spesifik atau telusuri katalog. Ini tidak berarti naskah tidak memiliki jurnal tujuan.</p></div>@endforelse
</section>@endif
@endsection
