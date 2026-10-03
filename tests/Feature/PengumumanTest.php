<?php

namespace Tests\Feature;

use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengumumanTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_warga_melihat_pengumuman_rw_dan_rt_sendiri_saja(): void
    {
        $kk = $this->buatKeluarga($this->buatWilayah('01'));
        $rt2 = $this->buatWilayah('02', 'C')->blok->rt;
        $warga = User::factory()->warga($kk->id)->create();

        $rw = Pengumuman::query()->create(['judul' => 'Info RW', 'isi' => 'untuk semua', 'terbit_pada' => now()->subMinute()]);
        $rtSaya = Pengumuman::query()->create(['judul' => 'Info RT Saya', 'isi' => 'x', 'rt_id' => $kk->rumah->blok->rt_id, 'terbit_pada' => now()->subMinute()]);
        $rtLain = Pengumuman::query()->create(['judul' => 'Info RT Lain', 'isi' => 'x', 'rt_id' => $rt2->id, 'terbit_pada' => now()->subMinute()]);
        $draf = Pengumuman::query()->create(['judul' => 'Draf Rahasia', 'isi' => 'x', 'terbit_pada' => null]);

        $this->actingAs($warga)->get(route('pengumuman.index'))
            ->assertSee('Info RW')->assertSee('Info RT Saya')->assertDontSee('Info RT Lain')->assertDontSee('Draf Rahasia');

        $this->get(route('pengumuman.show', $rtLain))->assertNotFound();
        $this->get(route('pengumuman.show', $draf))->assertNotFound();
        $this->get(route('pengumuman.create'))->assertForbidden();
    }

    public function test_pengurus_rt_hanya_bisa_mengumumkan_untuk_rt_nya(): void
    {
        $rumah = $this->buatWilayah('01');
        $rt2 = $this->buatWilayah('02', 'C')->blok->rt;
        $pengurus = User::factory()->pengurusRt($rumah->blok->rt_id)->create();

        $this->actingAs($pengurus)->post(route('pengumuman.store'), [
            'judul' => 'Ronda', 'isi' => 'Jadwal ronda', 'rt_id' => $rt2->id,
        ])->assertRedirect();

        $this->assertSame($rumah->blok->rt_id, Pengumuman::query()->firstOrFail()->rt_id);
    }
}
