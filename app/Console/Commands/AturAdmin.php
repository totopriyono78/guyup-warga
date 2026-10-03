<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Membuat atau mereset akun Pengurus RW.
 *   php artisan admin:atur                       (pakai ADMIN_EMAIL / ADMIN_PASSWORD dari .env)
 *   php artisan admin:atur admin@rw.id           (password ditanyakan)
 *   php artisan admin:atur admin@rw.id --password=RahasiaKuat123
 */
class AturAdmin extends Command
{
    protected $signature = 'admin:atur {email? : Email akun admin} {--password= : Password baru} {--name= : Nama}';

    protected $description = 'Buat atau reset password akun Pengurus RW (admin)';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: config('siwarga.admin.email'))));
        $password = (string) ($this->option('password') ?: config('siwarga.admin.password'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }

        if ($password === '') {
            $password = (string) $this->secret('Password baru (minimal 8 karakter)');
        }

        if (strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first() ?? new User(['email' => $email]);
        $baru = ! $user->exists;

        $user->fill([
            'name' => $this->option('name') ?: ($user->name ?: config('siwarga.admin.name', 'Admin RW')),
            'role' => User::ROLE_ADMIN,
            'rt_id' => null,
            'aktif' => true,
        ]);
        $user->password = $password;
        $user->save();

        $this->info(($baru ? 'Akun admin dibuat: ' : 'Password akun admin direset: ').$email);

        return self::SUCCESS;
    }
}
