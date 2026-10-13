<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title','Publikasi') · Katalog jurnal pendidikan</title>
    <link rel="stylesheet" href="{{ asset('catalog.css') }}?v={{ filemtime(public_path('catalog.css')) }}">
    @if(request()->routeIs('research-positioning.*') || request()->routeIs('monitor.*'))<link rel="stylesheet" href="{{ asset('m15.css') }}?v={{ filemtime(public_path('m15.css')) }}">@endif
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="site-sidebar" aria-label="Navigasi utama">
        <div class="sidebar-brand">
            <a class="brand" href="{{ route('sources.home') }}">Publikasi<span>Database jurnal</span></a>
        </div>

        <nav class="sidebar-nav">
            <p class="nav-section">TEMUKAN JURNAL</p>
            <a class="sidebar-link {{ request()->routeIs('sources.home') ? 'is-active' : '' }}" href="{{ route('sources.home') }}" @if(request()->routeIs('sources.home')) aria-current="page" @endif>Semua jurnal</a>
            <a class="sidebar-link {{ request()->routeIs('accreditations.*') ? 'is-active' : '' }}" href="{{ route('accreditations.index') }}" @if(request()->routeIs('accreditations.*')) aria-current="page" @endif>Akreditasi SINTA</a>
            <a class="sidebar-link {{ request()->routeIs('catalog.index') || request()->routeIs('catalog.show') || request()->routeIs('catalog.compare') ? 'is-active' : '' }}" href="{{ route('catalog.index') }}" @if(request()->routeIs('catalog.index') || request()->routeIs('catalog.show') || request()->routeIs('catalog.compare')) aria-current="page" @endif>Katalog terkurasi</a>
            <a class="sidebar-link {{ request()->routeIs('catalog.no-apc') ? 'is-active' : '' }}" href="{{ route('catalog.no-apc') }}" @if(request()->routeIs('catalog.no-apc')) aria-current="page" @endif>Jurnal tanpa APC</a>

            <a class="sidebar-link {{ request()->routeIs('guides.*') ? 'is-active' : '' }}" href="{{ route('guides.index') }}">Panduan publikasi</a>
            <a class="sidebar-link {{ request()->routeIs('assistant.*') ? 'is-active' : '' }}" href="{{ route('assistant.index') }}">Tanya publikasi</a>
            <p class="nav-section">ALAT BANTU</p>
            <a class="sidebar-link {{ request()->routeIs('integrity.*') ? 'is-active' : '' }}" href="{{ route('integrity.form') }}" @if(request()->routeIs('integrity.*')) aria-current="page" @endif>Periksa jurnal</a>
            <a class="sidebar-link {{ request()->routeIs('references.*') ? 'is-active' : '' }}" href="{{ route('references.form') }}" @if(request()->routeIs('references.*')) aria-current="page" @endif>Periksa referensi</a>
            <a class="sidebar-link {{ request()->routeIs('authorship.*') ? 'is-active' : '' }}" href="{{ route('authorship.form') }}" @if(request()->routeIs('authorship.*')) aria-current="page" @endif>Periksa authorship</a>
            <a class="sidebar-link {{ request()->routeIs('research-positioning.*') ? 'is-active' : '' }}" href="{{ route('research-positioning.form') }}" @if(request()->routeIs('research-positioning.*')) aria-current="page" @endif>Posisi riset</a>
            <a class="sidebar-link {{ request()->routeIs('semantic.*') ? 'is-active' : '' }}" href="{{ route('semantic.form') }}" @if(request()->routeIs('semantic.*')) aria-current="page" @endif>Pencocokan AI</a>

            <p class="nav-section">AKUN</p>
            @auth
                <a class="sidebar-link {{ request()->routeIs('account.index') ? 'is-active' : '' }}" href="{{ route('account.index') }}" @if(request()->routeIs('account.index')) aria-current="page" @endif>Akun saya</a>
                <a class="sidebar-link {{ request()->routeIs('proposals.*') ? 'is-active' : '' }}" href="{{ route('proposals.mine') }}">Usulan saya</a>
                @if(auth()->user()->is_admin)
                    <a class="sidebar-link {{ request()->routeIs('admin.*') ? 'is-active' : '' }}" href="{{ route('admin.index') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Kelola katalog</a>
                    <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="sidebar-link sidebar-action" type="submit">Keluar</button></form>
                @else
                    <form method="post" action="{{ route('account.logout') }}">@csrf<button class="sidebar-link sidebar-action" type="submit">Keluar</button></form>
                @endif
            @else
                <a class="sidebar-link {{ request()->routeIs('account.login') ? 'is-active' : '' }}" href="{{ route('account.login') }}" @if(request()->routeIs('account.login')) aria-current="page" @endif>Login pengguna</a>
                <a class="sidebar-link {{ request()->routeIs('login') ? 'is-active' : '' }}" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Login admin</a>
            @endauth
        </nav>

        <div class="sidebar-footer">Data jurnal dengan sumber yang dapat diperiksa.</div>
    </aside>

    <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Tutup menu"></button>

    <div class="page-column">
        <div class="mobile-topbar">
            <button class="menu-toggle" type="button" aria-controls="site-sidebar" aria-expanded="false" data-sidebar-toggle>Menu</button>
            <a class="brand" href="{{ route('sources.home') }}">Publikasi<span>Database jurnal</span></a>
        </div>

        <main>
            @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
            @if($errors->any())
                <div class="error" role="alert"><strong>Periksa kembali input.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </main>

        <footer>Publikasi · Data awal dikurasi dari sumber resmi. Informasi yang belum diperiksa ditandai secara terbuka. · <a href="{{ route('service.status') }}">Layanan dan pembayaran opsional</a></footer>
    </div>
</div>
<script>
(() => {
    const sidebar = document.getElementById('site-sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeButton = document.querySelector('[data-sidebar-close]');

    if (!sidebar || !toggle || !closeButton) return;

    const setOpen = (open) => {
        sidebar.classList.toggle('is-open', open);
        closeButton.classList.toggle('is-visible', open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
    closeButton.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
})();
</script>
</body>
</html>
