<?php

namespace Tests\Feature;

use App\Models\Donasi;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublikTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_halaman_umum_menampilkan_warna_rt_dan_nama_kepala_tanpa_data_pribadi(): void
    {
        $rumah = $this->buatWilayah('01');
        $rumah->update(['lat' => -6.2, 'lng' => 106.8]);
        $rumah->blok->rt->update(['warna' => '#dc2626']);
        $this->buatKeluarga($rumah, 'Rahasia Penghuni');

        $this->get('/')
            ->assertOk()
            ->assertSee('#dc2626')
            ->assertSee('RT 01')
            ->assertSee('A-'.$rumah->nomor)
            ->assertSee('Rahasia Penghuni') // nama kepala keluarga tampil (pengaturan bawaan)
            ->assertDontSee($rumah->kartuKeluargas()->first()->anggota()->first()->nik)
            ->assertSee(route('login'));

        // pengurus RW bisa mematikannya di Pengaturan
        \App\Models\Pengaturan::simpan(['peta_umum_nama' => '0']);
        $this->get('/')->assertOk()->assertSee('A-'.$rumah->nomor)->assertDontSee('Rahasia Penghuni');
    }

    public function test_halaman_donasi_menyembunyikan_nominal_donatur(): void
    {
        $admin = $this->admin();
        $d = Donasi::query()->create(['user_id' => $admin->id, 'judul' => 'Renovasi Pos', 'publik' => true, 'aktif' => true, 'tampilkan_total' => false]);
        $d->donaturs()->create(['nama' => 'Kel. Budi', 'nominal' => 123457, 'tanggal' => now()->toDateString()]);
        $d->donaturs()->create(['nama' => 'Pak Rahasia', 'anonim' => true, 'nominal' => 765433, 'tanggal' => now()->toDateString()]);

        $this->get(route('publik.donasi', $d))
            ->assertOk()
            ->assertSee('Kel. Budi')
            ->assertSee('Donatur anonim')
            ->assertDontSee('Pak Rahasia')
            ->assertDontSee('123.457')
            ->assertDontSee('765.433')
            ->assertDontSee('888.890'); // total juga disembunyikan bila tampilkan_total = false

        $this->get('/')->assertOk()->assertSee('Renovasi Pos');

        // Pengurus tetap melihat nominal
        $this->actingAs($admin)->get(route('donasi.show', $d))->assertOk()->assertSee('123.457')->assertSee('Pak Rahasia');
    }

    public function test_konten_non_publik_tidak_bisa_diakses_umum(): void
    {
        $admin = $this->admin();
        $d = Donasi::query()->create(['user_id' => $admin->id, 'judul' => 'Internal', 'publik' => false]);
        $p = Pengumuman::query()->create(['user_id' => $admin->id, 'judul' => 'Khusus warga', 'isi' => 'x', 'terbit_pada' => now()->subMinute()]);
        $draf = Pengumuman::query()->create(['user_id' => $admin->id, 'judul' => 'Draf', 'isi' => 'x', 'publik' => true]);
        $umum = Pengumuman::query()->create(['user_id' => $admin->id, 'judul' => 'Kerja bakti', 'isi' => 'Minggu pagi', 'publik' => true, 'terbit_pada' => now()->subMinute()]);

        $this->get(route('publik.donasi', $d))->assertNotFound();
        $this->get(route('publik.pengumuman', $p))->assertNotFound();
        $this->get(route('publik.pengumuman', $draf))->assertNotFound();
        $this->get(route('publik.pengumuman', $umum))->assertOk()->assertSee('Minggu pagi');
        $this->get('/')->assertSee('Kerja bakti')->assertDontSee('Khusus warga')->assertDontSee('Internal');
    }

    public function test_kelola_donasi_dan_otorisasi(): void
    {
        $rumah1 = $this->buatWilayah('01');
        $rumah2 = $this->buatWilayah('02', 'B');
        $kk = $this->buatKeluarga($rumah1, 'Joko Susilo');
        $rt1 = User::factory()->pengurusRt($rumah1->blok->rt_id)->create();
        $rt2 = User::factory()->pengurusRt($rumah2->blok->rt_id)->create();

        $this->actingAs($rt1)->post(route('donasi.store'), [
            'judul' => 'Santunan RT 01', 'target' => 1000000, 'publik' => '1', 'aktif' => '1', 'rt_id' => $rumah2->blok->rt_id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $d = Donasi::query()->firstOrFail();
        $this->assertSame($rumah1->blok->rt_id, $d->rt_id); // pengurus RT dipaksa ke RT-nya

        $this->post(route('donasi.donatur.store', $d), ['kartu_keluarga_id' => $kk->id, 'nominal' => 50000, 'tanggal' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame('Kel. Joko Susilo', $d->donaturs()->first()->nama);
        $this->assertSame(50000, $d->fresh()->terkumpul);

        // Pengurus RT lain tidak boleh melihat / mengelola
        $this->actingAs($rt2)->get(route('donasi.show', $d))->assertForbidden();
        $this->post(route('donasi.donatur.store', $d), ['nama' => 'X', 'nominal' => 1, 'tanggal' => now()->toDateString()])->assertForbidden();

        // Warga biasa tidak punya akses menu pengurus
        $warga = User::factory()->create(['kartu_keluarga_id' => $kk->id]);
        $this->actingAs($warga)->get(route('donasi.index'))->assertForbidden();

        // Hapus program yang sudah ada donatur → ditutup & disembunyikan
        $this->actingAs($rt1)->delete(route('donasi.destroy', $d))->assertRedirect(route('donasi.index'));
        $this->assertFalse($d->fresh()->publik);
        $this->assertFalse($d->fresh()->aktif);
    }
}
