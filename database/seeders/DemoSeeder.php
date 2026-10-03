<?php

namespace Database\Seeders;

use App\Models\AnggotaKeluarga;
use App\Models\Blok;
use App\Models\KartuKeluarga;
use App\Models\Pengumuman;
use App\Models\Rt;
use App\Models\Rumah;
use App\Models\TarifIuran;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba aplikasi (RW 02 Dusun Sidorejo): RT 03, 04, 05, beberapa blok, rumah, KK, anggota,
 * tarif iuran, tagihan 3 bulan terakhir, dan pengumuman.
 * Akun contoh (password: password):
 *   ketua.rt03@rw.local (pengurus RT 03), warga@rw.local (warga)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('id_ID');
        $warna = \App\Models\Rt::PALET;
        $struktur = ['03' => ['A', 'B'], '04' => ['C', 'D'], '05' => ['E']];

        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        $i = 0;

        foreach ($struktur as $nomorRt => $bloks) {
            $rt = Rt::query()->firstOrCreate(['nomor' => $nomorRt], [
                'nama_ketua' => $faker->name('male'),
                'no_hp_ketua' => '08'.$faker->numerify('##########'),
                'warna' => $warna[$i++ % count($warna)],
            ]);

            foreach ($bloks as $u => $namaBlok) {
                $blok = Blok::query()->firstOrCreate(['rt_id' => $rt->id, 'nama' => $namaBlok], ['urutan' => $u]);

                // 2 baris saling berhadapan, masing-masing 8 rumah
                for ($n = 1; $n <= 16; $n++) {
                    $rumah = Rumah::query()->firstOrCreate(['blok_id' => $blok->id, 'nomor' => (string) $n], [
                        'baris' => $n <= 8 ? 1 : 2,
                        'kolom' => $n <= 8 ? $n : $n - 8,
                        'status_hunian' => $faker->randomElement(['dihuni', 'dihuni', 'dihuni', 'dihuni', 'kontrakan', 'kosong']),
                    ]);

                    if ($rumah->status_hunian === 'kosong' || $rumah->kartuKeluargas()->exists()) {
                        continue;
                    }

                    $this->buatKeluarga($rumah, $faker);
                }
            }
        }

        $rt01 = Rt::query()->where('nomor', '03')->first(); // RT pertama di RW 02

        TarifIuran::query()->firstOrCreate(['nama' => 'Iuran Keamanan', 'rt_id' => null], ['nominal' => 30000, 'frekuensi' => 'bulanan']);
        TarifIuran::query()->firstOrCreate(['nama' => 'Iuran Kebersihan', 'rt_id' => null], ['nominal' => 20000, 'frekuensi' => 'bulanan']);
        TarifIuran::query()->firstOrCreate(['nama' => 'Iuran Sosial', 'rt_id' => null], ['nominal' => 10000, 'frekuensi' => 'bulanan', 'keterangan' => 'Santunan warga sakit / berduka']);
        TarifIuran::query()->firstOrCreate(['nama' => 'Kas RT 03', 'rt_id' => $rt01->id], ['nominal' => 10000, 'frekuensi' => 'bulanan']);
        TarifIuran::query()->firstOrCreate(['nama' => 'Iuran Tahunan Lingkungan', 'rt_id' => null], [
            'nominal' => 100000, 'frekuensi' => 'tahunan', 'bulan_tagih' => (int) now()->month,
            'keterangan' => 'Perawatan taman, lampu jalan, dan saluran air',
        ]);
        $acara = TarifIuran::query()->firstOrCreate(['nama' => 'Sumbangan HUT RI', 'rt_id' => null], [
            'nominal' => 25000, 'frekuensi' => 'insidental', 'sukarela' => true, 'tenggat' => now()->addWeeks(3)->toDateString(),
            'keterangan' => 'Lomba anak & tasyakuran 17 Agustus — sumbangan sukarela, minimal Rp25.000',
        ]);

        User::query()->firstOrCreate(['email' => 'ketua.rt03@rw.local'], [
            'name' => $rt01->nama_ketua, 'password' => 'password', 'role' => User::ROLE_RT, 'rt_id' => $rt01->id,
        ]);

        $kkWarga = KartuKeluarga::query()->diRt($rt01->id)->first();
        User::query()->firstOrCreate(['email' => 'warga@rw.local'], [
            'name' => $kkWarga->nama_kepala, 'password' => 'password', 'role' => User::ROLE_WARGA, 'kartu_keluarga_id' => $kkWarga->id,
        ]);

        $service = app(TagihanService::class);
        foreach ([2, 1, 0] as $mundur) {
            $periode = now()->startOfMonth()->subMonthsNoOverflow($mundur);
            $service->generate($periode);
        }
        $service->terbitkan($acara);

        // Tandai sebagian besar tagihan lama sebagai lunas agar rekap terlihat realistis
        \App\Models\Tagihan::query()
            ->where('periode', '<', now()->startOfMonth()->toDateString())
            ->inRandomOrder()->limit((int) (\App\Models\Tagihan::query()->count() * 0.5))
            ->get()->each(fn ($t) => $t->update([
                'status' => 'lunas', 'metode' => 'tunai',
                'nominal_dibayar' => $t->sukarela ? max($t->nominal, 50000) : $t->nominal,
                'dibayar_pada' => $t->periode->copy()->addDays(rand(1, 20)),
            ]));

        Pengumuman::query()->firstOrCreate(['judul' => 'Jadwal ronda bulan ini'], [
            'user_id' => $admin?->id,
            'rt_id' => $rt01->id,
            'isi' => "Jadwal ronda RT 03 bulan ini sudah ditempel di pos kamling. Warga yang berhalangan harap menukar jadwal dengan tetangga dan mengabari ketua RT.",
            'terbit_pada' => now()->subHours(5),
        ]);

        // identitas RW 02, warga tambahan + titik peta, pengumuman, galeri, donasi
        $this->call(ContohRw02Seeder::class);
    }

    private function buatKeluarga(Rumah $rumah, $faker): void
    {
        $namaKepala = $faker->firstNameMale().' '.$faker->lastName();

        $kk = KartuKeluarga::query()->create([
            'rumah_id' => $rumah->id,
            'no_kk' => $faker->unique()->numerify('3201############'),
            'nama_kepala' => $namaKepala,
            'status_tinggal' => $rumah->status_hunian === 'kontrakan' ? 'kontrak' : 'tetap',
            'no_hp' => '08'.$faker->numerify('##########'),
            'tanggal_masuk' => $faker->dateTimeBetween('-15 years', '-2 months'),
        ]);

        AnggotaKeluarga::query()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => $faker->unique()->numerify('3201############'),
            'nama' => $namaKepala, 'jenis_kelamin' => 'L', 'hubungan' => 'Kepala Keluarga',
            'tempat_lahir' => $faker->city(), 'tanggal_lahir' => $faker->dateTimeBetween('-60 years', '-28 years'),
            'agama' => 'Islam', 'status_perkawinan' => 'Kawin', 'pekerjaan' => $faker->jobTitle(),
            'pendidikan' => 'Diploma IV/S1', 'no_hp' => $kk->no_hp,
        ]);

        AnggotaKeluarga::query()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => $faker->unique()->numerify('3201############'),
            'nama' => $faker->firstNameFemale().' '.$faker->lastName(), 'jenis_kelamin' => 'P', 'hubungan' => 'Istri',
            'tempat_lahir' => $faker->city(), 'tanggal_lahir' => $faker->dateTimeBetween('-55 years', '-25 years'),
            'agama' => 'Islam', 'status_perkawinan' => 'Kawin', 'pekerjaan' => 'Mengurus Rumah Tangga',
            'pendidikan' => 'SMA/Sederajat',
        ]);

        for ($a = 0; $a < rand(0, 3); $a++) {
            $jk = $faker->randomElement(['L', 'P']);
            AnggotaKeluarga::query()->create([
                'kartu_keluarga_id' => $kk->id,
                'nik' => $faker->unique()->numerify('3201############'),
                'nama' => ($jk === 'L' ? $faker->firstNameMale() : $faker->firstNameFemale()).' '.$faker->lastName(),
                'jenis_kelamin' => $jk, 'hubungan' => 'Anak', 'urutan' => $a + 1,
                'tempat_lahir' => $faker->city(), 'tanggal_lahir' => $faker->dateTimeBetween('-24 years', '-1 year'),
                'agama' => 'Islam', 'status_perkawinan' => 'Belum Kawin', 'pekerjaan' => 'Pelajar/Mahasiswa',
            ]);
        }
    }
}
