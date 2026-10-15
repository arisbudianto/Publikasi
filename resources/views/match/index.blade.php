@extends('layout')
@section('title','Pencocokan dasar')
@section('content')
<p class="eyebrow">PENCOCOKAN DASAR · M03</p><h1>Temukan scope yang beririsan.</h1>
<p>Pencocokan kata kunci judul, abstrak, dan konteks dengan scope katalog. Sistem mendukung padanan istilah Indonesia dan Inggris yang terbatas.</p>
<p class="muted">Input diproses sementara, tidak disimpan ke database atau sesi aplikasi dan tidak dikirim ke layanan AI. Hasil bukan penilaian mutu naskah atau peluang penerimaan.</p>
@include('partials.publication-tools-tabs')
@if(isset($inputErrors))<div class="error" role="alert"><strong>Periksa input. Isi ulang naskah karena input tidak disimpan.</strong><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('match.run') }}" class="panel match-form" autocomplete="off">@csrf
<label for="title">Judul penelitian<input id="title" name="title" minlength="10" maxlength="300" required placeholder="10–300 karakter"></label>
<label for="abstract">Abstrak<textarea id="abstract" name="abstract" minlength="80" maxlength="10000" rows="8" required placeholder="80–10.000 karakter; uraikan tujuan, metode, dan temuan"></textarea></label>
<label for="context">Konteks penelitian (opsional)<textarea id="context" name="context" maxlength="1500" rows="3" placeholder="Contoh: jenjang pendidikan, populasi, metode, atau jenis artikel"></textarea></label>
<label for="index">Indeks jurnal<select id="index" name="index"><option value="all">SINTA atau Scopus</option><option value="sinta">SINTA</option><option value="scopus">Scopus</option></select></label>
@auth<label class="consent"><input type="checkbox" name="save_history" value="1"> Simpan judul dan daftar kandidat ke riwayat pribadi. Abstrak dan konteks tidak disimpan.</label>@endauth<button>Jalankan pencocokan</button></form>
@if(isset($results))
<section class="match-results indexed-comparison">
<h2>Bandingkan jurnal untuk naskah Anda</h2>
<p>{{ count($indexedCandidates) }} kandidat dari judul dan abstrak, maksimal 10 per indeks. Tabel utama menampilkan dua Scopus dan satu SINTA bila keduanya tersedia; daftar lainnya mengikuti urutan Scopus lalu SINTA 1 sampai 4. Daftar Scopus yang tersedia tidak memuat quartile, sehingga urutan Q1 sampai Q4 belum dapat diverifikasi. Nilai irisan kata bukan peluang diterima.</p>
@if($indexedCandidates)
@php
    $comparisonCandidates = array_slice($indexedCandidates, 0, 3);
    if (($selectedIndex ?? 'all') === 'all') {
        $sintaCandidate = collect($indexedCandidates)->first(fn ($candidate) => $candidate['sinta'] !== null && $candidate['scopus'] === null);
        if ($sintaCandidate && count(array_filter($indexedCandidates, fn ($candidate) => $candidate['scopus'] !== null)) >= 2) {
            $comparisonCandidates = [...array_slice($indexedCandidates, 0, 2), $sintaCandidate];
        }
    }
    $remainingCandidates = array_values(array_filter($indexedCandidates, fn ($candidate) => ! in_array($candidate, $comparisonCandidates, true)));
