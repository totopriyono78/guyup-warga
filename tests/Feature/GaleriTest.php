<?php

namespace Tests\Feature;

use App\Models\Galeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GaleriTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_pengurus_membuat_album_dan_mengunggah_foto(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('galeri.store'), [
            'judul' => 'Kerja Bakti', 'tanggal' => now()->toDateString(), 'publik' => '1',
            'fotos' => [UploadedFile::fake()->image('a.jpg', 2400, 1600), UploadedFile::fake()->image('b.jpg', 800, 600)],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $g = Galeri::query()->firstOrFail();
        $this->assertCount(2, $g->fotos);
        Storage::disk('public')->assertExists($g->fotos->first()->path);

        $this->post(route('galeri.foto.store', $g), ['fotos' => [UploadedFile::fake()->image('c.jpg')]])->assertSessionHasNoErrors();
        $this->assertSame(3, $g->fotos()->count());
        $this->assertSame(3, $g->fotos()->get()->last()->urutan);

        $foto = $g->fotos()->get()->last();
        $this->put(route('galeri.foto.update', $foto), ['sampul' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($foto->id, $g->fresh()->sampul_id);

        $this->put(route('galeri.foto.update', $foto), ['keterangan' => 'Gotong royong'])->assertSessionHasNoErrors();
        $this->assertSame('Gotong royong', $foto->fresh()->keterangan);

        $this->get(route('galeri.show', $g))->assertOk()->assertSee('Gotong royong');

        $this->delete(route('galeri.foto.destroy', $foto));
        $this->assertNull($g->fresh()->sampul_id);
        Storage::disk('public')->assertMissing($foto->path);
    }

    public function test_warga_hanya_melihat_dan_umum_hanya_album_publik(): void
    {
        $admin = $this->admin();
        $publik = Galeri::query()->create(['user_id' => $admin->id, 'judul' => 'Lomba Agustusan', 'publik' => true]);
        $publik->fotos()->create(['path' => 'galeri/x.jpg']);
        $internal = Galeri::query()->create(['user_id' => $admin->id, 'judul' => 'Rapat Internal Pengurus', 'publik' => false]);
        $internal->fotos()->create(['path' => 'galeri/y.jpg']);

        // tamu
        $this->get(route('publik.galeri'))->assertOk()->assertSee('Lomba Agustusan')->assertDontSee('Rapat Internal Pengurus');
        $this->get(route('publik.galeri.show', $publik))->assertOk();
        $this->get(route('publik.galeri.show', $internal))->assertNotFound();
        $this->get('/')->assertOk()->assertSee('Lomba Agustusan');
        $this->get(route('galeri.index'))->assertRedirect(route('login'));

        // warga login melihat semua album, tetapi tidak bisa mengelola
        $warga = User::factory()->create(['kartu_keluarga_id' => $this->buatKeluarga()->id]);
        $this->actingAs($warga)->get(route('galeri.index'))->assertOk()->assertSee('Rapat Internal Pengurus');
        $this->get(route('galeri.show', $internal))->assertOk();
        $this->get(route('galeri.create'))->assertForbidden();
        $this->post(route('galeri.foto.store', $publik), ['fotos' => [UploadedFile::fake()->image('z.jpg')]])->assertForbidden();
    }

    public function test_pengurus_rt_hanya_mengelola_album_rt_nya(): void
    {
        Storage::fake('public');
        $r1 = $this->buatWilayah('03');
        $r2 = $this->buatWilayah('04', 'B');
        $rt1 = User::factory()->pengurusRt($r1->blok->rt_id)->create();

        $this->actingAs($rt1)->post(route('galeri.store'), ['judul' => 'Posyandu', 'rt_id' => $r2->blok->rt_id])->assertRedirect();
        $g = Galeri::query()->firstOrFail();
        $this->assertSame($r1->blok->rt_id, $g->rt_id);

        $rw = Galeri::query()->create(['judul' => 'Kegiatan RW', 'publik' => true]);
        $this->get(route('galeri.edit', $rw))->assertForbidden();
        $this->delete(route('galeri.destroy', $rw))->assertForbidden();
        $this->delete(route('galeri.destroy', $g))->assertRedirect(route('galeri.index'));
    }
}
