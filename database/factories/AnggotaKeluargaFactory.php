<?php

namespace Database\Factories;

use App\Models\AnggotaKeluarga;
use App\Models\KartuKeluarga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnggotaKeluarga> */
class AnggotaKeluargaFactory extends Factory
{
    public function definition(): array
    {
        $jk = fake()->randomElement(['L', 'P']);

        return [
            'kartu_keluarga_id' => KartuKeluarga::factory(),
            'nik' => fake()->unique()->numerify('3201############'),
            'nama' => fake()->name($jk === 'L' ? 'male' : 'female'),
            'jenis_kelamin' => $jk,
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-70 years', '-1 year'),
            'hubungan' => 'Anak',
            'agama' => 'Islam',
        ];
    }
}
