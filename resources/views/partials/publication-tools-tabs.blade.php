<nav class="indexed-tabs" aria-label="Alat bantu publikasi">
    <a href="{{ route('match.form') }}" @if(request()->routeIs('match.*')) aria-current="page" @endif>Cocokkan naskah</a>
    <a href="{{ route('assistant.index') }}" @if(request()->routeIs('assistant.*')) aria-current="page" @endif>Tanya publikasi</a>
    <a href="{{ route('guides.index') }}" @if(request()->routeIs('guides.*')) aria-current="page" @endif>Panduan publikasi</a>
</nav>
