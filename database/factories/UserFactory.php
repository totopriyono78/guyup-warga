<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_WARGA,
            'aktif' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function pengurusRt(int $rtId): static
    {
        return $this->state(fn () => ['role' => User::ROLE_RT, 'rt_id' => $rtId]);
    }

    public function warga(int $kkId): static
    {
        return $this->state(fn () => ['role' => User::ROLE_WARGA, 'kartu_keluarga_id' => $kkId]);
    }
}
