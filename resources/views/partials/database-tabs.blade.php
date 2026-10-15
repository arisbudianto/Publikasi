<nav class="indexed-tabs" aria-label="Jenis data jurnal">
    <a href="{{ route('scopus.index') }}" @if(request()->routeIs('scopus.*')) aria-current="page" @endif>Scopus · daftar sumber</a>
    <a href="{{ route('accreditations.index') }}" @if(request()->routeIs('accreditations.*')) aria-current="page" @endif>Akreditasi SINTA · keputusan resmi</a>
</nav>
