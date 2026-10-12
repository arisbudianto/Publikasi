@extends('layout')
@section('title','Pencocokan semantik AI')
@section('content')
<p class="eyebrow">PENCOCOKAN SEMANTIK · M04</p><h1>Jelajahi makna dan artikel contoh.</h1>
<p>AI membandingkan makna naskah dengan scope jurnal. Artikel contoh berasal dari metadata Crossref dan tautan DOI; tidak dibuat oleh AI.</p>
<p>Hasil merupakan shortlist eksplorasi. Kemiripan bukan peluang diterima. Konteks, metode, jenjang, dan persyaratan jurnal tetap perlu diperiksa.</p>
@if(!$ready)<div class="notice">Mode AI belum diaktifkan pengelola. <a href="{{ route('match.form') }}">Gunakan pencocokan dasar</a>.</div>@endif
@if(isset($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
@if(isset($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>Input tidak disimpan; isi ulang formulir.</div>@endif
<form method="post" action="{{ route('semantic.run') }}" class="panel match-form" autocomplete="off">@csrf
<label for="title">Judul penelitian<input id="title" name="title" required minlength="10" maxlength="300"></label>
<label for="abstract">Abstrak<textarea id="abstract" name="abstract" required minlength="80" maxlength="5000" rows="7"></textarea></label>
<label for="context">Konteks (opsional)<textarea id="context" name="context" maxlength="500" rows="3"></textarea></label>
<p>Judul, abstrak, dan konteks dikirim ke Dinoiki untuk embedding. Dinoiki dapat meneruskan pemrosesan ke penyedia model sesuai kebijakannya. Aplikasi tidak menyimpan input atau embedding naskah. Metadata artikel publik dan embedding scope dapat di-cache.</p>
<label class="consent"><input type="checkbox" name="consent" value="1" required> Saya menyetujui pengiriman judul, abstrak, dan konteks ke Dinoiki untuk pencocokan ini.</label>
<p class="muted">Crossref hanya menerima ISSN jurnal; isi naskah tidak dikirim ke Crossref. Hindari data pribadi atau naskah rahasia tanpa izin yang sesuai.</p>@auth<label class="consent"><input type="checkbox" name="save_history" value="1"> Simpan judul dan kandidat ke riwayat pribadi; tanpa abstrak atau konteks.</label>@endauth<button @disabled(!$ready)>Cocokkan dengan AI</button>
</form>
@if(isset($results))<section><h2>Shortlist semantik · {{ count($results) }} jurnal</h2><p>Urutan berdasarkan cosine similarity scope. Ambang 0,30 bersifat awal dan belum dikalibrasi pada korpus naskah. Skala −1 sampai 1, bukan persentase penerimaan.</p>
@forelse($results as $r) @php($journal=$r['journal'])<article class="panel semantic-result">@if($journal->needsReview())<p class="notice">Scope perlu diperiksa ulang: pemeriksaan lebih dari 180 hari lalu.</p>@endif<h3><a href="{{ route('catalog.show',$journal) }}">{{ $journal->name }}</a></h3><p>Kemiripan scope: {{ number_format($r['similarity'],3) }}</p><p>{{ $journal->scope_summary }}</p><p><a href="{{ $journal->scope_source_url }}" target="_blank" rel="noopener noreferrer">Sumber scope ↗</a> · Diperiksa {{ $journal->checked_at->format('d/m/Y') }}</p>
<h4>Artikel contoh dari jurnal</h4><p class="muted">Sampel maksimal tiga artikel terbaru di Crossref, diurutkan menurut kemiripan judul. Artikel ini bukan bukti penerimaan metode Anda; abstrak dan isi artikel belum dianalisis.</p>
@forelse($r['evidence']['articles'] as $article)<div class="observation"><a href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer">{{ $article['title'] }} ↗</a><p>{{ $article['year'] ?? 'Tahun belum diketahui' }} · DOI {{ $article['doi'] }}</p><small>Metadata {{ $article['source'] }} · Diperiksa {{ $article['checked_at'] }}@if(isset($article['similarity'])) · Kemiripan judul {{ number_format($article['similarity'],3) }}@endif</small></div>@empty<p>{{ $r['evidence']['status']==='unavailable' ? 'Sumber artikel sedang tidak tersedia.' : 'Belum ada metadata artikel yang dapat diverifikasi berdasarkan ISSN.' }}</p>@endforelse
@if($r['evidence']['ranking_unavailable']??false)<p class="muted">Pengurutan semantik artikel gagal; metadata tetap ditampilkan.</p>@endif
<a href="{{ route('catalog.index',['ids'=>[$journal->id]]) }}">Mulai perbandingan</a>@auth<form method="post" action="{{ route('account.save',$journal) }}" class="shortlist-action">@csrf<button class="secondary">Simpan shortlist</button></form>@endauth</article>
@empty<div class="empty">Belum ada kandidat melewati ambang semantik. Telusuri katalog atau gunakan pencocokan dasar.</div>@endforelse</section>@endif
@endsection
