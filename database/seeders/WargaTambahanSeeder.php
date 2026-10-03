<?php

namespace Database\Seeders;

use App\Models\AnggotaKeluarga;
use App\Models\Blok;
use App\Models\KartuKeluarga;
use App\Models\Pengaturan;
use App\Models\Rt;
use App\Models\Rumah;
use App\Services\TagihanService;
use Illuminate\Database\Seeder;

/**
 * Contoh warga tambahan untuk RT 03, 04, 05: satu blok baru per RT (F, G, H) berisi 10 rumah
 * lengkap dengan titik peta. Titik ditempatkan di sekitar rumah-rumah yang sudah ada di peta
 * (di sisi luar kelompok RT masing-masing) supaya tidak menumpuk.
 * Aman dijalankan berulang kali.
 */
class WargaTambahanSeeder extends Seeder
{
    /** Pusat cadangan bila belum ada titik sama sekali: Selomartani, Kalasan, Sleman */
    private const PUSAT_CADANGAN = [-7.7318, 110.4426];

    private const BLOK = ['03' => 'F', '04' => 'G', '05' => 'H'];

    private const JARAK_RUMAH = 0.00013;   // ±14 m antar rumah

    private const JARAK_BARIS = 0.00024;   // ±27 m antar deret (jalan di tengah)

    private array $pria = ['Sutrisno', 'Suparman', 'Sugeng', 'Bambang', 'Wahyudi', 'Joko', 'Slamet', 'Harjono', 'Suwarno', 'Purwanto',
        'Agus', 'Teguh', 'Heru', 'Gunawan', 'Sumarno', 'Mujiono', 'Eko', 'Tri', 'Yanto', 'Wagiman', 'Marjuki', 'Sukardi', 'Ponijo', 'Darmanto', 'Supriyadi'];

    private array $belakang = ['Prasetyo', 'Santoso', 'Widodo', 'Raharjo', 'Susilo', 'Wibowo', 'Hartono', 'Nugroho', 'Setiawan', 'Kurniawan',
        'Saputro', 'Wijaya', 'Pranoto', 'Haryanto', 'Sasongko', 'Utomo', 'Hadi', 'Sudarsono', 'Purnomo', 'Wicaksono'];

    private array $wanita = ['Sri Lestari', 'Siti Aminah', 'Sumiyati', 'Ngatini', 'Suharni', 'Wahyuni', 'Endang Sulistyowati', 'Rini Astuti', 'Tutik Handayani',
        'Puji Astuti', 'Yuli Purwanti', 'Sulastri', 'Dwi Rahayu', 'Retno Wulandari', 'Partini', 'Sri Mulyani', 'Suwarni', 'Ninik Kustiyah', 'Tri Wahyuningsih', 'Marsinah'];

    private array $anakL = ['Dimas', 'Bagus', 'Aditya', 'Rizky', 'Galih', 'Bima', 'Arya', 'Danang', 'Fajar', 'Yoga', 'Satria', 'Raka'];

    private array $anakP = ['Ayu', 'Dewi', 'Putri', 'Laras', 'Sekar', 'Wulan', 'Intan', 'Anisa', 'Nanda', 'Rara', 'Kirana', 'Tiara'];

    private array $pekerjaan = ['Petani', 'Wiraswasta', 'Karyawan Swasta', 'PNS', 'Buruh Harian Lepas', 'Pedagang', 'Guru', 'Pensiunan', 'Perangkat Desa', 'Pengrajin', 'Sopir'];

