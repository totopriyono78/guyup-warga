<?php

namespace Tests\Feature;

use App\Models\Donasi;
use App\Models\DonasiDonatur;
use App\Models\Pembayaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalangDanaWargaTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_warga_melihat_donasi_sesuai_cakupan_dan_riwayatnya(): void
    {
        $rumah1 = $this->buatWilayah('03');
        $rumah2 = $this->buatWilayah('04', 'B');
        $kk = $this->buatKeluarga($rumah1, 'Joko Susilo');
        $warga = User::factory()->warga($kk->id)->create();

        $publik = Donasi::query()->create(['judul' => 'Renovasi Pos Umum', 'publik' => true, 'aktif' => true]);
        $rw = Donasi::query()->create(['judul' => 'Kas Sosial RW Internal', 'publik' => false, 'aktif' => true]);
        $rtSendiri = Donasi::query()->create(['judul' => 'Santunan RT Tiga', 'publik' => false, 'aktif' => true, 'rt_id' => $rumah1->blok->rt_id]);
        $rtLain = Donasi::query()->create(['judul' => 'Santunan RT Empat', 'publik' => false, 'aktif' => true, 'rt_id' => $rumah2->blok->rt_id]);

        // riwayat: satu QRIS lunas + satu dicatat pengurus
        $p = Pembayaran::query()->create([
            'kartu_keluarga_id' => $kk->id, 'user_id' => $warga->id, 'donasi_id' => $publik->id, 'donatur_nama' => 'Kel. Joko Susilo',
            'order_id' => (string) Str::uuid(), 'jumlah_iuran' => 75000, 'total' => 75000, 'status' => 'paid', 'paid_at' => now(),
        ]);
        DonasiDonatur::query()->create(['donasi_id' => $publik->id, 'pembayaran_id' => $p->id, 'nama' => 'Kel. Joko Susilo', 'nominal' => 75000, 'tanggal' => now(), 'kartu_keluarga_id' => $kk->id]);
        DonasiDonatur::query()->create(['donasi_id' => $rw->id, 'nama' => 'Kel. Joko Susilo', 'nominal' => 20000, 'tanggal' => now(), 'kartu_keluarga_id' => $kk->id]);

        $this->actingAs($warga)->get(route('galang.index'))->assertOk()
            ->assertSee('Renovasi Pos Umum')->assertSee('Kas Sosial RW Internal')->assertSee('Santunan RT Tiga')
            ->assertDontSee('Santunan RT Empat')
            ->assertSee('Donasi saya')->assertSee('75.000')->assertSee('20.000');

        $this->get(route('galang.show', $rtSendiri))->assertOk();
        $this->get(route('galang.show', $publik))->assertOk()->assertSee('Keluarga Anda')->assertSee('Donasi saya di program ini');
        $this->get(route('galang.show', $rtLain))->assertNotFound();

        $this->get(route('dashboard'))->assertOk()->assertSee('Galang dana')->assertSee('Renovasi Pos Umum');
        $this->get(route('dashboard'))->assertDontSee('Santunan RT Empat');

        // menu warga ada "Galang Dana", tetapi tidak ada menu kelola
        $this->get(route('galang.index'))->assertSee(route('galang.index'))->assertDontSee('Kelola Galang Dana');
        $this->get(route('donasi.index'))->assertForbidden();
    }

    public function test_warga_bisa_donasi_qris_untuk_program_internal_cakupannya(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'aino.test/payment/v1/request' => fn ($r) => \Illuminate\Support\Facades\Http::response([
                'responseCode' => '2004700', 'referenceNo' => 'REF1', 'partnerReferenceNo' => $r['transaction_details']['order_id'],
                'expiryDate' => now()->addMinutes(15)->toIso8601String(), 'paymentType' => 'qr',
                'paymentContent' => '000201010212', 'amount' => ['value' => $r['transaction_details']['gross_amount']],
            ]),
        ]);
        $rumah1 = $this->buatWilayah('03');
        $rumah2 = $this->buatWilayah('04', 'B');
        $warga = User::factory()->warga($this->buatKeluarga($rumah1)->id)->create();
        $rtSendiri = Donasi::query()->create(['judul' => 'Internal RT 03', 'publik' => false, 'aktif' => true, 'terima_qris' => true, 'rt_id' => $rumah1->blok->rt_id]);
        $rtLain = Donasi::query()->create(['judul' => 'Internal RT 04', 'publik' => false, 'aktif' => true, 'terima_qris' => true, 'rt_id' => $rumah2->blok->rt_id]);

        $this->actingAs($warga)->get(route('galang.show', $rtSendiri))->assertSee('Lanjut bayar dengan QRIS');
        $this->post(route('publik.donasi.qris', $rtSendiri), ['nominal' => '20000'])->assertRedirect();
        $this->post(route('publik.donasi.qris', $rtLain), ['nominal' => '20000'])->assertNotFound();
        $this->assertSame(1, Pembayaran::query()->count());

        // halaman bayar mengarahkan kembali ke halaman galang dana di aplikasi
        $p = Pembayaran::query()->firstOrFail();
        $this->get(route('publik.donasi.bayar', $p))->assertOk()->assertSee(route('galang.show', $rtSendiri));
        $this->get(route('galang.index'))->assertSee('Internal RT 03')->assertSee('20.000');

        // tamu tidak bisa berdonasi ke program internal
        auth()->logout();
        $this->post(route('publik.donasi.qris', $rtSendiri), ['nominal' => '20000'])->assertNotFound();
    }

    public function test_admin_melihat_semua_dan_transaksi(): void
    {
        $r2 = $this->buatWilayah('04', 'B');
        $admin = $this->admin();
        $d = Donasi::query()->create(['judul' => 'Internal RT 04', 'publik' => false, 'aktif' => true, 'rt_id' => $r2->blok->rt_id]);
        Pembayaran::query()->create(['donasi_id' => $d->id, 'donatur_nama' => 'Bu Sri', 'order_id' => (string) Str::uuid(), 'jumlah_iuran' => 30000, 'total' => 30000, 'status' => 'pending', 'expired_at' => now()->addMinutes(10)]);

        $this->actingAs($admin)->get(route('galang.index'))->assertOk()->assertSee('Internal RT 04');
        $this->get(route('galang.show', $d))->assertOk()->assertSee('Kelola');
        $this->get(route('donasi.show', $d))->assertOk()->assertSee('Transaksi QRIS')->assertSee('Bu Sri')->assertSee('Menunggu pembayaran');
        $this->get(route('dashboard'))->assertSee('Internal RT 04');
    }
}
