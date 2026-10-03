<?php

namespace Tests\Feature;

use App\Models\Rumah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SusunDenahTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_pindah_tukar_posisi_tukar_nomor_dan_ganti_nomor(): void
    {
        $r1 = $this->buatWilayah('01', 'A'); // No.1 di (1,1)
        $r2 = $this->buatWilayah('01', 'A'); // No.2 di (1,2)
        $kk = $this->buatKeluarga($r1, 'Pemilik Satu');
        $url = route('blok.susun', $r1->blok_id);
        $this->actingAs($this->admin());

        $this->get(route('blok.show', $r1->blok_id))->assertOk()->assertSee('Susunan denah');

        // pindah ke petak kosong
        $this->postJson($url, ['aksi' => 'pindah', 'rumah_id' => $r1->id, 'baris' => 3, 'kolom' => 5])->assertOk()->assertJsonCount(2, 'rumahs');
        $this->assertSame([3, 5], [$r1->fresh()->baris, $r1->fresh()->kolom]);

        // pindah ke petak terisi ditolak
        $this->postJson($url, ['aksi' => 'pindah', 'rumah_id' => $r1->id, 'baris' => 1, 'kolom' => 2])->assertStatus(422);

        // tukar posisi
        $this->postJson($url, ['aksi' => 'tukar_posisi', 'rumah_id' => $r1->id, 'target_id' => $r2->id])->assertOk();
        $this->assertSame([1, 2], [$r1->fresh()->baris, $r1->fresh()->kolom]);
        $this->assertSame([3, 5], [$r2->fresh()->baris, $r2->fresh()->kolom]);

        // tukar nomor: KK tetap di rumah (id) yang sama
        $this->postJson($url, ['aksi' => 'tukar_nomor', 'rumah_id' => $r1->id, 'target_id' => $r2->id])->assertOk();
        $this->assertSame('2', $r1->fresh()->nomor);
        $this->assertSame('1', $r2->fresh()->nomor);
        $this->assertSame($r1->id, $kk->fresh()->rumah_id);

        // ganti nomor: bentrok ditolak, nomor baru diterima
        $this->postJson($url, ['aksi' => 'nomor', 'rumah_id' => $r1->id, 'nomor' => '1'])->assertStatus(422);
        $this->postJson($url, ['aksi' => 'nomor', 'rumah_id' => $r1->id, 'nomor' => '12A'])->assertOk();
        $this->assertSame('12A', $r1->fresh()->nomor);
    }

    public function test_tidak_bisa_menyusun_blok_rt_lain_atau_rumah_blok_lain(): void
    {
        $rt1 = $this->buatWilayah('01', 'A');
        $rt2 = $this->buatWilayah('02', 'C');
        $this->actingAs(User::factory()->pengurusRt($rt1->blok->rt_id)->create());

        $this->postJson(route('blok.susun', $rt2->blok_id), ['aksi' => 'pindah', 'rumah_id' => $rt2->id, 'baris' => 5, 'kolom' => 5])->assertForbidden();

        // rumah dari blok lain tidak bisa diselipkan ke blok sendiri
        $this->postJson(route('blok.susun', $rt1->blok_id), ['aksi' => 'pindah', 'rumah_id' => $rt2->id, 'baris' => 5, 'kolom' => 5])->assertStatus(422);
        $this->assertSame(1, Rumah::query()->find($rt2->id)->baris);
    }
}
