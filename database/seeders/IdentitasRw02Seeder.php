<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Models\Pengumuman;
use App\Models\Rt;
use App\Models\TarifIuran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Identitas RW 02 Dusun Sidorejo dan RT 03, 04, 05.
 *
 * - Mengisi Pengaturan: nama RW, dusun, kelurahan, kecamatan, kabupaten, provinsi
 *   (selanjutnya bisa diubah di menu Pengaturan).
 * - Bila RT yang ada masih RT 01–03 (data contoh lama), dinomori ulang menjadi RT 03–05
 *   beserta blok, rumah, titik peta dan warganya. Bila tidak, RT 03–05 yang belum ada dibuat.
 */
class IdentitasRw02Seeder extends Seeder
{
    public const RT = ['03', '04', '05'];

    public function run(): void
    {
        Pengaturan::simpan([
            'nama_rw' => 'RW 02',
            'dusun' => 'Sidorejo',
            'kelurahan' => 'Selomartani',
            'kecamatan' => 'Kalasan',
            'kabupaten' => 'Sleman',
            'provinsi' => 'D.I. Yogyakarta',
            'kota' => null,
        ]);

        $nomor = Rt::query()->orderBy('nomor')->pluck('nomor')->all();

        if (array_diff(self::RT, $nomor) === []) {
            // RT 03–05 sudah ada
        } elseif ($nomor === ['01', '02', '03']) {
            DB::transaction(function () {
                // dari belakang supaya nomor unik tidak bentrok: 03→05, 02→04, 01→03
                foreach (['03' => '05', '02' => '04', '01' => '03'] as $lama => $baru) {
                    Rt::query()->where('nomor', $lama)->update(['nomor' => $baru]);
                }
            });
            $this->command?->info('RT 01, 02, 03 dinomori ulang menjadi RT 03, 04, 05.');
        } else {
            foreach (self::RT as $n) {
                Rt::query()->firstOrCreate(['nomor' => $n], ['warna' => Rt::warnaBerikutnya()]);
            }
            $this->command?->warn('RT lain yang sudah ada tidak diubah: '.implode(', ', array_diff($nomor, self::RT)).'. Hapus/ubah lewat menu RT, Blok & Rumah bila perlu.');
        }

        // Warna RT 03 merah, RT 04 biru, RT 05 kuning (hanya bila warnanya belum diubah manual)
        $bawaan = array_merge(Rt::PALET, ['#0f766e', '#1d4ed8', '#b45309']);
        foreach (self::RT as $i => $n) {
            $rt = Rt::query()->where('nomor', $n)->first();
            if ($rt && (! $rt->warna || in_array(strtolower($rt->warna), $bawaan, true))) {
                $rt->update(['warna' => Rt::PALET[$i]]);
            }
        }

        // Rapikan label data contoh lama yang menyebut nomor RT
        foreach (Rt::query()->get() as $rt) {
            TarifIuran::query()->where('rt_id', $rt->id)->where('nama', 'like', 'Kas RT %')->update(['nama' => 'Kas RT '.$rt->nomor]);

            foreach (User::query()->where('rt_id', $rt->id)->where('email', 'like', 'ketua.rt%@rw.local')->get() as $u) {
                $email = 'ketua.rt'.$rt->nomor.'@rw.local';
                if ($u->email !== $email && ! User::query()->where('email', $email)->exists()) {
                    $u->update(['email' => $email]);
                    $this->command?->info("Akun pengurus RT {$rt->nomor} sekarang: {$email}");
                }
            }

            Pengumuman::query()->where('rt_id', $rt->id)->where('judul', 'Jadwal ronda bulan ini')
                ->get()->each(fn ($p) => $p->update(['isi' => preg_replace('/RT \d{2}/', 'RT '.$rt->nomor, $p->isi)]));
        }
    }
}
