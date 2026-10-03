<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Membuat akun admin RW dari ADMIN_EMAIL / ADMIN_PASSWORD di .env.
     * Bila ADMIN_PASSWORD diisi, password akun yang sudah ada ikut disamakan,
     * sehingga menjalankan ulang `php artisan db:seed` setelah mengubah .env langsung berlaku.
     *
     * Data contoh dibuat terpisah: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $email = strtolower(trim((string) config('siwarga.admin.email', 'admin@rw.local')));
        $passwordEnv = (string) config('siwarga.admin.password');

        $admin = User::query()->whereRaw('LOWER(email) = ?', [$email])->first() ?? new User(['email' => $email]);
        $baru = ! $admin->exists;

        $admin->fill([
            'name' => $admin->name ?: config('siwarga.admin.name', 'Admin RW'),
            'role' => User::ROLE_ADMIN,
            'rt_id' => null,
            'aktif' => true,
        ]);

        $passwordSementara = null;
        if ($passwordEnv !== '') {
            $admin->password = $passwordEnv;
        } elseif ($baru) {
            $passwordSementara = Str::password(12, symbols: false);
            $admin->password = $passwordSementara;
        }

        $admin->save();

        $this->command?->info(($baru ? 'Akun admin dibuat: ' : 'Akun admin diperbarui: ').$email);
        if ($passwordSementara) {
            $this->command?->warn("ADMIN_PASSWORD kosong. Password sementara: {$passwordSementara}  (segera ganti di menu Profil)");
        } elseif ($passwordEnv !== '') {
            $this->command?->info('Password disamakan dengan ADMIN_PASSWORD di .env.');
        }
    }
}
