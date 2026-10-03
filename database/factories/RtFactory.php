<?php

namespace Database\Factories;

use App\Models\Rt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rt> */
class RtFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nomor' => str_pad((string) fake()->unique()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'nama_ketua' => fake()->name('male'),
            'no_hp_ketua' => '08'.fake()->numerify('##########'),
            'warna' => fake()->randomElement(\App\Models\Rt::PALET),
        ];
    }
}
