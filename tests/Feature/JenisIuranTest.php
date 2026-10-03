<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\TarifIuran;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JenisIuranTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_admin_membuat_jenis_bulanan_tahunan_insidental(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('tarif.store'), ['nama' => 'Kebersihan', 'frekuensi' => 'bulanan', 'nominal' => 20000])->assertSessionHasNoErrors();
        $this->post(route('tarif.store'), ['nama' => 'Tahunan', 'frekuensi' => 'tahunan', 'nominal' => 100000, 'bulan_tagih' => 3])->assertSessionHasNoErrors();
        $this->post(route('tarif.store'), ['nama' => 'Tahunan tanpa bulan', 'frekuensi' => 'tahunan', 'nominal' => 100000])->assertSessionHasErrors('bulan_tagih');
        $this->post(route('tarif.store'), ['nama' => 'HUT RI', 'frekuensi' => 'insidental', 'nominal' => 0, 'sukarela' => 1, 'tenggat' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $this->post(route('tarif.store'), ['nama' => 'Nol wajib', 'frekuensi' => 'bulanan', 'nominal' => 0])->assertSessionHasErrors('nominal');

        $this->assertSame(3, TarifIuran::query()->count());
        $this->get(route('tarif.index'))->assertOk()->assertSee('HUT RI')->assertSee('Sukarela');
    }

    public function test_tahunan_hanya_terbit_di_bulan_tagih_dan_insidental_lewat_tombol(): void
    {
        $kk = $this->buatKeluarga();
        $tahunan = TarifIuran::query()->create(['nama' => 'Tahunan', 'nominal' => 100000, 'frekuensi' => 'tahunan', 'bulan_tagih' => 3, 'aktif' => true]);
        $acara = TarifIuran::query()->create(['nama' => 'Renovasi Pos', 'nominal' => 50000, 'frekuensi' => 'insidental', 'tenggat' => '2026-12-31', 'aktif' => true]);
        $service = app(TagihanService::class);

        $this->assertSame(0, $service->generate(\Illuminate\Support\Carbon::create(2026, 2, 1)));
        $this->assertSame(1, $service->generate(\Illuminate\Support\Carbon::create(2026, 3, 1)));
        $this->assertSame('Tahunan 2026', Tagihan::query()->where('tarif_iuran_id', $tahunan->id)->value('judul'));

        // insidental tidak ikut generate bulanan
        $this->assertSame(0, Tagihan::query()->where('tarif_iuran_id', $acara->id)->count());

        $this->actingAs($this->admin())->post(route('tarif.terbitkan', $acara))->assertSessionHas('sukses');
        $t = Tagihan::query()->where('tarif_iuran_id', $acara->id)->firstOrFail();
        $this->assertSame('Renovasi Pos', $t->judul);
        $this->assertSame('2026-12-31', $t->jatuh_tempo->toDateString());
        $this->assertNotNull($acara->fresh()->diterbitkan_pada);

        // terbitkan ulang tidak menggandakan
        $this->post(route('tarif.terbitkan', $acara));
        $this->assertSame(1, Tagihan::query()->where('tarif_iuran_id', $acara->id)->count());

        $this->get(route('iuran.index', ['jenis' => $acara->id]))->assertOk()->assertSee($kk->nama_kepala)->assertSee('Renovasi Pos');
        $this->get(route('iuran.export', ['jenis' => $acara->id]))->assertOk();
    }

    public function test_pengurus_rt_tidak_bisa_mengubah_jenis_iuran_rw_tapi_bisa_menerbitkan_untuk_rt_nya(): void
    {
        $kkRt1 = $this->buatKeluarga($this->buatWilayah('01', 'A'));
        $kkRt2 = $this->buatKeluarga($this->buatWilayah('02', 'C'));
        $acara = TarifIuran::query()->create(['nama' => 'Acara RW', 'nominal' => 20000, 'frekuensi' => 'insidental', 'aktif' => true]);
        $this->actingAs(User::factory()->pengurusRt($kkRt1->rumah->blok->rt_id)->create());

        $this->put(route('tarif.update', $acara), ['nama' => 'X', 'frekuensi' => 'insidental', 'nominal' => 1])->assertForbidden();
        $this->post(route('tarif.terbitkan', $acara))->assertSessionHas('sukses');

        $this->assertTrue(Tagihan::query()->where('kartu_keluarga_id', $kkRt1->id)->exists());
        $this->assertFalse(Tagihan::query()->where('kartu_keluarga_id', $kkRt2->id)->exists());
    }

    public function test_hapus_jenis_yang_sudah_ada_tagihan_hanya_menonaktifkan(): void
    {
        $this->buatKeluarga();
        $jenis = $this->tarif(20000);
        app(TagihanService::class)->generate(now());

        $this->actingAs($this->admin())->delete(route('tarif.destroy', $jenis))->assertSessionHas('sukses');
        $this->assertFalse($jenis->fresh()->aktif);
        $this->assertSame(1, Tagihan::query()->count());
    }

    public function test_bayar_sukarela_via_qris_dan_tunai(): void
    {
        $kk = $this->buatKeluarga();
        $warga = User::factory()->warga($kk->id)->create();
        $acara = TarifIuran::query()->create(['nama' => 'HUT RI', 'nominal' => 25000, 'frekuensi' => 'insidental', 'sukarela' => true, 'aktif' => true]);
        app(TagihanService::class)->terbitkan($acara);
        $t = Tagihan::query()->firstOrFail();
        $this->assertTrue($t->sukarela);

        Http::fake(['aino.test/payment/v1/request' => fn (Request $r) => Http::response([
            'responseCode' => '2004700', 'responseMessage' => 'Successful', 'referenceNo' => 'R1',
            'partnerReferenceNo' => $r['transaction_details']['order_id'], 'expiryDate' => now()->addMinutes(15)->toIso8601String(),
            'paymentType' => 'qr', 'amount' => ['value' => $r['transaction_details']['gross_amount'], 'currency' => 'IDR'], 'paymentContent' => '000201',
        ])]);

        $this->actingAs($warga)->get(route('iuran.saya'))->assertOk()->assertSee('HUT RI')->assertSee('isi nominal sendiri');

        // di bawah minimal ditolak
        $this->from(route('iuran.saya'))->post(route('pembayaran.store'), ['tagihan' => [$t->id], 'jumlah' => [$t->id => '10000']])->assertSessionHas('gagal');
        $this->assertSame(0, Pembayaran::query()->count());

        // nominal pilihan warga dipakai sebagai total QRIS
        $this->post(route('pembayaran.store'), ['tagihan' => [$t->id], 'jumlah' => [$t->id => '75.000']])->assertRedirect();
        Http::assertSent(fn (Request $r) => $r['transaction_details']['gross_amount'] === 75000);
        $p = Pembayaran::query()->firstOrFail();
        $this->assertSame(75000, (int) $p->tagihans()->first()->pivot->nominal);

        // bila lunas lewat AINO, nominal_dibayar = 75.000
        app(\App\Services\PembayaranService::class)->terapkan($p, 'paid');
        $this->assertSame(75000, $t->fresh()->nominal_dibayar);
        $this->assertSame(75000, $t->fresh()->nominal_masuk);

        // tunai sukarela wajib isi nominal
        $t2 = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id, 'tarif_iuran_id' => $acara->id, 'periode' => now()->startOfMonth()->addMonthNoOverflow(), 'nominal' => 25000, 'sukarela' => true]);
        $this->actingAs($this->admin())->post(route('iuran.lunas', $t2), ['metode' => 'tunai'])->assertSessionHasErrors('nominal');
        $this->post(route('iuran.lunas', $t2), ['metode' => 'tunai', 'nominal' => 40000])->assertSessionHas('sukses');
        $this->assertSame(40000, $t2->fresh()->nominal_dibayar);
    }

    public function test_tagihan_lama_gabungan_tidak_digandakan_dan_sukarela_bukan_tunggakan(): void
    {
        $kk = $this->buatKeluarga();
        // tagihan versi lama (sebelum jenis iuran): tanpa tarif_iuran_id
        Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id, 'periode' => now()->startOfMonth(), 'nominal' => 50000]);
        $this->tarif(20000);

        $this->assertSame(0, app(TagihanService::class)->generate(now()));
        $this->assertSame(1, app(TagihanService::class)->generate(now()->startOfMonth()->addMonthNoOverflow()));

        $sukarela = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id, 'periode' => now()->startOfMonth()->subYear(), 'nominal' => 0, 'sukarela' => true, 'judul' => 'Donasi lama']);
        $this->assertFalse($sukarela->terlambat());
    }
}
