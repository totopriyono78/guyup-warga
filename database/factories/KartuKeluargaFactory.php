<?php

namespace Database\Factories;

use App\Models\KartuKeluarga;
use App\Models\Rumah;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KartuKeluarga> */
class KartuKeluargaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rumah_id' => Rumah::factory(),
            'no_kk' => fake()->unique()->numerify('3201############'),
            'nama_kepala' => fake()->name('male'),
            'status_tinggal' => 'tetap',
            'no_hp' => '08'.fake()->numerify('##########'),
            'tanggal_masuk' => fake()->dateTimeBetween('-15 years', '-1 month'),
            'aktif' => true,
        ];
    }
}
