<?php

/*
|--------------------------------------------------------------------------
| AINO Payment Gateway (QRIS MPM / Virtual Account)
|--------------------------------------------------------------------------
|
| Dokumentasi: "API INTEGRATION - QRIS MPM or VA" v1.0.0 (Feb 2026)
|  - Generate QRIS/VA : POST {base_url}/payment/v1/request
|  - Query Payment    : POST {base_url}/payment/v1/inquiry
|  - Finish Notify    : AINO -> POST {APP_URL}/aino/notify
|
| Autentikasi: Basic base64(merchant_code:secret_key)
|
*/

return [

    'merchant_code' => env('AINO_MERCHANT_CODE'),

    'secret_key' => env('AINO_SECRET_KEY'),

    // Development: https://svc-core-go-dev.ainosi.com
    // Production : https://apg.ainosi.com
    'base_url' => rtrim((string) env('AINO_BASE_URL', 'https://svc-core-go-dev.ainosi.com'), '/'),

    'generate_path' => env('AINO_GENERATE_PATH', '/payment/v1/request'),

    // Dokumen menyebut /payment/v1/inquiry (contoh request menulis /payment/v1/status).
    // Ubah lewat .env bila AINO menginstruksikan path lain.
    'inquiry_path' => env('AINO_INQUIRY_PATH', '/payment/v1/inquiry'),

    // Nilai payment_type: qr | va_mandiri | va_bni | va_bca | va_bri
    'payment_type' => env('AINO_PAYMENT_TYPE', 'qr'),

    // URL callback (wajib HTTPS). Kosongkan untuk memakai APP_URL/aino/notify
    'callback_url' => env('AINO_CALLBACK_URL'),

    'timeout' => (int) env('AINO_TIMEOUT', 8),

    // Biaya layanan yang dibebankan ke warga (persen dari total iuran, 0 = ditanggung kas)
    'biaya_persen' => (float) env('AINO_BIAYA_PERSEN', 0),

    // Nominal minimal donasi lewat QRIS (rupiah)
    'donasi_minimal' => (int) env('AINO_DONASI_MINIMAL', 10000),

    // Mode uji coba: menampilkan pilihan nominal Rp1 di formulir donasi (matikan setelah selesai uji)
    'donasi_uji' => (bool) env('AINO_DONASI_UJI', false),

    // Batas waktu QR bila AINO tidak mengirim expiryDate (menit)
    // Opsional: hanya terima callback dari IP ini (pisahkan dengan koma). Kosong = semua IP.
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('AINO_ALLOWED_IPS', ''))))),

    'default_expiry_minutes' => (int) env('AINO_DEFAULT_EXPIRY', 15),

];
