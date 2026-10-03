<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('siwarga.aplikasi') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6 font-sans text-center">
    <div class="max-w-md">
        <p class="text-6xl font-black text-brand-700">@yield('code')</p>
        <h1 class="mt-3 text-xl font-bold text-slate-900">@yield('judul')</h1>
        <p class="mt-2 text-slate-600">@yield('pesan')</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-6">Kembali ke beranda</a>
    </div>
</body>
</html>
