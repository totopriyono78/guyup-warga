<?php

namespace Database\Factories;

use App\Models\Blok;
use App\Models\Rt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Blok> */
class BlokFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rt_id' => Rt::factory(),
            'nama' => fake()->unique()->bothify('?#'),
            'urutan' => 0,
        ];
    }
}