    public function run(): void
    {
        $faker = fake('id_ID');
        mt_srand(2026); // hasil sama tiap dijalankan

        $semua = Rumah::query()->whereNotNull('lat')->whereNotNull('lng')
            ->join('bloks', 'bloks.id', '=', 'rumahs.blok_id')
            ->get(['rumahs.lat', 'rumahs.lng', 'bloks.rt_id'])
            ->map(fn ($r) => ['lat' => (float) $r->lat, 'lng' => (float) $r->lng, 'rt' => (int) $r->rt_id]);

        [$pusat, $radius] = $this->pusatDanRadius($semua);

        if ($semua->isEmpty() && ! Pengaturan::petaAwal()['tersimpan']) {
            Pengaturan::simpan(['peta_lat' => $pusat[0], 'peta_lng' => $pusat[1], 'peta_zoom' => 17]);
        }

        $terpakai = $semua->map(fn ($t) => [$t['lat'], $t['lng']])->all();
        $i = 0;

        foreach (self::BLOK as $nomorRt => $namaBlok) {
            $i++;
            $rt = Rt::query()->where('nomor', $nomorRt)->first();
            if (! $rt) {
                continue;
            }

            $blok = Blok::query()->firstOrCreate(['rt_id' => $rt->id, 'nama' => $namaBlok], [
                'urutan' => (int) Blok::query()->where('rt_id', $rt->id)->max('urutan') + 1,
                'keterangan' => 'Data contoh warga tambahan',
            ]);
            // Blok dengan nama sama yang dibuat sendiri oleh pengurus tidak disentuh
            if (! $blok->wasRecentlyCreated && $blok->keterangan !== 'Data contoh warga tambahan') {
                $this->command?->warn("Blok {$namaBlok} di RT {$nomorRt} sudah dipakai, dilewati.");

                continue;
            }

            // Arah keluar dari pusat menuju kelompok titik RT ini
            $titikRt = $semua->where('rt', $rt->id);
            $sudut = 2 * M_PI * $i / count(self::BLOK) + M_PI / 6;
            $arah = $titikRt->isNotEmpty()
                ? [$titikRt->avg('lat') - $pusat[0], $titikRt->avg('lng') - $pusat[1]]
                : [sin($sudut), cos($sudut)];
            if (sqrt($arah[0] ** 2 + $arah[1] ** 2) < 0.00005) {
                $arah = [sin($sudut), cos($sudut)]; // kelompok RT tepat di tengah: sebar melingkar
            }
            $panjang = sqrt($arah[0] ** 2 + $arah[1] ** 2);
            $arah = [$arah[0] / $panjang, $arah[1] / $panjang];

            // Mulai tepat di luar area yang sudah ada, geser keluar bila masih bertabrakan
            $jarak = $radius + 0.0005;
            do {
                $asal = [$pusat[0] + $arah[0] * $jarak, $pusat[1] + $arah[1] * $jarak];
                $posisi = $this->tataLetak($asal);
                $jarak += 0.0003;
            } while ($this->bertabrakan($posisi, $terpakai) && $jarak < $radius + 0.01);

            foreach ($posisi as $k => [$lat, $lng]) {
                $n = $k + 1;
                $rumah = Rumah::query()->where('blok_id', $blok->id)->where('nomor', (string) $n)->first();
                if (! $rumah) {
                    $rumah = Rumah::query()->create([
                        'blok_id' => $blok->id,
                        'nomor' => (string) $n,
                        'baris' => $n <= 5 ? 1 : 2,
                        'kolom' => $n <= 5 ? $n : $n - 5,
                        'lat' => round($lat, 7),
                        'lng' => round($lng, 7),
                        'status_hunian' => match (true) {
                            $n === 4 => 'kontrakan',
                            $n === 9 => 'kosong',
                            $n === 10 && $nomorRt === '04' => 'usaha',
                            default => 'dihuni',
                        },
                        'keterangan' => $n === 10 && $nomorRt === '04' ? 'Warung kelontong' : null,
                    ]);
                } elseif ($rumah->lat === null) {
                    $rumah->update(['lat' => round($lat, 7), 'lng' => round($lng, 7)]);
                }
                $terpakai[] = [$rumah->lat, $rumah->lng];

                if (in_array($rumah->status_hunian, ['kosong', 'usaha'], true) || $rumah->kartuKeluargas()->exists()) {
                    continue;
                }

                $this->buatKeluarga($rumah, $faker);
            }
        }

        // Tagihan bulan berjalan untuk keluarga baru (tagihan yang sudah ada tidak diduplikasi)
        app(TagihanService::class)->generate(now());
    }

