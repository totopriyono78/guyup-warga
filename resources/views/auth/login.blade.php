<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ pengaturan('nama_rw', config('siwarga.aplikasi')) }}</title>
    @include('layouts.partials.ikon')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-br from-brand-800 via-brand-700 to-teal-600 p-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center text-white">
            <img src="{{ asset('img/logo.svg') }}" alt="{{ config('siwarga.aplikasi') }}" class="mx-auto mb-3 size-16 rounded-2xl shadow-lg ring-4 ring-white/20">
            <h1 class="text-2xl font-bold">{{ pengaturan('nama_rw', config('siwarga.aplikasi')) }}</h1>
            <p class="text-sm text-brand-100">
                {{ wilayah() ?: 'Aplikasi warga RT/RW' }}
            </p>
        </div>
        <form method="post" action="{{ route('login') }}" class="card card-body space-y-4 shadow-xl">
            @csrf
            <div>
                <label for="email" class="label">Email atau No. HP</label>
                <input id="email" name="email" type="text" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="input @error('email') input-error @enderror">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-700"> Ingat saya
            </label>
            <button class="btn btn-primary w-full py-2.5">Masuk</button>
            <p class="text-center text-xs text-slate-500">Belum punya akun? Hubungi pengurus RT Anda.</p>
        </form>
        <p class="mt-5 text-center text-sm"><a href="{{ route('publik') }}" class="font-medium text-white/90 underline-offset-4 hover:underline">&larr; Lihat peta &amp; info umum {{ pengaturan('nama_rw', '') }}</a></p>
        <div class="mt-8 flex items-center justify-center gap-2 text-xs text-white/70">
            <span>Ditenagai</span><img src="{{ asset('img/rukoon-putih.svg') }}" alt="{{ config('siwarga.aplikasi') }}" class="h-5">
        </div>
    </div>
</body>
</html>
