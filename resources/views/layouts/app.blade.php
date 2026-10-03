@php
    $u = auth()->user();
    $namaRw = pengaturan('nama_rw', config('siwarga.aplikasi'));
    $menu = [
        ['route' => 'dashboard', 'aktif' => 'dashboard', 'icon' => 'home', 'label' => 'Beranda', 'tampil' => true],
        ['route' => 'denah', 'aktif' => 'denah', 'icon' => 'map', 'label' => 'Denah Wilayah', 'tampil' => true],
        ['route' => 'keluarga.index', 'aktif' => 'keluarga.*|anggota.*', 'icon' => 'users', 'label' => $u->isPengurus() ? 'Data Warga' : 'Keluarga Saya', 'tampil' => $u->isPengurus() || $u->kartu_keluarga_id],
        ['route' => 'cari', 'aktif' => 'cari', 'icon' => 'search', 'label' => 'Pencarian', 'tampil' => true],
        ['route' => 'pengumuman.index', 'aktif' => 'pengumuman.*', 'icon' => 'megaphone', 'label' => 'Pengumuman', 'tampil' => true],
        ['route' => 'galang.index', 'aktif' => 'galang.*', 'icon' => 'heart', 'label' => 'Galang Dana', 'tampil' => true],
        ['route' => 'galeri.index', 'aktif' => 'galeri.*', 'icon' => 'photo', 'label' => 'Galeri Kegiatan', 'tampil' => true],
        ['route' => 'iuran.saya', 'aktif' => 'iuran.saya|pembayaran.show', 'icon' => 'qr', 'label' => 'Iuran Saya', 'tampil' => (bool) $u->kartu_keluarga_id],
    ];
    $menuPengurus = [
        ['route' => 'iuran.index', 'aktif' => 'iuran.index|iuran.transaksi|tarif.*', 'icon' => 'wallet', 'label' => 'Kelola Iuran', 'tampil' => $u->isPengurus()],
        ['route' => 'donasi.index', 'aktif' => 'donasi.*', 'icon' => 'heart', 'label' => 'Kelola Galang Dana', 'tampil' => $u->isPengurus()],
        ['route' => 'wilayah', 'aktif' => 'wilayah|blok.*', 'icon' => 'building', 'label' => 'RT, Blok & Rumah', 'tampil' => $u->isPengurus()],
        ['route' => 'pengguna.index', 'aktif' => 'pengguna.*', 'icon' => 'key', 'label' => 'Akun Pengguna', 'tampil' => $u->isPengurus()],
        ['route' => 'pengaturan', 'aktif' => 'pengaturan', 'icon' => 'cog', 'label' => 'Pengaturan', 'tampil' => $u->isAdmin()],
    ];
    $isAktif = fn ($pola) => collect(explode('|', $pola))->contains(fn ($p) => request()->routeIs($p));
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') · {{ $namaRw }}</title>
    @include('layouts.partials.ikon')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <script>try { if (localStorage.getItem('rukoon.sidebar') === 'tutup') document.documentElement.classList.add('sb-tutup'); } catch (e) {}</script>
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    @stack('head')
</head>
<body class="h-full font-sans text-slate-800 antialiased" x-data="{
    menu: false,
    tutup: document.documentElement.classList.contains('sb-tutup'),
    toggleSidebar() {
        this.tutup = !this.tutup;
        document.documentElement.classList.toggle('sb-tutup', this.tutup);
        try { localStorage.setItem('rukoon.sidebar', this.tutup ? 'tutup' : 'buka'); } catch (e) {}
        // beri tahu peta (Leaflet) agar menyesuaikan ukurannya
        requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
    },
}">
<div class="min-h-full">
    {{-- Sidebar desktop --}}
    <aside id="sidebar-desktop" class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex">
        @include('layouts.partials.sidebar')
    </aside>

    {{-- Sidebar mobile --}}
    <div x-cloak x-show="menu" class="fixed inset-0 z-40 lg:hidden">
        <div x-show="menu" x-transition.opacity class="absolute inset-0 bg-slate-900/40" @click="menu = false"></div>
        <aside x-show="menu" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full"
               x-transition:leave="transition duration-150" x-transition:leave-end="-translate-x-full"
               class="absolute inset-y-0 left-0 flex w-72 max-w-[85%] flex-col bg-white shadow-xl">
            @include('layouts.partials.sidebar')
        </aside>
    </div>

    <div id="konten" class="lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="btn btn-ghost -ml-2 p-2 lg:hidden" @click="menu = true" aria-label="Buka menu">
                <x-icon name="menu" class="size-6" />
            </button>
            <button type="button" class="btn btn-ghost -ml-2 hidden p-2 lg:inline-flex" @click="toggleSidebar()"
                    :title="tutup ? 'Tampilkan menu samping' : 'Sembunyikan menu samping'" :aria-pressed="tutup.toString()" aria-label="Tampilkan/sembunyikan menu samping">
                <x-icon name="sidebar" class="size-6 transition-transform" ::class="tutup ? '' : 'rotate-180'" />
            </button>
            <form action="{{ route('cari') }}" method="get" class="flex-1">
                <label class="relative block max-w-md">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><x-icon name="search" class="size-4" /></span>
                    <input type="search" name="q" value="{{ request()->routeIs('cari') ? request('q') : '' }}"
                           placeholder="Cari warga atau rumah…" class="input rounded-full pl-9">
                </label>
            </form>
            <a href="{{ route('publik') }}" class="btn btn-ghost shrink-0 gap-1.5 px-2.5 sm:px-3" title="Kembali ke website">
                <x-icon name="globe" class="size-5" /> <span class="hidden text-sm sm:inline">Website</span>
            </a>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-5 pb-[calc(6rem+env(safe-area-inset-bottom))] sm:px-6 lg:py-8 lg:pb-10">
            <x-flash />
            @yield('content')
        </main>
    </div>

    {{-- Navigasi bawah untuk HP --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden">
        @foreach ([
            ['dashboard', 'dashboard', 'home', 'Beranda'],
            ['denah', 'denah', 'map', 'Denah'],
            ['pengumuman.index', 'pengumuman.*', 'megaphone', 'Info'],
            $u->isPengurus() ? ['iuran.index', 'iuran.index|iuran.transaksi|tarif.*', 'wallet', 'Iuran'] : ['iuran.saya', 'iuran.saya|pembayaran.show', 'qr', 'Iuran'],
        ] as [$r, $a, $i, $l])
            <a href="{{ route($r) }}" class="relative flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium {{ $isAktif($a) ? 'text-brand-700' : 'text-slate-500' }}">
                @if ($isAktif($a))<span class="absolute inset-x-5 top-0 h-0.5 rounded-full bg-brand-600"></span>@endif
                <x-icon :name="$i" class="size-5" />{{ $l }}
            </a>
        @endforeach
        {{-- Menu lengkap (galeri, galang dana, data warga, dll.) --}}
        <button type="button" @click="menu = true" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-slate-500">
            <x-icon name="menu" class="size-5" />Menu
        </button>
    </nav>
</div>
@stack('scripts')
</body>
</html>