    /** @return array{0: array{0: float, 1: float}, 1: float} */
    private function pusatDanRadius($titik): array
    {
        if ($titik->isEmpty()) {
            $awal = Pengaturan::petaAwal();
            $pusat = $awal['tersimpan'] ? [$awal['lat'], $awal['lng']] : self::PUSAT_CADANGAN;

            return [$pusat, 0.0006];
        }

        $pusat = [$titik->avg('lat'), $titik->avg('lng')];
        $radius = $titik->map(fn ($t) => sqrt(($t['lat'] - $pusat[0]) ** 2 + ($t['lng'] - $pusat[1]) ** 2))->max();

        return [$pusat, max(0.0004, (float) $radius)];
    }

    /** Dua deret rumah berhadapan (5 + 5) dengan jalan di tengah, berpusat di $asal. */
    private function tataLetak(array $asal): array
    {
        $hasil = [];
        foreach ([1, 2] as $baris) {
            for ($k = 0; $k < 5; $k++) {
                $hasil[] = [
                    $asal[0] + ($baris === 1 ? 0.5 : -0.5) * self::JARAK_BARIS,
                    $asal[1] + ($k - 2) * self::JARAK_RUMAH,
                ];
            }
        }

        return $hasil;
    }

    private function bertabrakan(array $posisi, array $terpakai): bool
    {
        foreach ($posisi as [$lat, $lng]) {
            foreach ($terpakai as [$a, $b]) {
                if (abs($lat - $a) < 0.0001 && abs($lng - $b) < 0.0001) {
                    return true;
                }
            }
        }

        return false;
    }

