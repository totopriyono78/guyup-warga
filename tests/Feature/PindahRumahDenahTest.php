<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Rumah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PindahRumahDenahTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_mode_susun_tampil_untuk_pengurus_saja(): void
    {
        $r = $this->buatWilayah('01', 'A');
        $this->buatKeluarga($r, 'Budi');

        $this->actingAs($this->admin())->get(route('denah', ['tampilan' => 'blok', 'susun' => 1]))
            ->assertOk()->assertSee('Mode pindah rumah aktif')->assertSee('data-rumah="'.$r->id.'"', false)->assertSee('data-slot data-blok', false);

        $warga = User::factory()->create(['role' => User::ROLE_WARGA]);
        $this->actingAs($warga)->get(route('denah', ['tampilan' => 'blok', 'susun' => 1]))
            ->assertOk()->assertDontSee('Mode pindah rumah aktif')->assertDontSee('data-slot data-blok', false);
        $this->actingAs($warga)->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $r->id, 'blok_id' => $r->blok_id, 'baris' => 2, 'kolom' => 2])
            ->assertForbidden();
    }

    public function test_pindah_ke_blok_lain_membawa_keluarga(): void
    {
        $a1 = $this->buatWilayah('01', 'A');
        $b1 = $this->buatWilayah('01', 'B');
        $kk = $this->buatKeluarga($a1, 'Pemilik A1');
        $this->actingAs($this->admin());

        // nomor "1" sudah ada di blok B -> diminta nomor baru, disertai saran
        $this->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $a1->id, 'blok_id' => $b1->blok_id, 'baris' => 1, 'kolom' => 3])
            ->assertStatus(422)->assertJson(['nomor_bentrok' => true, 'saran' => '2']);
        $this->assertSame($a1->blok_id, $a1->fresh()->blok_id);

        // petak terisi ditolak
        $this->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $a1->id, 'blok_id' => $b1->blok_id, 'baris' => 1, 'kolom' => 1, 'nomor' => '9'])
            ->assertStatus(422);

        $this->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $a1->id, 'blok_id' => $b1->blok_id, 'baris' => 1, 'kolom' => 3, 'nomor' => '2'])
            ->assertOk()->assertJsonPath('pesan', 'Rumah A-1 dipindah ke Blok B No. 2.');

        $a1->refresh();
        $this->assertSame([$b1->blok_id, '2', 1, 3], [$a1->blok_id, $a1->nomor, $a1->baris, $a1->kolom]);
        $this->assertSame($a1->id, $kk->fresh()->rumah_id);

        // blok A sekarang kosong dan bisa dihapus
        $this->delete(route('blok.destroy', Blok::query()->where('nama', 'A')->first()))->assertRedirect(route('wilayah'));
    }

    public function test_tukar_tempat_di_blok_sama_dan_antar_blok(): void
    {
        $a1 = $this->buatWilayah('01', 'A');       // A-1 (1,1)
        $a2 = $this->buatWilayah('01', 'A');       // A-2 (1,2)
        $b1 = $this->buatWilayah('01', 'B');       // B-1 (1,1)
        $b2 = $this->buatWilayah('01', 'B');       // B-2 (1,2)
        Rumah::query()->whereKey($b2->id)->update(['nomor' => '7']);
        $this->actingAs($this->admin());

        $this->postJson(route('denah.pindah'), ['aksi' => 'tukar', 'rumah_id' => $a1->id, 'target_id' => $a2->id])->assertOk();
        $this->assertSame([1, 2], [$a1->fresh()->baris, $a1->fresh()->kolom]);
        $this->assertSame([1, 1], [$a2->fresh()->baris, $a2->fresh()->kolom]);

        // A-2 <-> B-7 antar blok (nomor tidak bentrok)
        $this->postJson(route('denah.pindah'), ['aksi' => 'tukar', 'rumah_id' => $a2->id, 'target_id' => $b2->id])->assertOk();
        $this->assertSame([$b1->blok_id, '2', 1, 2], [$a2->fresh()->blok_id, $a2->fresh()->nomor, $a2->fresh()->baris, $a2->fresh()->kolom]);
        $this->assertSame([$a1->blok_id, '7', 1, 1], [$b2->fresh()->blok_id, $b2->fresh()->nomor, $b2->fresh()->baris, $b2->fresh()->kolom]);

        // A-1 <-> B-2: nomor "1" sudah dipakai B-1 -> ditolak
        $this->postJson(route('denah.pindah'), ['aksi' => 'tukar', 'rumah_id' => $a1->id, 'target_id' => $a2->id])->assertStatus(422);

        // A-1 <-> B-1: sama-sama nomor 1, bertukar blok tanpa bentrok
        $this->postJson(route('denah.pindah'), ['aksi' => 'tukar', 'rumah_id' => $a1->id, 'target_id' => $b1->id])->assertOk();
        $this->assertSame([$b1->blok_id, '1'], [$a1->fresh()->blok_id, $a1->fresh()->nomor]);
        $this->assertSame([$a2->blok_id, '1'], [$b1->fresh()->blok_id, $b1->fresh()->nomor]);
    }

    public function test_pengurus_rt_tidak_bisa_memindah_ke_rt_lain(): void
    {
        $r1 = $this->buatWilayah('01', 'A');
        $r2 = $this->buatWilayah('02', 'C');
        $this->actingAs(User::factory()->pengurusRt($r1->blok->rt_id)->create());

        $this->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $r1->id, 'blok_id' => $r2->blok_id, 'baris' => 2, 'kolom' => 2])->assertForbidden();
        $this->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $r2->id, 'blok_id' => $r1->blok_id, 'baris' => 2, 'kolom' => 2])->assertForbidden();
        $this->postJson(route('denah.pindah'), ['aksi' => 'pindah', 'rumah_id' => $r1->id, 'blok_id' => $r1->blok_id, 'baris' => 2, 'kolom' => 2])->assertOk();
    }
}
