@extends('layout')
@section('title','Mencari jurnal yang tepat dengan Bantuan AI')
@section('content')
<h1>Mencari jurnal yang tepat dengan Bantuan AI</h1>
<p>Masukkan judul dan abstrak. AI membandingkan naskah dengan cakupan jurnal terkurasi. Kandidat SINTA dan Scopus ditampilkan terpisah berdasarkan irisan kata pada data indeks.</p>
<p class="muted">Kecocokan bukan peluang diterima. Periksa cakupan, indeks, dan persyaratan pada sumber resmi sebelum submit.</p>
@if(!$ready)<div class="notice" role="status">Layanan AI belum diaktifkan pengelola. Formulir tersedia setelah layanan siap.</div>@endif
@if(isset($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
@if(isset($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>Input tidak disimpan; isi ulang formulir.</div>@endif
<form method="post" action="{{ route('semantic.run') }}" class="panel match-form" autocomplete="off">@csrf
<label for="title">Judul penelitian<input id="title" name="title" required minlength="10" maxlength="300" placeholder="10–300 karakter"></label>
<label for="abstract">Abstrak<textarea id="abstract" name="abstract" required minlength="80" maxlength="5000" rows="8" placeholder="80–5.000 karakter; uraikan tujuan, metode, dan temuan"></textarea></label>
<label for="context">Konteks penelitian (opsional)<textarea id="context" name="context" maxlength="500" rows="3" placeholder="Contoh: jenjang pendidikan, populasi, metode, atau jenis artikel"></textarea></label>
<label for="index">Indeks jurnal<select id="index" name="index"><option value="all" @selected(($selectedIndex ?? 'all') === 'all')>SINTA atau Scopus</option><option value="sinta" @selected(($selectedIndex ?? '') === 'sinta')>SINTA</option><option value="scopus" @selected(($selectedIndex ?? '') === 'scopus')>Scopus</option></select></label>
<p class="muted">Pilihan indeks berlaku untuk daftar kandidat dari data indeks. AI membandingkan cakupan katalog terkurasi yang tersedia, bukan seluruh jurnal Scopus atau SINTA.</p>
<p>Judul, abstrak, dan konteks dikirim ke Dinoiki untuk membuat embedding. Dinoiki dapat meneruskan pemrosesan ke penyedia model sesuai kebijakannya. Aplikasi tidak menyimpan isi naskah atau embedding naskah; metadata publik dan embedding cakupan dapat di-cache.</p>
<label class="consent"><input type="checkbox" name="consent" value="1" required> Saya menyetujui pengiriman judul, abstrak, dan konteks ke Dinoiki untuk pencocokan ini.</label>
<p class="muted">Crossref hanya menerima ISSN jurnal. Hindari memasukkan data pribadi atau naskah rahasia tanpa izin.</p>
@auth<label class="consent"><input type="checkbox" name="save_history" value="1"> Simpan judul dan kandidat ke riwayat pribadi; abstrak dan konteks tidak disimpan.</label>@endauth
<button @disabled(!$ready)>Cari jurnal dengan AI</button>
</form>
@if(isset($results))<section><h2>Kecocokan cakupan dengan AI · {{ count($results) }} jurnal</h2><p>Urutan berdasarkan cosine similarity scope. Ambang 0,30 bersifat awal dan belum dikalibrasi pada korpus naskah. Skala −1 sampai 1, bukan persentase penerimaan.</p>
@forelse($results as $r) @php($journal=$r['journal'])<article class="panel semantic-result">@if($journal->needsReview())<p class="notice">Scope perlu diperiksa ulang: pemeriksaan lebih dari 180 hari lalu.</p>@endif @include('integrity._discontinued-alert',['journal'=>$journal,'discontinuedByJournal'=>$discontinuedByJournal ?? []])<h3><a href="{{ route('catalog.show',$journal) }}">{{ $journal->name }}</a></h3><p>Kemiripan scope: {{ number_format($r['similarity'],3) }}</p><p>{{ $journal->scope_summary }}</p><p><a href="{{ $journal->scope_source_url }}" target="_blank" rel="noopener noreferrer">Sumber scope ↗</a> · Diperiksa {{ $journal->checked_at->format('d/m/Y') }}</p>
<h4>Artikel contoh dari jurnal</h4><p class="muted">Sampel maksimal tiga artikel terbaru di Crossref, diurutkan menurut kemiripan judul. Artikel ini bukan bukti penerimaan metode Anda; abstrak dan isi artikel belum dianalisis.</p>
@forelse($r['evidence']['articles'] as $article)<div class="observation"><a href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer">{{ $article['title'] }} ↗</a><p>{{ $article['year'] ?? 'Tahun belum diketahui' }} · DOI {{ $article['doi'] }}</p><small>Metadata {{ $article['source'] }} · Diperiksa {{ $article['checked_at'] }}@if(isset($article['similarity'])) · Kemiripan judul {{ number_format($article['similarity'],3) }}@endif</small></div>@empty<p>{{ $r['evidence']['status']==='unavailable' ? 'Sumber artikel sedang tidak tersedia.' : 'Belum ada metadata artikel yang dapat diverifikasi berdasarkan ISSN.' }}</p>@endforelse
@if($r['evidence']['ranking_unavailable']??false)<p class="muted">Pengurutan semantik artikel gagal; metadata tetap ditampilkan.</p>@endif
<a href="{{ route('catalog.index',['ids'=>[$journal->id]]) }}">Mulai perbandingan</a>@auth<form method="post" action="{{ route('account.save',$journal) }}" class="shortlist-action">@csrf<button class="secondary">Simpan shortlist</button></form>@endauth</article>
@empty<div class="empty">Belum ada kandidat katalog yang melewati ambang semantik. Periksa istilah topik atau telusuri katalog.</div>@endforelse</section>@endif

@if(isset($indexedCandidates))
<section class="match-results">
<h2>Kandidat dari data indeks · {{ count($indexedCandidates) }}</h2>
<p>Daftar ini memakai irisan istilah judul dan abstrak, bukan penilaian AI atas scope. Sumber Scopus yang tersedia tidak memuat quartile; verifikasi status terkini pada sumber resmi.</p>
@forelse($indexedCandidates as $candidate)
<article class="panel"><h3><a href="{{ $candidate['url'] }}">{{ $candidate['name'] }}</a></h3>
<p>
@if($candidate['scopus'])
Scopus · snapshot {{ $candidate['scopus']->snapshot_month }} · quartile belum tersedia
@endif
@if($candidate['sinta'])
<span class="rank-badge rank-badge--sinta-{{ $candidate['sinta']->rank }}">SINTA {{ $candidate['sinta']->rank }}</span>
@endif
· ISSN {{ $candidate['issn'] ?: 'belum tercatat' }}</p>
<p>{{ $candidate['evidence'] }} · Istilah beririsan: {{ implode(', ', $candidate['matches']) }}</p></article>
@empty
<p>Belum ada kandidat dari indeks yang beririsan dengan istilah naskah ini.</p>
@endforelse
</section>
@endif
@endsection