    private function nik(string $awal = '340410'): string
    {
        do {
            $nik = $awal.str_pad((string) mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (AnggotaKeluarga::query()->where('nik', $nik)->exists());

        return $nik;
    }

    private function noKk(): string
    {
        do {
            $no = '340410'.str_pad((string) mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (KartuKeluarga::query()->where('no_kk', $no)->exists());

        return $no;
    }

    private function pilih(array $daftar): string
    {
        return $daftar[mt_rand(0, count($daftar) - 1)];
    }

    private function buatKeluarga(Rumah $rumah, $faker): void
    {
        $belakang = $this->pilih($this->belakang);
        $kepala = $this->pilih($this->pria).' '.$belakang;
        $agama = mt_rand(1, 10) <= 8 ? 'Islam' : $this->pilih(['Katolik', 'Kristen']);
        $kontrak = $rumah->status_hunian === 'kontrakan';

        $kk = KartuKeluarga::query()->create([
            'rumah_id' => $rumah->id,
            'no_kk' => $this->noKk(),
            'nama_kepala' => $kepala,
            'status_tinggal' => $kontrak ? 'kontrak' : 'tetap',
            'no_hp' => '08'.mt_rand(11, 89).str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            'tanggal_masuk' => $kontrak ? now()->subMonths(mt_rand(3, 20))->toDateString() : null,
            'aktif' => true,
        ]);

        $lahirKepala = now()->subYears(mt_rand(30, 66))->subDays(mt_rand(0, 360));
        AnggotaKeluarga::query()->create([
            'kartu_keluarga_id' => $kk->id, 'nik' => $this->nik(), 'nama' => $kepala, 'jenis_kelamin' => 'L',
            'hubungan' => 'Kepala Keluarga', 'tempat_lahir' => $this->pilih(['Sleman', 'Sleman', 'Yogyakarta', 'Klaten', 'Bantul', 'Gunungkidul']),
            'tanggal_lahir' => $lahirKepala->toDateString(), 'agama' => $agama, 'status_perkawinan' => 'Kawin',
            'pekerjaan' => $lahirKepala->age > 60 ? 'Pensiunan' : $this->pilih($this->pekerjaan),
            'pendidikan' => $this->pilih(['SD/Sederajat', 'SMP/Sederajat', 'SMA/Sederajat', 'SMA/Sederajat', 'Diploma III', 'Diploma IV/S1']),
            'no_hp' => $kk->no_hp, 'urutan' => 0,
        ]);

        AnggotaKeluarga::query()->create([
            'kartu_keluarga_id' => $kk->id, 'nik' => $this->nik(), 'nama' => $this->pilih($this->wanita), 'jenis_kelamin' => 'P',
            'hubungan' => 'Istri', 'tempat_lahir' => $this->pilih(['Sleman', 'Yogyakarta', 'Klaten', 'Magelang']),
            'tanggal_lahir' => $lahirKepala->copy()->addYears(mt_rand(1, 5))->toDateString(), 'agama' => $agama,
            'status_perkawinan' => 'Kawin', 'pekerjaan' => $this->pilih(['Mengurus Rumah Tangga', 'Pedagang', 'Guru', 'Karyawan Swasta', 'Wiraswasta']),
            'pendidikan' => $this->pilih(['SMP/Sederajat', 'SMA/Sederajat', 'Diploma III', 'Diploma IV/S1']), 'urutan' => 1,
        ]);

        $jumlahAnak = mt_rand(0, 3);
        for ($a = 0; $a < $jumlahAnak; $a++) {
            $jk = mt_rand(0, 1) ? 'L' : 'P';
            $umur = max(1, min(30, $lahirKepala->age - mt_rand(22, 32) - $a * 3));
            AnggotaKeluarga::query()->create([
                'kartu_keluarga_id' => $kk->id, 'nik' => $this->nik(),
                'nama' => $this->pilih($jk === 'L' ? $this->anakL : $this->anakP).' '.$belakang,
                'jenis_kelamin' => $jk, 'hubungan' => 'Anak', 'tempat_lahir' => 'Sleman',
                'tanggal_lahir' => now()->subYears($umur)->subDays(mt_rand(0, 360))->toDateString(), 'agama' => $agama,
                'status_perkawinan' => $umur > 25 && mt_rand(0, 1) ? 'Kawin' : 'Belum Kawin',
                'pekerjaan' => $umur < 6 ? 'Belum/Tidak Bekerja' : ($umur <= 22 ? 'Pelajar/Mahasiswa' : $this->pilih($this->pekerjaan)),
                'pendidikan' => match (true) {
                    $umur < 6 => 'Tidak/Belum Sekolah', $umur < 12 => 'Belum Tamat SD', $umur < 15 => 'SD/Sederajat',
                    $umur < 18 => 'SMP/Sederajat', default => $this->pilih(['SMA/Sederajat', 'Diploma IV/S1']),
                },
                'urutan' => $a + 2,
            ]);
        }

        // Sebagian keluarga tinggal bersama orang tua (simbah)
        if (mt_rand(1, 5) === 1) {
            AnggotaKeluarga::query()->create([
                'kartu_keluarga_id' => $kk->id, 'nik' => $this->nik(), 'nama' => 'Mbah '.$this->pilih(['Karto', 'Sastro', 'Ngadiman', 'Painem', 'Tukinem']),
                'jenis_kelamin' => mt_rand(0, 1) ? 'L' : 'P', 'hubungan' => 'Orang Tua', 'tempat_lahir' => 'Sleman',
                'tanggal_lahir' => $lahirKepala->copy()->subYears(mt_rand(22, 30))->toDateString(), 'agama' => $agama,
                'status_perkawinan' => 'Cerai Mati', 'pekerjaan' => 'Belum/Tidak Bekerja', 'pendidikan' => 'SD/Sederajat', 'urutan' => 9,
            ]);
        }
    }
}
