<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_tampil_dan_tamu_diarahkan_ke_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
        $this->get('/')->assertOk(); // halaman umum
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_dengan_email_atau_no_hp(): void
    {
        $user = User::factory()->admin()->create(['email' => 'rw@contoh.id', 'no_hp' => '081234567890']);

        $this->post('/login', ['email' => 'rw@contoh.id', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->post('/login', ['email' => '081234567890', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        User::factory()->create(['email' => 'x@contoh.id', 'aktif' => false]);

        $this->post('/login', ['email' => 'x@contoh.id', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_semua_halaman_utama_bisa_dibuka_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['/', '/dashboard', '/denah', '/keluarga', '/cari?q=bu', '/pengumuman', '/iuran', '/iuran/tarif', '/iuran/transaksi', '/wilayah', '/wilayah/peta', '/pengguna', '/pengaturan', '/profil', '/keluarga/create', '/pengumuman/create', '/donasi', '/donasi/create', '/galeri', '/galeri/create', '/galang'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
