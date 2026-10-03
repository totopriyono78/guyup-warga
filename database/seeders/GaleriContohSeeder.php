<?php

namespace Database\Seeders;

use App\Models\Galeri;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Contoh album galeri kegiatan. Gambarnya ilustrasi (bukan foto asli) dari
 * public/img/galeri-contoh. Aman dijalankan berulang kali.
 * Ganti/hapus album contoh lewat menu Galeri Kegiatan setelah punya foto asli.
 */
class GaleriContohSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();
        $rt04 = Rt::query()->where('nomor', '04')->value('id');
        $tahun = (int) now()->year;
        $tujuhbelas = Carbon::create(now()->month >= 8 ? $tahun : $tahun - 1, 8, 17);

        $album = [
            'kerja-bakti' => [
                'judul' => 'Kerja Bakti Bersih Saluran Irigasi',
                'tanggal' => now()->subWeeks(3)->previous(Carbon::SUNDAY),
                'lokasi' => 'Sepanjang jalan dusun & saluran irigasi',
                'deskripsi' => 'Warga RT 03, 04, dan 05 bergotong royong membersihkan selokan dan saluran irigasi menjelang musim hujan. Terima kasih kepada ibu-ibu PKK yang menyiapkan konsumsi.',
                'ket' => ['Membersihkan tepi jalan dusun', 'Spanduk kerja bakti RW 02', 'Memangkas rumput di pinggir sawah', 'Sampah diangkut ke tempat pembuangan'],
            ],
            'minggu-pahing' => [
                'judul' => 'Pertemuan Rutin Minggu Pahing',
                'tanggal' => now()->subWeeks(5),
                'lokasi' => 'Balai Dusun',
                'deskripsi' => 'Pertemuan selapanan warga: laporan kas RW, evaluasi ronda, dan rencana merti dusun.',
                'ket' => ['Pembukaan oleh Ketua RW', 'Penyampaian laporan kas', 'Sesi tanya jawab warga'],
            ],
            'posyandu' => [
                'judul' => 'Posyandu Balita & Lansia',
                'tanggal' => now()->subWeeks(2),
                'lokasi' => 'Rumah kader RT 04',
                'rt_id' => $rt04,
                'deskripsi' => 'Penimbangan balita, imunisasi, dan cek kesehatan lansia bersama kader posyandu.',
                'ket' => ['Pendaftaran & penimbangan', 'Antrean ibu dan balita', 'Pemeriksaan kesehatan lansia'],
            ],
            'hut-ri' => [
                'judul' => 'Lomba HUT RI ke-'.($tujuhbelas->year - 1945),
                'tanggal' => $tujuhbelas,
                'lokasi' => 'Lapangan dusun',
                'deskripsi' => 'Peringatan Hari Kemerdekaan dengan upacara sederhana dan aneka lomba untuk anak-anak hingga bapak-bapak.',
                'ket' => ['Panjat pinang', 'Balap karung', 'Upacara pengibaran bendera', 'Lomba makan kerupuk'],
            ],
            'merti-dusun' => [
                'judul' => 'Merti Dusun Sidorejo',
                'tanggal' => now()->subYear()->startOfMonth()->next(Carbon::SUNDAY),
                'lokasi' => 'Balai Dusun',
                'deskripsi' => 'Ungkapan syukur atas hasil panen: kirab gunungan hasil bumi dan kenduri bersama seluruh warga.',
                'ket' => ['Gunungan hasil bumi', 'Kirab budaya keliling dusun', 'Kenduri bersama'],
            ],
        ];

        foreach ($album as $slug => $a) {
            $g = Galeri::query()->firstOrCreate(['judul' => $a['judul']], [
                'user_id' => $admin?->id,
                'rt_id' => $a['rt_id'] ?? null,
                'tanggal' => $a['tanggal']->toDateString(),
                'lokasi' => $a['lokasi'],
                'deskripsi' => $a['deskripsi'],
                'publik' => true,
            ]);

            if ($g->fotos()->exists()) {
                continue;
            }

            foreach ($a['ket'] as $i => $ket) {
                // Gambar contoh ikut dalam aplikasi (public/img/galeri-contoh), tidak disalin ke storage,
                // sehingga tetap tampil di hosting yang storage-nya terpisah (mis. volume Railway).
                $path = "img/galeri-contoh/{$slug}-".($i + 1).'.jpg';
                if (! is_file(public_path($path))) {
                    continue;
                }
                $g->fotos()->create(['path' => $path, 'keterangan' => $ket, 'urutan' => $i + 1]);
            }
        }
    }
}
