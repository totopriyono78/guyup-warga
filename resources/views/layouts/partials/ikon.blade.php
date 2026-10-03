{{-- Ikon aplikasi Rukoon, manifest (bisa "Tambahkan ke layar utama" di HP), dan pratinjau tautan --}}
<meta name="theme-color" content="#0f766e">
<meta name="application-name" content="{{ config('siwarga.aplikasi') }}">
<meta name="apple-mobile-web-app-title" content="{{ config('siwarga.aplikasi') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<link rel="icon" href="{{ asset('img/logo.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('img/ikon/favicon-32.png') }}" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta property="og:site_name" content="{{ config('siwarga.aplikasi') }} · {{ pengaturan('nama_rw', '') }}">
{{-- isi @section('title', ...) sudah di-escape oleh Blade, jadi tidak di-escape ulang --}}
<meta property="og:title" content="{!! strip_tags(trim($__env->yieldContent('title', 'Info Warga'))) !!} · {{ pengaturan('nama_rw', config('siwarga.aplikasi')) }}">
<meta property="og:description" content="{!! strip_tags(trim($__env->yieldContent('deskripsi', 'Peta wilayah, informasi kegiatan, dan penggalangan dana warga.'))) !!}">
<meta property="og:image" content="{{ asset('img/og-rukoon.jpg') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
