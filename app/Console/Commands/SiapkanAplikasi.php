<?php

namespace App\Console\Commands;

use App\Models\Rt;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Satu perintah untuk hosting tanpa terminal (mis. Railway "Pre-deploy command"):
 *   php artisan rukoon:siapkan            migrasi + akun admin (dari ADMIN_EMAIL / ADMIN_PASSWORD)
 *   php artisan rukoon:siapkan --contoh   ... + data contoh RW 02 bila database masih kosong
 * Data contoh juga bisa diaktifkan dengan variabel SEED_CONTOH=true.
 * Aman dijalankan setiap deploy: data yang sudah ada tidak digandakan.
 */
class SiapkanAplikasi extends Command
{
    protected $signature = 'rukoon:siapkan {--contoh : Isi data contoh bila belum ada data wilayah}';

    protected $description = 'Migrasi database, buat akun admin, dan (opsional) isi data contoh';

    public function handle(): int
    {
        $this->components->info('Migrasi database…');
        $this->call('migrate', ['--force' => true]);

        $this->components->info('Akun admin…');
        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $contoh = $this->option('contoh') || filter_var(env('SEED_CONTOH', false), FILTER_VALIDATE_BOOL);
        if ($contoh) {
            if (Rt::query()->exists()) {
                $this->components->warn('Data wilayah sudah ada, data contoh tidak diisi ulang.');
            } elseif (! class_exists(\Faker\Factory::class)) {
                $this->components->error('Paket fakerphp/faker tidak terpasang (composer install --no-dev). Data contoh dilewati.');
            } else {
                $this->components->info('Mengisi data contoh RW 02…');
                $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
            }
        }

        $this->components->info('Selesai.');

        return self::SUCCESS;
    }
}
