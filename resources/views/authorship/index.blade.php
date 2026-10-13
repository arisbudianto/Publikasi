@extends('layout')

@section('title', 'Penentu authorship')

@section('content')
<section class="page-heading authorship-page">
    <p class="eyebrow">ALAT BANTU</p>
    <h1>Periksa kriteria authorship</h1>
    <p>Catat kontribusi tiap orang, periksa empat kriteria ICMJE, dan susun draf pernyataan kontribusi CRediT.</p>

    <div class="authorship-source-note">
        <strong>Dasar penilaian {{ \App\Services\AuthorshipEvaluator::VERSION }}</strong>
        <p>Keempat syarat ICMJE harus dipenuhi untuk menjadi penulis menurut rekomendasi ICMJE. Peran CRediT membantu menjelaskan kontribusi, tetapi CRediT sendiri tidak menentukan authorship. Keputusan akhir mengikuti kebijakan jurnal dan institusi.</p>
        <p><a href="https://www.icmje.org/recommendations/browse/roles-and-responsibilities/defining-the-role-of-authors-and-contributors.html" target="_blank" rel="noopener noreferrer">Rekomendasi ICMJE tentang penulis dan kontributor</a> · <a href="https://credit.niso.org/contributor-roles/" target="_blank" rel="noopener noreferrer">Taksonomi peran CRediT</a></p>
    </div>

    @if($inputErrors)
        <div class="error" role="alert"><strong>Periksa kembali isian.</strong><ul>@foreach($inputErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ route('authorship.analyze') }}" id="authorship-form" class="authorship-form">
        @csrf
        <div class="authorship-form-heading"><div><h2>Daftar kontributor</h2><p id="person-count">{{ count($people) }} dari 30 orang</p></div><button type="button" class="button button-secondary" id="add-person">Tambah orang</button></div>
        <div id="people-list">
            @foreach($people as $index => $person)
                @include('authorship._person', ['index' => $index, 'person' => $person])
            @endforeach
        </div>
        <p class="authorship-order-note">Usulan urutan hanya memakai jumlah peran CRediT yang dipilih (setiap peran dihitung sama; jumlah bukan ukuran usaha atau mutu). Urutan penulis ditentukan bersama oleh kelompok penulis dan kebiasaannya berbeda menurut bidang serta jurnal.</p>
        <button class="button" type="submit">Tinjau authorship</button>
        <p class="authorship-privacy">Isian diproses untuk permintaan ini saja. Aplikasi tidak menyimpan nama, jawaban, atau peran ke database, sesi, maupun log. Tidak ada AI yang digunakan.</p>
    </form>

    <template id="person-template">
        @include('authorship._person', ['index' => '__INDEX__', 'person' => ['name' => '', 'criteria' => [], 'roles' => []]])
    </template>

    @if($result)
        <section class="authorship-results" aria-labelledby="authorship-results-title">
            <p class="eyebrow">HASIL · {{ $result['version'] }}</p>
            <h2 id="authorship-results-title">Ringkasan kontribusi</h2>
            <p class="authorship-order-note">Hasil ini adalah alat bantu diskusi, bukan keputusan jurnal. Urutan sementara menggunakan jumlah peran terpilih tanpa pembobotan.</p>
            <div class="table-wrap authorship-table-wrap">
                <table class="authorship-results-table">
                    <thead><tr><th>Urutan sementara</th><th>Nama</th><th>Hasil kriteria</th><th>Syarat yang belum dipenuhi</th><th>Peran CRediT</th></tr></thead>
                    <tbody>
                    @foreach($result['people'] as $person)
                        <tr>
                            <td>{{ $person['suggested_order'] ?? '—' }}</td>
                            <td>{{ $person['name'] }}</td>
                            <td><strong class="authorship-status status-{{ $person['status'] }}">{{ $person['status_label'] }}</strong><p>{{ $person['note'] }}</p></td>
                            <td>@if($person['missing_criteria'])<ul>@foreach($person['missing_criteria'] as $key)<li>{{ strtoupper($key) }}: {{ $criteria[$key] }}</li>@endforeach</ul>@else — @endif</td>
                            <td>@if($person['role_labels']){{ implode('; ', $person['role_labels']) }}@else Belum dipilih @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($result['author_order'])<p><strong>Usulan urutan sementara:</strong> {{ implode(' → ', $result['author_order']) }}</p>@endif
            <div class="authorship-statement">
                <div class="authorship-form-heading"><h3>Pernyataan kontribusi CRediT</h3><button type="button" class="button button-secondary" id="copy-statement" @disabled($result['contribution_statement'] === '')>Salin pernyataan</button></div>
                <pre id="contribution-statement">{{ $result['contribution_statement'] !== '' ? $result['contribution_statement'] : 'Belum ada peran CRediT yang dipilih.' }}</pre>
                <p id="copy-feedback" role="status" aria-live="polite"></p>
            </div>
            <p class="authorship-final-note">Pastikan semua orang yang memenuhi kriteria diberi kesempatan meninjau draf dan menyetujui versi final. Perselisihan authorship perlu diselesaikan oleh institusi terkait; keputusan urutan dibuat bersama oleh kelompok penulis.</p>
        </section>
    @endif
</section>
<script src="{{ asset('authorship.js') }}?v={{ filemtime(public_path('authorship.js')) }}" defer></script>
@endsection
