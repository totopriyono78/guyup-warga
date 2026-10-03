<?php

use App\Http\Controllers\AinoNotifyController;
use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlokController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DenahController;
use App\Http\Controllers\DonasiController;
use App\Http\Controllers\DonasiQrisController;
use App\Http\Controllers\GaleriController;
use App\Http\Controllers\GalangDanaController;
use App\Http\Controllers\IuranController;
use App\Http\Controllers\KeluargaController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PencarianController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PetaController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PublikController;
use App\Http\Controllers\RtController;
use App\Http\Controllers\RumahController;
use App\Http\Controllers\TarifIuranController;
use Illuminate\Support\Facades\Route;

/*
| Callback AINO (Finish Notify) — server ke server, tanpa login & tanpa CSRF.
*/
Route::post('aino/notify', AinoNotifyController::class)->name('aino.notify');

/*
| Halaman umum (tanpa login): peta wilayah per RT, info umum, penggalangan dana.
| Tidak menampilkan data pribadi warga.
*/
Route::get('/', [PublikController::class, 'index'])->name('publik');
Route::get('info/{pengumuman}', [PublikController::class, 'pengumuman'])->name('publik.pengumuman')->whereNumber('pengumuman');
Route::get('galang-dana/{donasi}', [PublikController::class, 'donasi'])->name('publik.donasi')->whereNumber('donasi');
Route::post('galang-dana/{donasi}/qris', [DonasiQrisController::class, 'store'])->name('publik.donasi.qris')->whereNumber('donasi')->middleware('throttle:6,1');
Route::get('galang-dana/bayar/{pembayaran}', [DonasiQrisController::class, 'show'])->name('publik.donasi.bayar')->whereUuid('pembayaran');
Route::get('galang-dana/bayar/{pembayaran}/status', [DonasiQrisController::class, 'status'])->name('publik.donasi.status')->whereUuid('pembayaran')->middleware('throttle:30,1');
Route::get('kegiatan', [PublikController::class, 'galeri'])->name('publik.galeri');
Route::get('kegiatan/{galeri}', [PublikController::class, 'galeriAlbum'])->name('publik.galeri.show')->whereNumber('galeri');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // 1. Denah wilayah
    Route::get('denah', [DenahController::class, 'index'])->name('denah');

    // 2. Data warga
    Route::resource('keluarga', KeluargaController::class)->parameters(['keluarga' => 'keluarga']);
    Route::get('keluarga/{keluarga}/anggota/create', [AnggotaController::class, 'create'])->name('anggota.create');
    Route::post('keluarga/{keluarga}/anggota', [AnggotaController::class, 'store'])->name('anggota.store');
    Route::get('anggota/{anggota}/edit', [AnggotaController::class, 'edit'])->name('anggota.edit');
    Route::put('anggota/{anggota}', [AnggotaController::class, 'update'])->name('anggota.update');
    Route::delete('anggota/{anggota}', [AnggotaController::class, 'destroy'])->name('anggota.destroy');

    // 3. Pencarian
    Route::get('cari', PencarianController::class)->name('cari');

    // 4. Pengumuman
    Route::resource('pengumuman', PengumumanController::class)->parameters(['pengumuman' => 'pengumuman']);

    // 5. Iuran & pembayaran QRIS
    Route::get('iuran/saya', [IuranController::class, 'saya'])->name('iuran.saya');
    Route::post('pembayaran', [PembayaranController::class, 'store'])->name('pembayaran.store')->middleware('throttle:10,1');
    Route::get('pembayaran/{pembayaran}', [PembayaranController::class, 'show'])->name('pembayaran.show')->whereUuid('pembayaran');
    Route::get('pembayaran/{pembayaran}/status', [PembayaranController::class, 'status'])->name('pembayaran.status')->middleware('throttle:30,1')->whereUuid('pembayaran');

    // Galang dana untuk warga: daftar program, donasi QRIS, riwayat donasi sendiri
    Route::get('galang', [GalangDanaController::class, 'index'])->name('galang.index');
    Route::get('galang/{donasi}', [GalangDanaController::class, 'show'])->name('galang.show')->whereNumber('donasi');

    // Galeri foto kegiatan (lihat: semua warga)
    Route::get('galeri', [GaleriController::class, 'index'])->name('galeri.index');
    Route::get('galeri/{galeri}', [GaleriController::class, 'show'])->name('galeri.show')->whereNumber('galeri');

    Route::get('profil', [ProfilController::class, 'edit'])->name('profil');
    Route::put('profil', [ProfilController::class, 'update'])->name('profil.update');

    /*
    | Khusus pengurus (RW & RT)
    */
    Route::middleware('role:admin,rt')->group(function () {
        Route::get('wilayah', [RtController::class, 'index'])->name('wilayah');
        Route::get('wilayah/blok/{blok}', [BlokController::class, 'show'])->name('blok.show');
        Route::post('wilayah/blok', [BlokController::class, 'store'])->name('blok.store');
        Route::put('wilayah/blok/{blok}', [BlokController::class, 'update'])->name('blok.update');
        Route::delete('wilayah/blok/{blok}', [BlokController::class, 'destroy'])->name('blok.destroy');
        Route::post('wilayah/blok/{blok}/rumah', [RumahController::class, 'store'])->name('rumah.store');
        Route::post('wilayah/blok/{blok}/rumah-massal', [RumahController::class, 'storeMassal'])->name('rumah.massal');
        Route::post('wilayah/blok/{blok}/susun', [RumahController::class, 'susun'])->name('blok.susun');
        Route::put('wilayah/rumah/{rumah}', [RumahController::class, 'update'])->name('rumah.update');
        Route::put('wilayah/rumah/{rumah}/lokasi', [RumahController::class, 'lokasi'])->name('rumah.lokasi');
        Route::get('wilayah/peta', [PetaController::class, 'edit'])->name('peta.edit');
        Route::post('wilayah/peta/rumah', [RumahController::class, 'storeDiPeta'])->name('rumah.peta.store');
        Route::delete('wilayah/rumah/{rumah}', [RumahController::class, 'destroy'])->name('rumah.destroy');

        Route::get('iuran', [IuranController::class, 'index'])->name('iuran.index');
        Route::post('iuran/generate', [IuranController::class, 'generate'])->name('iuran.generate');
        Route::post('iuran/tagihan/{tagihan}/lunas', [IuranController::class, 'tandaiLunas'])->name('iuran.lunas');
        Route::post('iuran/tagihan/{tagihan}/batal', [IuranController::class, 'batalLunas'])->name('iuran.batal');
        Route::get('iuran/export', [IuranController::class, 'export'])->name('iuran.export');
        Route::get('iuran/transaksi', [IuranController::class, 'transaksi'])->name('iuran.transaksi');
        Route::post('iuran/transaksi/{pembayaran}/cek', [PembayaranController::class, 'cek'])->name('pembayaran.cek')->whereUuid('pembayaran');

        Route::get('iuran/tarif', [TarifIuranController::class, 'index'])->name('tarif.index');
        Route::post('iuran/tarif', [TarifIuranController::class, 'store'])->name('tarif.store');
        Route::put('iuran/tarif/{tarif}', [TarifIuranController::class, 'update'])->name('tarif.update');
        Route::delete('iuran/tarif/{tarif}', [TarifIuranController::class, 'destroy'])->name('tarif.destroy');
        Route::post('iuran/tarif/{tarif}/terbitkan', [TarifIuranController::class, 'terbitkan'])->name('tarif.terbitkan');

        // Galeri: kelola album & foto
        Route::resource('galeri', GaleriController::class)->except(['index', 'show'])->parameters(['galeri' => 'galeri']);
        Route::post('galeri/{galeri}/foto', [GaleriController::class, 'tambahFoto'])->name('galeri.foto.store');
        Route::put('galeri-foto/{foto}', [GaleriController::class, 'ubahFoto'])->name('galeri.foto.update');
        Route::delete('galeri-foto/{foto}', [GaleriController::class, 'hapusFoto'])->name('galeri.foto.destroy');

        // Penggalangan dana / donasi
        Route::resource('donasi', DonasiController::class)->parameters(['donasi' => 'donasi']);
        Route::post('donasi/{donasi}/donatur', [DonasiController::class, 'tambahDonatur'])->name('donasi.donatur.store');
        Route::delete('donasi-donatur/{donatur}', [DonasiController::class, 'hapusDonatur'])->name('donasi.donatur.destroy');
        Route::post('donasi-qris/{pembayaran}/cek', [DonasiController::class, 'cekQris'])->name('donasi.qris.cek')->whereUuid('pembayaran');
        Route::post('donasi-qris/{pembayaran}/berhasil', [DonasiController::class, 'tandaiQrisBerhasil'])->name('donasi.qris.berhasil')->whereUuid('pembayaran');

        Route::resource('pengguna', PenggunaController::class)->except('show')->parameters(['pengguna' => 'pengguna']);
    });

    /*
    | Khusus pengurus RW
    */
    Route::middleware('role:admin')->group(function () {
        Route::post('wilayah/rt', [RtController::class, 'store'])->name('rt.store');
        Route::put('wilayah/rt/{rt}', [RtController::class, 'update'])->name('rt.update');
        Route::delete('wilayah/rt/{rt}', [RtController::class, 'destroy'])->name('rt.destroy');

        Route::get('pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan');
        Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
        Route::put('wilayah/peta/awal', [PetaController::class, 'simpanAwal'])->name('peta.awal');
    });
});
