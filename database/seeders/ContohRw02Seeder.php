<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Satu perintah untuk menyiapkan data contoh RW 02 Dusun Sidorejo:
 *   php artisan db:seed --class=ContohRw02Seeder
 */
class ContohRw02Seeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IdentitasRw02Seeder::class,
            WargaTambahanSeeder::class,
            PengumumanContohSeeder::class,
            GaleriContohSeeder::class,
            DonasiContohSeeder::class,
        ]);
    }
}
