<?php

namespace Database\Seeders;

use App\Models\Pengumuman;
use App\Models\User;
use App\Support\Pasaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Contoh pengumuman untuk seluruh warga RW 02 (pertemuan Minggu Pahing, kerja bakti, posyandu, dll).
 * Tanggal kegiatan dihitung dari hari ini, termasuk hari pasarannya. Aman dijalankan berulang kali.
 */
class PengumumanContohSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();
        $rw = pengaturan('nama_rw', 'RW 02');
        $dusun = pengaturan('dusun') ? 'Dusun '.pengaturan('dusun') : 'dusun';

        $tgl = fn (Carbon $t) => Pasaran::hariPasaran($t).', '.$t->translatedFormat('j F Y');

        $pahing = Pasaran::berikutnya('Minggu', 'Pahing', now()->addDay());
        $minggu = now()->next(Carbon::SUNDAY);
        $kerjaBakti = $minggu->isSameDay($pahing) ? $minggu->copy()->addWeek() : $minggu;
        $posyandu = now()->addDays(((5 - now()->dayOfWeek) + 7) % 7 ?: 7); // Jumat terdekat
        $psn = now()->next(Carbon::SATURDAY);
        $merti = now()->addWeeks(5)->next(Carbon::SUNDAY);

        $data = [
            [
                'judul' => 'Pertemuan Rutin Warga Minggu Pahing',
                'penting' => true, 'publik' => true, 'terbit' => now()->subDays(2),
                'isi' => "Assalamu'alaikum wr. wb. / Salam sejahtera,\n\nDengan hormat, mengundang Bapak/Ibu kepala keluarga warga {$rw} {$dusun} untuk hadir dalam pertemuan rutin selapanan:\n\nHari/tanggal : {$tgl($pahing)}\nWaktu        : 19.30 WIB s.d. selesai\nTempat       : Balai Dusun\n\nAcara:\n1. Pembukaan\n2. Laporan kas RW dan iuran bulan lalu\n3. Persiapan kerja bakti & merti dusun\n4. Informasi dari kalurahan\n5. Lain-lain\n\nMohon hadir tepat waktu. Bagi yang berhalangan dapat diwakilkan anggota keluarga. Matur nuwun.",
            ],
            [
                'judul' => 'Kerja Bakti Bersih Lingkungan & Saluran Irigasi',
                'penting' => true, 'publik' => true, 'terbit' => now()->subDay(),
                'isi' => "Menjelang musim hujan, seluruh warga RT 03, RT 04, dan RT 05 diajak gotong royong membersihkan lingkungan.\n\nHari/tanggal : {$tgl($kerjaBakti)}\nPukul        : 07.00 WIB s.d. selesai\nKumpul       : Pos ronda masing-masing RT\n\nKegiatan:\n• Membersihkan saluran irigasi dan selokan\n• Memangkas ranting pohon di tepi jalan\n• Membersihkan makam dusun\n\nMohon membawa cangkul, sabit, sapu lidi, atau karung. Konsumsi disiapkan ibu-ibu PKK.",
            ],
            [
                'judul' => 'Posyandu Balita & Lansia Bulan Ini',
                'penting' => false, 'publik' => true, 'terbit' => now()->subDays(3),
                'isi' => "Posyandu balita dan posyandu lansia {$dusun} dilaksanakan pada:\n\nHari/tanggal : {$tgl($posyandu)}\nPukul        : 08.00 – 11.00 WIB\nTempat       : Rumah Ibu Kader RT 04\n\nLayanan: penimbangan dan pengukuran balita, imunisasi, vitamin A, cek tekanan darah, gula darah, dan asam urat untuk lansia.\n\nJangan lupa membawa buku KIA / KMS.",
            ],
            [
                'judul' => 'Jadwal Ronda Malam & Jimpitan',
                'penting' => false, 'publik' => false, 'terbit' => now()->subDays(6),
                'isi' => "Jadwal ronda malam tiap RT sudah ditempel di pos ronda masing-masing. Ronda dimulai pukul 22.00 WIB.\n\nJimpitan tetap berjalan: mohon menyiapkan beras atau uang Rp500 di tempat jimpitan depan rumah setiap malam. Hasil jimpitan digunakan untuk kas sosial warga (santunan sakit dan lelayu).\n\nWarga yang berhalangan ronda dimohon bertukar jadwal dan memberi tahu ketua RT.",
            ],
            [
                'judul' => 'Pemberantasan Sarang Nyamuk (PSN) Serentak',
                'penting' => false, 'publik' => true, 'terbit' => now()->subDays(4),
                'isi' => "Mencegah demam berdarah, PSN serentak dilaksanakan {$tgl($psn)} pagi.\n\nLakukan 3M Plus di rumah masing-masing:\n1. Menguras bak mandi dan tempat penampungan air\n2. Menutup rapat tempat penampungan air\n3. Mendaur ulang/mengubur barang bekas\n\nKader jumantik akan berkeliling memeriksa jentik. Mohon kerja samanya.",
            ],
            [
                'judul' => 'Rencana Merti Dusun',
                'penting' => false, 'publik' => true, 'terbit' => now()->subHours(10),
                'isi' => "Sebagai ungkapan syukur atas hasil panen, {$dusun} akan menggelar Merti Dusun pada {$tgl($merti)}.\n\nRangkaian acara:\n• Kirab gunungan hasil bumi\n• Kenduri bersama di Balai Dusun\n• Pentas jathilan dan karawitan anak\n\nPembentukan panitia akan dibahas pada pertemuan Minggu Pahing. Warga yang ingin menyumbang hasil bumi atau dana dapat menghubungi pengurus RT.",
            ],
            [
                'judul' => 'Arisan & Pertemuan PKK',
                'penting' => false, 'publik' => false, 'terbit' => now()->subDays(5),
                'isi' => "Diberitahukan kepada ibu-ibu anggota PKK {$rw}, arisan dan pertemuan rutin diadakan tanggal 15 bulan ini pukul 15.30 WIB di rumah Ibu Ketua PKK.\n\nMateri: pemanfaatan pekarangan untuk tanaman obat keluarga (TOGA). Mohon membawa bibit tanaman bila ada.",
            ],
            [
                'judul' => 'Pembayaran PBB Tahun Ini',
                'penting' => false, 'publik' => false, 'terbit' => now()->subDays(8),
                'isi' => "SPPT PBB tahun ini sudah dibagikan melalui ketua RT. Pembayaran dapat dititipkan kepada petugas pemungut di masing-masing RT paling lambat akhir bulan depan, atau dibayar mandiri melalui bank/aplikasi.\n\nBila SPPT belum diterima atau terdapat kesalahan data, silakan lapor ke ketua RT.",
            ],
        ];

        foreach ($data as $d) {
            Pengumuman::query()->firstOrCreate(['judul' => $d['judul']], [
                'user_id' => $admin?->id,
                'rt_id' => null,
                'isi' => $d['isi'],
                'penting' => $d['penting'],
                'publik' => $d['publik'],
                'terbit_pada' => $d['terbit'],
            ]);
        }
    }
}
