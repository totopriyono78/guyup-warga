<?php

return [

    // Nama aplikasi (merek). Nama RW tetap diatur di menu Pengaturan.
    'aplikasi' => env('SIWARGA_APLIKASI', 'Rukoon'),

    /*
    | Identitas wilayah. Nilai ini hanya default awal — pengurus RW dapat
    | mengubahnya dari menu Pengaturan (disimpan di tabel pengaturans).
    */
    'nama_rw' => env('SIWARGA_NAMA_RW', 'RW 02'),
    'dusun' => env('SIWARGA_DUSUN', 'Sidorejo'),
    'kelurahan' => env('SIWARGA_KELURAHAN', 'Selomartani'),
    'kecamatan' => env('SIWARGA_KECAMATAN', 'Kalasan'),
    'kabupaten' => env('SIWARGA_KABUPATEN', env('SIWARGA_KOTA', 'Sleman')),
    'provinsi' => env('SIWARGA_PROVINSI', 'D.I. Yogyakarta'),

    // Ukuran maksimal foto yang diunggah (KB) dan sisi terpanjang setelah dikompres (px)
    'foto_max_kb' => (int) env('SIWARGA_FOTO_MAX_KB', 5120),
    'foto_max_px' => (int) env('SIWARGA_FOTO_MAX_PX', 1000),

    // Akun admin RW pertama, dibuat oleh `php artisan db:seed`
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin RW'),
        'email' => env('ADMIN_EMAIL', 'admin@rw.local'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // IP reverse proxy yang dipercaya (pisahkan koma). Default hanya localhost.
    // Isi bila memakai load balancer / Cloudflare, mis. "10.0.0.0/8,173.245.48.0/20"
    // "*" = percayai semua proxy (wajib di Railway/Render/Fly karena HTTPS diakhiri di load balancer mereka)
    'trusted_proxies' => trim((string) env('TRUSTED_PROXIES', '127.0.0.1,::1')) === '*'
        ? '*'
        : array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))))),

    // Peta wilayah (Leaflet)
    'peta' => [
        // Isi untuk memakai citra satelit Google (Map Tiles API). Kosong = OpenStreetMap + citra satelit Esri.
        'google_key' => env('GOOGLE_MAPS_KEY'),
        // Yang dilihat warga biasa di peta/denah untuk rumah selain rumahnya sendiri:
        //   nama    = titik + nama & foto penghuni (default)
        //   titik   = titik rumah saja, tanpa nama/foto
        //   sendiri = hanya rumahnya sendiri yang tampil di peta
        'warga' => env('SIWARGA_PETA_WARGA', 'nama'),
        // Posisi awal peta sebelum pengurus RW menyimpan posisi wilayah (default: seluruh Indonesia)
        'lat' => (float) env('SIWARGA_PETA_LAT', -2.5),
        'lng' => (float) env('SIWARGA_PETA_LNG', 118.0),
        'zoom' => (int) env('SIWARGA_PETA_ZOOM', 5),
    ],

    // Tanggal jatuh tempo iuran setiap bulan
    'jatuh_tempo_tanggal' => (int) env('SIWARGA_JATUH_TEMPO', 10),

];
