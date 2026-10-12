<details class="directory-filter" @if($activeFilterCount) open @endif>
    <summary>Filter ({{ $activeFilterCount }} aktif)</summary>
    <form method="get" action="{{ $filterAction }}" class="directory-filter-form">
        @foreach($ids as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach
        <label>Cari nama atau ISSN<input name="q" maxlength="120" value="{{ $filters['q'] ?? '' }}" placeholder="Nama jurnal atau ISSN"></label>
        <label>Bidang<select name="subject"><option value="">Semua bidang</option>@foreach($subjects as $subject)<option value="{{ $subject }}" @selected(($filters['subject'] ?? '') === $subject)>{{ $subject }}</option>@endforeach</select></label>
        @unless($noApc ?? false)
        <label>Skema peringkat<select name="rank_scheme"><option value="">Semua skema</option>@foreach($options['rankSchemes'] as $scheme)<option value="{{ $scheme }}" @selected(($filters['rank_scheme'] ?? '') === $scheme)>{{ $scheme }}</option>@endforeach</select></label>
        <label>Nilai peringkat<input type="number" min="1" max="100" name="rank_value" value="{{ $filters['rank_value'] ?? '' }}" placeholder="mis. 2"></label>
        <label>Indeksasi<input name="indexation" maxlength="120" value="{{ $filters['indexation'] ?? '' }}" placeholder="mis. DOAJ"></label>
        <label>Jalur biaya<select name="fee_route"><option value="">Semua jalur</option>@foreach(['apc_oa'=>'APC akses terbuka','subscription'=>'Langganan','submission_fee'=>'Biaya submit','other'=>'Lainnya'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['fee_route'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>APC maksimum (unit minor)<input type="number" min="0" name="apc_max_minor" value="{{ $filters['apc_max_minor'] ?? '' }}" placeholder="0 diperbolehkan"></label>
        <label>Mata uang<select name="apc_currency"><option value="">Pilih mata uang</option>@foreach($options['currencies'] as $currency)<option value="{{ $currency }}" @selected(($filters['apc_currency'] ?? '') === $currency)>{{ $currency }}</option>@endforeach</select></label>
        <p class="filter-help">Nilai maksimum memakai unit minor mata uang yang dipilih. Data yang tidak diketahui tidak masuk hasil filter biaya.</p>
        @endunless
        <label>Bahasa<select name="language"><option value="">Semua bahasa</option>@foreach($options['languages'] as $language)<option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>{{ $language }}</option>@endforeach</select></label>
        @unless($noApc ?? false)
        <div class="filter-pair"><label>Frekuensi minimum/tahun<input type="number" min="1" max="365" name="frequency_min" value="{{ $filters['frequency_min'] ?? '' }}"></label><label>Frekuensi maksimum/tahun<input type="number" min="1" max="365" name="frequency_max" value="{{ $filters['frequency_max'] ?? '' }}"></label></div>
        <label>Status sumber<select name="verification"><option value="">Semua status</option><option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>Terverifikasi</option><option value="publisher_claim" @selected(($filters['verification'] ?? '') === 'publisher_claim')>Klaim penerbit</option></select></label>
        <label>Urutkan<select name="sort"><option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Nama</option><option value="rank" @selected(($filters['sort'] ?? '') === 'rank')>Peringkat (perlu skema)</option><option value="cost" @selected(($filters['sort'] ?? '') === 'cost')>Biaya terendah (perlu mata uang)</option><option value="checked" @selected(($filters['sort'] ?? '') === 'checked')>Pemeriksaan terbaru</option></select></label>
        <label>Informasi<select name="information"><option value="">Jenis informasi (filter lama)</option>@foreach(['index'=>'Indeksasi','fee'=>'Biaya publikasi','time'=>'Waktu proses'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['information'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Ketersediaan<select name="availability"><option value="">Semua status (filter lama)</option><option value="known" @selected(($filters['availability'] ?? '') === 'known')>Informasi tersedia</option><option value="unknown" @selected(($filters['availability'] ?? '') === 'unknown')>Belum diketahui</option></select></label>
        @endunless
        @if(!($noApc ?? false))<label>Penerbit<select name="publisher"><option value="">Semua penerbit</option>@foreach($publishers as $publisher)<option value="{{ $publisher }}" @selected(($filters['publisher'] ?? '') === $publisher)>{{ $publisher }}</option>@endforeach</select></label>@endif
        <div class="filter-actions"><button>Terapkan filter</button><a href="{{ $filterAction }}{{ count($ids) ? '?'.http_build_query(['ids'=>$ids]) : '' }}">Reset</a></div>
    </form>
</details>
