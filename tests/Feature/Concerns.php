<?php

namespace Tests\Feature;

use App\Models\AnggotaKeluarga;
use App\Models\Blok;
use App\Models\KartuKeluarga;
use App\Models\Rt;
use App\Models\Rumah;
use App\Models\TarifIuran;
use App\Models\User;

trait Concerns
{
    protected function buatWilayah(string $nomorRt = '01', string $blok = 'A'): Rumah
    {
        $rt = Rt::query()->firstOrCreate(['nomor' => $nomorRt], ['warna' => '#0f766e']);
        $b = Blok::query()->firstOrCreate(['rt_id' => $rt->id, 'nama' => $blok]);
        $no = (string) (Rumah::query()->where('blok_id', $b->id)->count() + 1);

        return Rumah::query()->create(['blok_id' => $b->id, 'nomor' => $no, 'baris' => 1, 'kolom' => (int) $no]);
    }

    protected function buatKeluarga(?Rumah $rumah = null, string $nama = 'Budi Santoso'): KartuKeluarga
    {
        $rumah ??= $this->buatWilayah();
        $kk = KartuKeluarga::query()->create(['rumah_id' => $rumah->id, 'nama_kepala' => $nama, 'status_tinggal' => 'tetap']);
        AnggotaKeluarga::query()->create([
            'kartu_keluarga_id' => $kk->id, 'nama' => $nama, 'jenis_kelamin' => 'L',
            'hubungan' => 'Kepala Keluarga', 'nik' => fake()->unique()->numerify('3201############'),
        ]);

        return $kk;
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function tarif(int $nominal = 50000, ?int $rtId = null): TarifIuran
    {
        return TarifIuran::query()->create(['nama' => 'Iuran bulanan', 'nominal' => $nominal, 'rt_id' => $rtId, 'aktif' => true]);
    }
}
