<?php

namespace Tests\Feature;

use App\Support\Pasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IdentitasWilayahTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_admin_mengatur_identitas_wilayah(): void
    {
        $this->actingAs($this->admin())->put(route('pengaturan.update'), [
            'nama_rw' => 'RW 02', 'dusun' => 'Sidorejo', 'kelurahan' => 'Selomartani',
            'kecamatan' => 'Kalasan', 'kabupaten' => 'Sleman', 'provinsi' => 'D.I. Yogyakarta',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Dusun Sidorejo, Kel. Selomartani, Kec. Kalasan, Kab. Sleman, D.I. Yogyakarta', wilayah());
        $this->assertSame('Dusun Sidorejo · Selomartani', wilayah('singkat'));

        auth()->logout();
        $this->get('/')->assertSee('RW 02')->assertSee('Dusun Sidorejo, Kel. Selomartani');
        $this->get('/login')->assertSee('RW 02');

        // Kolom boleh dikosongkan (tidak kembali ke nilai bawaan)
        $this->actingAs($this->admin())->put(route('pengaturan.update'), ['nama_rw' => 'RW 02', 'dusun' => '', 'kelurahan' => 'Selomartani'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Kel. Selomartani', wilayah());
    }

    public function test_admin_menambah_dan_menghapus_rt(): void
    {
        $this->actingAs($this->admin())->post(route('rt.store'), ['nomor' => '6'])->assertSessionHasNoErrors();
        $rt = \App\Models\Rt::query()->where('nomor', '06')->firstOrFail();

        $this->delete(route('rt.destroy', $rt))->assertSessionHas('sukses');
        $this->assertModelMissing($rt);
    }

    public function test_hari_pasaran(): void
    {
        $this->assertSame('Jumat Legi', Pasaran::hariPasaran(Carbon::create(1945, 8, 17)));
        $this->assertSame('Kamis Pon', Pasaran::hariPasaran(Carbon::create(1901, 6, 6)));
        $this->assertSame('Sabtu Pon', Pasaran::hariPasaran(Carbon::create(2026, 10, 3)));

        $mp = Pasaran::berikutnya('Minggu', 'Pahing', Carbon::create(2026, 10, 3));
        $this->assertSame('Minggu Pahing', Pasaran::hariPasaran($mp));
        $this->assertTrue($mp->between(Carbon::create(2026, 10, 3), Carbon::create(2026, 11, 7)));
    }
}
