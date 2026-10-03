<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\Rumah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetaTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_pengurus_menyimpan_dan_menghapus_titik_rumah(): void
    {
        $rumah = $this->buatWilayah('01');
        $this->actingAs($this->admin());

        $this->get(route('peta.edit'))->assertOk()->assertSee('Atur Titik Rumah');

        $this->putJson(route('rumah.lokasi', $rumah), ['lat' => -6.2012345, 'lng' => 106.8165432])
            ->assertOk()->assertJsonPath('rumah.kode', $rumah->blok->nama.'-'.$rumah->nomor);
        $this->assertEqualsWithDelta(-6.2012345, $rumah->fresh()->lat, 0.0000001);
        $this->assertEqualsWithDelta(106.8165432, $rumah->fresh()->lng, 0.0000001);

        $this->putJson(route('rumah.lokasi', $rumah), ['lat' => 200, 'lng' => 106])->assertStatus(422);

        $this->putJson(route('rumah.lokasi', $rumah), ['lat' => null, 'lng' => null])->assertOk();
        $this->assertNull($rumah->fresh()->lat);
    }

    public function test_membuat_rumah_baru_dari_titik_peta(): void
    {
        $rumah = $this->buatWilayah('01', 'A'); // A-1 di baris 1 kolom 1
        $this->actingAs($this->admin());

        $this->postJson(route('rumah.peta.store'), [
            'blok_id' => $rumah->blok_id, 'nomor' => '7', 'lat' => -6.2, 'lng' => 106.8,
        ])->assertCreated()->assertJsonPath('rumah.kode', 'A-7');

        $baru = Rumah::query()->where('nomor', '7')->firstOrFail();
        $this->assertSame([1, 2], [$baru->baris, $baru->kolom]); // posisi denah kosong berikutnya
        $this->assertEqualsWithDelta(-6.2, $baru->lat, 0.0000001);

        // nomor ganda ditolak
        $this->postJson(route('rumah.peta.store'), ['blok_id' => $rumah->blok_id, 'nomor' => '7', 'lat' => -6.2, 'lng' => 106.8])
            ->assertStatus(422);
    }

    public function test_pengurus_rt_tidak_bisa_mengubah_titik_rt_lain(): void
    {
        $rt1 = $this->buatWilayah('01', 'A');
        $rt2 = $this->buatWilayah('02', 'C');
        $this->actingAs(User::factory()->pengurusRt($rt1->blok->rt_id)->create());

        $this->putJson(route('rumah.lokasi', $rt2), ['lat' => -6.2, 'lng' => 106.8])->assertForbidden();
        $this->postJson(route('rumah.peta.store'), ['blok_id' => $rt2->blok_id, 'nomor' => '9', 'lat' => -6.2, 'lng' => 106.8])->assertForbidden();
        $this->putJson(route('peta.awal'), ['lat' => -6.2, 'lng' => 106.8, 'zoom' => 17])->assertForbidden();
    }

    public function test_admin_menyimpan_posisi_awal_peta(): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('peta.awal'), ['lat' => -6.25, 'lng' => 106.85, 'zoom' => 18])->assertOk();

        $awal = Pengaturan::petaAwal();
        $this->assertEqualsWithDelta(-6.25, $awal['lat'], 0.0001);
        $this->assertSame(18, $awal['zoom']);
        $this->assertTrue($awal['tersimpan']);
    }

    public function test_denah_menampilkan_peta_bila_ada_titik_dan_privasi_warga(): void
    {
        $rumahSaya = $this->buatWilayah('01', 'A');
        $rumahLain = $this->buatWilayah('01', 'A');
        $rumahSaya->update(['lat' => -6.2, 'lng' => 106.8]);
        $rumahLain->update(['lat' => -6.2001, 'lng' => 106.8001]);
        $kkSaya = $this->buatKeluarga($rumahSaya, 'Warga Saya');
        $this->buatKeluarga($rumahLain, 'Tetangga Rahasia');
        $warga = User::factory()->warga($kkSaya->id)->create();

        // default "nama": nama tetangga terlihat
        $this->actingAs($warga)->get(route('denah'))->assertOk()->assertSee('vendor/leaflet/leaflet.js')->assertSee('Tetangga');

        // "titik": nama tetangga disembunyikan, nama sendiri tetap
        config(['siwarga.peta.warga' => 'titik']);
        $this->get(route('denah'))->assertOk()->assertDontSee('Tetangga')->assertSee('Warga Saya');

        // "sendiri": koordinat rumah lain tidak dikirim
        config(['siwarga.peta.warga' => 'sendiri']);
        $this->get(route('denah'))->assertOk()->assertDontSee('106.8001');
    }

    public function test_ubah_kk_dari_peta_kembali_ke_titik_rumah_dan_tolak_url_luar(): void
    {
        $rumah = $this->buatWilayah('01');
        $kk = $this->buatKeluarga($rumah, 'Lama Namanya');
        $this->actingAs($this->admin());

        $kembali = route('denah', ['tampilan' => 'peta']).'#rumah-'.$rumah->id;
        $this->get(route('keluarga.edit', ['keluarga' => $kk, 'kembali' => $kembali]))->assertOk()->assertSee('name="kembali"', false);

        $data = ['rumah_id' => $rumah->id, 'status_tinggal' => 'kontrak', 'aktif' => 1, 'no_hp' => '0811'];
        $this->put(route('keluarga.update', $kk), $data + ['kembali' => $kembali])->assertRedirect($kembali);
        $this->assertSame('kontrak', $kk->fresh()->status_tinggal);

        // URL luar tidak diikuti (mencegah open redirect)
        $this->put(route('keluarga.update', $kk), $data + ['kembali' => 'https://situs-lain.test/x'])->assertRedirect(route('keluarga.show', $kk));

        // tautan ubah KK & anggota tersedia di data denah untuk pengurus
        $rumah->update(['lat' => -6.2, 'lng' => 106.8]);
        $this->get(route('denah'))->assertOk()->assertSee('urlEdit', false);
    }
}
