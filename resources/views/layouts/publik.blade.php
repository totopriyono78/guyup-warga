@php
    $namaRw = pengaturan('nama_rw', config('siwarga.aplikasi'));
    $wilayah = wilayah();
    $wilayahSingkat = wilayah('singkat');
@endphp
<!DOCTYPE html>
<html lang="id" class="bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Info Warga') · {{ $namaRw }}</title>
    <meta name="description" content="@yield('deskripsi', 'Peta wilayah, informasi umum, dan penggalangan dana '.$namaRw.'.')">
    @include('layouts.partials.ikon')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    @stack('head')
</head>
<body class="min-h-screen font-sans text-slate-800 antialiased">
    <header class="sticky top-0 z-[1100] border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4 sm:px-6">
            <a href="{{ route('publik') }}" class="flex min-w-0 flex-1 items-center" aria-label="{{ config('siwarga.aplikasi') }} — beranda">
                <img src="{{ asset('img/rukoon.svg') }}" alt="{{ config('siwarga.aplikasi') }}" class="h-9 w-auto shrink-0">
            </a>
            <nav class="hidden items-center gap-1 text-sm font-medium text-slate-600 md:flex">
                <a href="{{ route('publik') }}#peta" class="rounded-lg px-3 py-2 hover:bg-slate-100">Peta</a>
                <a href="{{ route('publik') }}#galang-dana" class="rounded-lg px-3 py-2 hover:bg-slate-100">Galang dana</a>
                <a href="{{ route('publik') }}#info" class="rounded-lg px-3 py-2 hover:bg-slate-100">Info</a>
                <a href="{{ route('publik.galeri') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Galeri</a>
            </nav>
            @if (auth()->check())
                <a href="{{ route('dashboard') }}" class="btn btn-primary shrink-0">Dasbor</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary shrink-0"><x-icon name="lock" class="size-4" /> Masuk</a>
            @endif
        </div>
        <nav class="flex gap-1 overflow-x-auto border-t border-slate-100 px-2 py-1 text-sm font-medium text-slate-600 md:hidden">
            @foreach ([['#peta', 'Peta'], ['#galang-dana', 'Galang dana'], ['#info', 'Info']] as [$h, $l])
                <a href="{{ route('publik').$h }}" class="shrink-0 rounded-lg px-3 py-1.5 hover:bg-slate-100">{{ $l }}</a>
            @endforeach
            <a href="{{ route('publik.galeri') }}" class="shrink-0 rounded-lg px-3 py-1.5 hover:bg-slate-100 {{ request()->routeIs('publik.galeri*') ? 'bg-brand-50 text-brand-800' : '' }}">Galeri</a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-12 border-t border-slate-200 bg-white">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-slate-600 sm:grid-cols-2 sm:px-6">
            <div>
                <p class="font-semibold text-slate-900">{{ $namaRw }}</p>
                @if ($wilayah)<p>{{ $wilayah }}</p>@endif
                @if (pengaturan('alamat_sekretariat'))
                    <p class="mt-2 flex gap-2"><x-icon name="pin" class="mt-0.5 size-4 shrink-0" /> <span>Sekretariat: {{ pengaturan('alamat_sekretariat') }}</span></p>
                @endif
                @if (pengaturan('kontak'))
                    <p class="mt-1 flex gap-2"><x-icon name="phone" class="mt-0.5 size-4 shrink-0" /> <span>{{ pengaturan('kontak') }}</span></p>
                @endif
            </div>
            <div class="sm:text-right">
                <p>Data pribadi warga hanya dapat dilihat oleh warga terdaftar dan pengurus.</p>
                <p class="mt-1">Belum punya akun? Hubungi pengurus RT Anda.</p>
                <p class="mt-3 flex items-center gap-2 text-xs text-slate-400 sm:justify-end">&copy; {{ now()->year }} {{ $namaRw }} · Ditenagai
                    <img src="{{ asset('img/rukoon.svg') }}" alt="{{ config('siwarga.aplikasi') }}" class="h-4"></p>
            </div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
