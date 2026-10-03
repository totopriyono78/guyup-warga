<?php

namespace Database\Factories;

use App\Models\Blok;
use App\Models\Rumah;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rumah> */
class RumahFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 5000);

        return [
            'blok_id' => Blok::factory(),
            'nomor' => (string) $n,
            'baris' => intdiv($n, 50) + 1,
            'kolom' => $n % 50 + 1,
            'status_hunian' => 'dihuni',
        ];
    }
}