@endphp
@if(($selectedIndex ?? 'all') === 'all' && ! collect($indexedCandidates)->contains(fn ($candidate) => $candidate['sinta'] !== null))
<p class="notice">Belum ada kandidat SINTA 1 sampai 4 dengan subjek yang beririsan dengan naskah ini. Ubah istilah topik jika perlu; jangan menganggap jurnal dari bidang lain sebagai pilihan submit.</p>
@endif
<div class="table-wrap comparison-wrap"><table class="comparison-table">
<thead><tr><th scope="col">Kriteria</th>@foreach($comparisonCandidates as $candidate)<th scope="col"><a href="{{ $candidate['url'] }}">{{ $candidate['name'] }}</a><small>{{ $candidate['publisher'] ?: 'Penerbit tidak tercatat' }}</small></th>@endforeach</tr></thead>
<tbody>
<tr><th scope="row">Kecocokan awal</th>@foreach($comparisonCandidates as $candidate)<td>{{ $candidate['score'] }} istilah beririsan<small>{{ implode(', ', $candidate['matches']) }}</small></td>@endforeach</tr>
<tr><th scope="row">Peringkat dan status</th>@foreach($comparisonCandidates as $candidate)<td>@if($candidate['scopus'])<span class="indexed-badge indexed-active">Scopus Active</span> <small>Quartile belum tersedia · snapshot {{ $candidate['scopus']->snapshot_month }}</small>@endif @if($candidate['sinta'])<span class="indexed-badge indexed-sinta">S{{ $candidate['sinta']->rank }}</span><small>SINTA · SK {{ $candidate['sinta']->decree_date?->format('d/m/Y') }}</small>@endif</td>@endforeach</tr>
<tr><th scope="row">Dasar pencocokan</th>@foreach($comparisonCandidates as $candidate)<td>{{ $candidate['evidence'] }}</td>@endforeach</tr>
<tr><th scope="row">ISSN</th>@foreach($comparisonCandidates as $candidate)<td>{{ $candidate['issn'] ?: 'Belum tercatat' }}</td>@endforeach</tr>
<tr><th scope="row">Peluang diterima</th>@foreach($comparisonCandidates as $candidate)<td>Belum tersedia</td>@endforeach</tr>
<tr><th scope="row">Biaya dan waktu review</th>@foreach($comparisonCandidates as $candidate)<td>Periksa panduan jurnal</td>@endforeach</tr>
</tbody></table></div>
@if($remainingCandidates)<details class="comparison-more"><summary>Lihat {{ count($remainingCandidates) }} kandidat lain</summary><div class="table-wrap"><table><thead><tr><th>Jurnal</th><th>Indeks</th><th>Kecocokan awal</th></tr></thead><tbody>@foreach($remainingCandidates as $candidate)<tr><td><a href="{{ $candidate['url'] }}">{{ $candidate['name'] }}</a></td><td>{{ $candidate['scopus'] ? 'Scopus, quartile belum tersedia' : 'S'.$candidate['sinta']->rank.' · SINTA' }}</td><td>{{ $candidate['score'] }} istilah: {{ implode(', ', $candidate['matches']) }}</td></tr>@endforeach</tbody></table></div></details>@endif
@else<p>Belum ada kandidat terindeks yang beririsan dengan topik. Coba istilah bidang yang lebih spesifik.</p>@endif
</section>
<section class="match-results"><h2>Hasil pencocokan · {{ count($results) }} kandidat</h2>
<p>Urutan mengikuti irisan istilah unik: judul berbobot 3, abstrak 1, konteks 1. Hanya kandidat dengan irisan judul atau abstrak ditampilkan, maksimal 10. Aturan {{ \App\Services\BasicMatcher::VERSION }}.</p>
@forelse($results as $rank=>$result) @php($journal=$result['journal'])
<article class="panel">@if($journal->needsReview())<p class="notice">Scope perlu diperiksa ulang: pemeriksaan lebih dari 180 hari lalu.</p>@endif @include('integrity._discontinued-alert',['journal'=>$journal,'discontinuedByJournal'=>$discontinuedByJournal ?? []])<p class="eyebrow">KANDIDAT {{ $rank+1 }} · {{ $journal->subject }}</p><h3><a href="{{ route('catalog.show',$journal) }}">{{ $journal->name }}</a></h3><p>{{ $journal->scope_summary }}</p>
<ul><li>Irisan judul: {{ implode(', ',$result['title_matches']) ?: 'Tidak ditemukan' }}</li><li>Irisan abstrak: {{ implode(', ',$result['abstract_matches']) ?: 'Tidak ditemukan' }}</li><li>Irisan konteks: {{ implode(', ',$result['context_matches']) ?: ($result['context_checked'] ? 'Tidak ditemukan; perlu diperiksa pada sumber resmi' : 'Konteks tidak diisi') }}</li></ul>
<p class="muted">Irisan kata tidak membuktikan jenjang, populasi, metode, atau jenis artikel diterima. Konteks belum diverifikasi; periksa panduan jurnal. Istilah yang tidak beririsan bukan bukti penolakan scope.</p>
<p class="source"><a href="{{ $journal->scope_source_url }}" target="_blank" rel="noopener noreferrer">Sumber scope resmi ↗</a> · Diperiksa {{ $journal->checked_at->format('d/m/Y') }}</p>
<a href="{{ route('catalog.index',['ids'=>[$journal->id]]) }}">Mulai perbandingan dengan jurnal ini</a>
@auth<form method="post" action="{{ route('account.save',$journal) }}" class="shortlist-action">@csrf<button class="secondary">Simpan shortlist</button></form>@endauth</article>
@empty<div class="empty"><h3>Belum ada kandidat dengan irisan kata kunci.</h3><p>Coba istilah bidang yang lebih spesifik atau telusuri katalog. Ini tidak berarti naskah tidak memiliki jurnal tujuan.</p></div>@endforelse
</section>@endif
@endsection
