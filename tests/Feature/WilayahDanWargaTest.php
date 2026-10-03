<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\KartuKeluarga;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WilayahDanWargaTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_admin_menyusun_rt_blok_dan_rumah_massal(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('rt.store'), ['nomor' => '1', 'nama_ketua' => 'Pak RT'])->assertSessionHasNoErrors();
        $rt = Rt::query()->firstOrFail();
        $this->assertSame('01', $rt->nomor);

        $this->post(route('blok.store'), ['rt_id' => $rt->id, 'nama' => 'A'])->assertRedirect();
        $blok = Blok::query()->firstOrFail();

        $this->post(route('rumah.massal', $blok), ['dari' => 1, 'sampai' => 10, 'jumlah_baris' => 2])->assertSessionHasNoErrors();
        $rumah = \App\Models\Rumah::query()->where('blok_id', $blok->id);
        $this->assertSame(10, (clone $rumah)->count());
        $this->assertSame(2, (int) (clone $rumah)->max('baris'));
        $this->assertSame(5, (int) (clone $rumah)->max('kolom'));

        $this->get(route('denah'))->assertOk()->assertSee('Blok A');
        $this->get(route('blok.show', $blok))->assertOk();
    }

    public function test_posisi_rumah_tidak_boleh_bentrok(): void
    {
        $this->actingAs($this->admin());
        $rumah = $this->buatWilayah();

        $this->post(route('rumah.store', $rumah->blok), ['nomor' => '99', 'baris' => $rumah->baris, 'kolom' => $rumah->kolom, 'status_hunian' => 'dihuni'])
            ->assertSessionHasErrors('kolom');
    }

    public function test_pengurus_rt_tidak_bisa_mengelola_rt_lain(): void
    {
        $rumahRt1 = $this->buatWilayah('01', 'A');
        $rumahRt2 = $this->buatWilayah('02', 'C');
        $pengurus = User::factory()->pengurusRt($rumahRt1->blok->rt_id)->create();

        $this->actingAs($pengurus);
        $this->get(route('blok.show', $rumahRt1->blok))->assertOk();
        $this->get(route('blok.show', $rumahRt2->blok))->assertForbidden();

        $kkRt2 = $this->buatKeluarga($rumahRt2);
        $this->get(route('keluarga.edit', $kkRt2))->assertForbidden();
        $this->post(route('rt.store'), ['nomor' => '09'])->assertForbidden();
    }

    public function test_tambah_keluarga_dengan_foto_kepala(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $rumah = $this->buatWilayah();

        $this->post(route('keluarga.store'), [
            'rumah_id' => $rumah->id,
            'no_kk' => '3201010101010001',
            'status_tinggal' => 'tetap',
            'kepala_nama' => 'Ahmad Fauzi',
            'kepala_jenis_kelamin' => 'L',
            'kepala_nik' => '3201010101010002',
            'foto' => UploadedFile::fake()->image('keluarga.jpg', 1600, 1200),
            'kepala_foto' => UploadedFile::fake()->image('wajah.jpg', 600, 800),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $kk = KartuKeluarga::query()->with('anggota')->firstOrFail();
        $this->assertSame('Ahmad Fauzi', $kk->nama_kepala);
        $this->assertCount(1, $kk->anggota);
        Storage::disk('public')->assertExists($kk->foto);
        Storage::disk('public')->assertExists($kk->anggota->first()->foto);

        // foto diperkecil maks 1000px
        [$w] = getimagesize(Storage::disk('public')->path($kk->foto));
        $this->assertLessThanOrEqual(1000, $w);

        // tambah anggota, lalu kepala ganda ditolak
        $this->post(route('anggota.store', $kk), ['nama' => 'Siti', 'jenis_kelamin' => 'P', 'hubungan' => 'Istri'])->assertSessionHasNoErrors();
        $this->post(route('anggota.store', $kk), ['nama' => 'Orang Lain', 'jenis_kelamin' => 'L', 'hubungan' => 'Kepala Keluarga'])->assertSessionHasErrors('hubungan');
    }

    public function test_warga_hanya_melihat_detail_keluarga_sendiri_dan_nik_tidak_bocor(): void
    {
        $kkSaya = $this->buatKeluarga(null, 'Warga Satu');
        $kkLain = $this->buatKeluarga(null, 'Tetangga Dua');
        $nikLain = $kkLain->anggota()->first()->nik;
        $warga = User::factory()->warga($kkSaya->id)->create();

        $this->actingAs($warga);
        $this->get(route('keluarga.index'))->assertRedirect(route('keluarga.show', $kkSaya));
        $this->get(route('keluarga.show', $kkSaya))->assertOk();
        $this->get(route('keluarga.show', $kkLain))->assertForbidden();
        $this->get(route('keluarga.create'))->assertForbidden();
        $this->get(route('iuran.index'))->assertForbidden();

        // denah & pencarian menampilkan nama tetangga tapi tidak NIK-nya
        $this->get(route('denah'))->assertOk()->assertSee('Tetangga Dua')->assertDontSee($nikLain);
        $this->get(route('cari', ['q' => 'Tetangga']))->assertOk()->assertSee('Tetangga Dua');
        $this->get(route('cari', ['q' => $nikLain]))->assertOk()->assertDontSee('Tetangga Dua');
    }

    public function test_pencarian_rumah_dengan_format_blok_nomor(): void
    {
        $this->actingAs($this->admin());
        $rumah = $this->buatWilayah('01', 'B');
        $this->buatKeluarga($rumah, 'Pemilik Rumah B1');

        $this->get(route('cari', ['q' => 'B-1']))->assertOk()->assertSee('Pemilik Rumah B1');
    }

    public function test_pengurus_rt_tanpa_rt_dan_kk_tanpa_rumah(): void
    {
        $tanpaRt = User::factory()->create(['role' => User::ROLE_RT, 'rt_id' => null]);
        $this->actingAs($tanpaRt)->get(route('iuran.index'))->assertForbidden();
        $this->get(route('wilayah'))->assertForbidden();

        $rumah = $this->buatWilayah('01');
        $pengurus = User::factory()->pengurusRt($rumah->blok->rt_id)->create();
        $kkTanpaRumah = KartuKeluarga::query()->create(['nama_kepala' => 'Tanpa Rumah', 'status_tinggal' => 'kos']);

        $this->actingAs($pengurus)->get(route('keluarga.show', $kkTanpaRumah))->assertForbidden();
        $this->get(route('keluarga.edit', $kkTanpaRumah))->assertForbidden();
    }

    public function test_akun_yang_dinonaktifkan_langsung_keluar(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['aktif' => false]);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
