<?php

use Illuminate\Support\Facades\Schedule;

// Buat tagihan iuran setiap tanggal 1 pukul 00:05
Schedule::command('iuran:generate')->monthlyOn(1, '00:05')->withoutOverlapping();

// Cocokkan status transaksi QRIS yang masih menunggu (cadangan bila callback gagal)
Schedule::command('pembayaran:sinkron')->everyFiveMinutes()->withoutOverlapping();
