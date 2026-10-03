<?php

namespace Database\Seeders;

use App\Models\Donasi;
use App\Models\KartuKeluarga;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contoh penggalangan dana "Renovasi Pos Kamling" beserta beberapa donatur.
 * Aman dijalankan berulang kali:  php artisan db:seed --class=DonasiContohSeeder
 */
class DonasiContohSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        $donasi = Donasi::query()->firstOrCreate(['judul' => 'Renovasi Pos Kamling'], [
            'user_id' => $admin?->id,
            'ringkasan' => 'Perbaikan atap bocor, pengecatan ulang, dan pemasangan lampu sorot di pos kamling utama.',
            'deskripsi' => "Pos kamling utama sudah berusia lebih dari 10 tahun. Atapnya bocor saat hujan, catnya mengelupas, dan penerangan di sekitarnya kurang sehingga petugas ronda kesulitan memantau jalan.\n\nDana yang terkumpul akan dipakai untuk:\n• Mengganti atap seng dengan genteng metal\n• Mengecat ulang dinding dan bangku\n• Memasang 2 lampu sorot LED tenaga surya\n• Membeli kentongan dan senter baru\n\nLaporan penggunaan dana akan diumumkan setelah renovasi selesai. Terima kasih atas kepedulian Bapak/Ibu.",
            'target' => 7500000,
            'mulai' => now()->subDays(14)->toDateString(),
            'selesai' => now()->addDays(30)->toDateString(),
            'cara_donasi' => "1. Tunai melalui Bendahara RW (rumah Blok A-1)\n2. Transfer ke rekening kas RW: BRI 0000-01-000000-00-0 a.n. Kas RW, lalu konfirmasi ke bendahara agar nama Anda tercatat.\n\nCentang \"sembunyikan nama\" jika ingin donasi Anda tercatat sebagai anonim.",
            'tampilkan_total' => true,
            'publik' => true,
            'aktif' => true,
        ]);

        if ($donasi->donaturs()->exists()) {
            return;
        }

        $kks = KartuKeluarga::query()->aktif()->inRandomOrder()->limit(6)->get();
        $contoh = [
            [500000, false], [250000, false], [1000000, true], [150000, false],
            [300000, false], [200000, true], [750000, false], [100000, false],
        ];

        foreach ($contoh as $i => [$nominal, $anonim]) {
            $kk = $kks[$i] ?? null;
            $donasi->donaturs()->create([
                'nama' => $kk ? 'Kel. '.$kk->nama_kepala : ['Toko Berkah Jaya', 'Warung Bu Siti'][$i % 2],
                'kartu_keluarga_id' => $kk?->id,
                'anonim' => $anonim,
                'nominal' => $nominal,
                'tanggal' => now()->subDays(14 - $i)->toDateString(),
                'dicatat_oleh' => $admin?->id,
            ]);
        }
    }
}
