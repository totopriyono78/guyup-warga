<?php

namespace Database\Factories;

use App\Models\KartuKeluarga;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tagihan> */
class TagihanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kartu_keluarga_id' => KartuKeluarga::factory(),
            'periode' => now()->startOfMonth()->toDateString(),
            'nominal' => 50000,
            'rincian' => [['nama' => 'Iuran bulanan', 'nominal' => 50000]],
            'status' => 'belum',
        ];
    }
}
