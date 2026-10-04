<?php

namespace Tests\Feature;

use App\Models\AnggotaKeluarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HapusAnggotaTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_pengurus_bisa_menghapus_anggota_yang_salah_ditambahkan(): void
    {
        $kk = $this->buatKeluarga(null, 'Budi Santoso');
        $salah = AnggotaKeluarga::query()->create([
            'kartu_keluarga_id' => $kk->id, 'nama' => 'Salah Input', 'jenis_kelamin' => 'P', 'hubungan' => 'Anak',
        ]);
        $this->actingAs($this->admin());

        $this->get(route('keluarga.show', $kk))->assertOk()->assertSee(route('anggota.destroy', $salah), false);
        $this->get(route('anggota.edit', $salah))->assertOk()->assertSee('Hapus anggota keluarga');

        $this->delete(route('anggota.destroy', $salah))->assertRedirect(route('keluarga.show', $kk));
        $this->assertModelMissing($salah);
    }

    public function test_kepala_keluarga_terkunci_selama_ada_anggota_lain(): void
    {
        $kk = $this->buatKeluarga(null, 'Budi Santoso');
        $kepala = $kk->anggota()->first();
        AnggotaKeluarga::query()->create(['kartu_keluarga_id' => $kk->id, 'nama' => 'Siti', 'jenis_kelamin' => 'P', 'hubungan' => 'Istri']);
        $this->actingAs($this->admin());

        $this->get(route('anggota.edit', $kepala))->assertOk()->assertSee('Jadikan anggota lain sebagai kepala keluarga');
        $this->delete(route('anggota.destroy', $kepala))->assertSessionHas('gagal');
        $this->assertModelExists($kepala);
    }

    public function test_warga_lain_tidak_bisa_menghapus(): void
    {
        $kk = $this->buatKeluarga(null, 'Budi Santoso');
        $anggota = $kk->anggota()->first();
        $lain = User::factory()->create(['role' => User::ROLE_WARGA]);

        $this->actingAs($lain)->delete(route('anggota.destroy', $anggota))->assertForbidden();
        $this->assertModelExists($anggota);
    }
}
