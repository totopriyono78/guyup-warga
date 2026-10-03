<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IuranAinoTest extends TestCase
{
    use Concerns, RefreshDatabase;

    private function responGenerate(string $orderId, int $amount): array
    {
        return [
            'responseCode' => '2004700',
            'responseMessage' => 'Successful',
            'referenceNo' => '61120260208201224',
            'partnerReferenceNo' => $orderId,
            'expiryDate' => now()->addMinutes(15)->toIso8601String(),
            'paymentType' => 'qr',
            'amount' => ['value' => $amount, 'currency' => 'IDR'],
            'paymentContent' => '00020101021226570011ID.DANA.WWW0118936009150638993912',
        ];
    }

    private function responInquiry(string $orderId, int $amount, string $status): array
    {
        return [
            'responseCode' => '2005500',
            'responseMessage' => 'Successful',
            'referenceNumber' => '61120260208201224',
            'partnerReferenceNumber' => $orderId,
            'amount' => ['value' => $amount, 'currency' => 'IDR'],
            'transactionStatusDesc' => $status,
            'paidTime' => $status === 'paid' ? now()->toIso8601String() : null,
        ];
    }

    public function test_generate_tagihan_per_jenis_rw_dan_rt_serta_idempoten(): void
    {
        $kk1 = $this->buatKeluarga($this->buatWilayah('01'));
        $kk2 = $this->buatKeluarga($this->buatWilayah('02', 'C'));
        $this->tarif(30000);
        $this->tarif(10000, $kk1->rumah->blok->rt_id);

        $service = app(TagihanService::class);
        $this->assertSame(3, $service->generate(now()));
        $this->assertSame(0, $service->generate(now()));

        $this->assertSame(40000, (int) Tagihan::query()->where('kartu_keluarga_id', $kk1->id)->sum('nominal'));
        $this->assertSame(2, Tagihan::query()->where('kartu_keluarga_id', $kk1->id)->count());
        $this->assertSame(30000, (int) Tagihan::query()->where('kartu_keluarga_id', $kk2->id)->sum('nominal'));
        $this->assertStringContainsString('Iuran bulanan', Tagihan::query()->where('kartu_keluarga_id', $kk2->id)->value('judul'));
    }

    public function test_alur_bayar_qris_sampai_lunas_lewat_finish_notify(): void
    {
        $kk = $this->buatKeluarga();
        $warga = User::factory()->warga($kk->id)->create();
        $t1 = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id, 'periode' => now()->startOfMonth()->subMonthNoOverflow(), 'nominal' => 50000]);
        $t2 = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id, 'periode' => now()->startOfMonth(), 'nominal' => 50000]);

        $orderId = null;
        Http::fake([
            'aino.test/payment/v1/request' => function (Request $r) use (&$orderId) {
                $orderId = $r['transaction_details']['order_id'];

                return Http::response($this->responGenerate($orderId, $r['transaction_details']['gross_amount']));
            },
            'aino.test/payment/v1/inquiry' => fn (Request $r) => Http::response($this->responInquiry($r['order_id'], 100000, 'paid')),
        ]);

        $this->actingAs($warga)->post(route('pembayaran.store'), ['tagihan' => [$t1->id, $t2->id]])->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/payment/v1/request')
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('TEST_MERCHANT:test-secret'))
            && $r['payment_type'] === 'qr'
            && $r['transaction_details']['gross_amount'] === 100000
            && filled($r['callback']));

        $p = Pembayaran::query()->firstOrFail();
        $this->assertSame($orderId, $p->order_id);
        $this->assertSame('pending', $p->status);
        $this->assertNotEmpty($p->payment_content);
        $this->get(route('pembayaran.show', $p))->assertOk()->assertSee('Rp 100.000');

        // AINO mengirim Finish Notify
        $this->postJson(route('aino.notify'), [
            'referenceNo' => '61120260208201224', 'acquireReferenceNo' => 'ACQ1', 'orderId' => $p->order_id,
            'orderDate' => now()->format('Y-m-d H:i:s'), 'paymentType' => 'QRIS', 'amount' => '100000',
            'statusCode' => '3', 'statusLabel' => 'paid',
        ])->assertOk()->assertJson(['responseCode' => '200']);

        $this->assertSame('paid', $p->fresh()->status);
        $this->assertTrue($t1->fresh()->isLunas());
        $this->assertTrue($t2->fresh()->isLunas());
        $this->assertSame('qris', $t1->fresh()->metode);

        // notifikasi ganda tetap aman
        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'referenceNo' => '61120260208201224', 'statusCode' => '3'])
            ->assertOk();
    }

    public function test_notify_palsu_tidak_melunasi_bila_query_payment_masih_pending(): void
    {
        [$p, $t] = $this->transaksiPending();
        Http::fake(['aino.test/payment/v1/inquiry' => Http::response($this->responInquiry($p->order_id, 50000, 'pending'))]);

        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'referenceNo' => $p->reference_no, 'statusCode' => '3', 'statusLabel' => 'paid'])
            ->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertFalse($t->fresh()->isLunas());
    }

    public function test_nominal_tidak_cocok_ditolak(): void
    {
        [$p, $t] = $this->transaksiPending();
        Http::fake(['aino.test/payment/v1/inquiry' => Http::response($this->responInquiry($p->order_id, 1, 'paid'))]);

        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'referenceNo' => $p->reference_no])->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertFalse($t->fresh()->isLunas());
    }

    public function test_order_tidak_dikenal_dibalas_400(): void
    {
        $this->postJson(route('aino.notify'), ['orderId' => 'tidak-ada'])->assertStatus(400)->assertJson(['responseCode' => '400']);
    }

    public function test_gagal_generate_menampilkan_pesan(): void
    {
        $kk = $this->buatKeluarga();
        $warga = User::factory()->warga($kk->id)->create();
        $t = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id]);
        Http::fake(['aino.test/*' => Http::response(['responseCode' => '4044708', 'responseMessage' => 'Invalid Merchant'])]);

        $this->actingAs($warga)->from(route('iuran.saya'))->post(route('pembayaran.store'), ['tagihan' => [$t->id]])
            ->assertRedirect(route('iuran.saya'))->assertSessionHas('gagal');

        $this->assertSame(0, Pembayaran::query()->count());
    }

    public function test_warga_tidak_bisa_membayar_tagihan_keluarga_lain(): void
    {
        $kk = $this->buatKeluarga();
        $lain = $this->buatKeluarga(null, 'Lain');
        $warga = User::factory()->warga($kk->id)->create();
        $tLain = Tagihan::factory()->create(['kartu_keluarga_id' => $lain->id]);
        Http::fake();

        $this->actingAs($warga)->post(route('pembayaran.store'), ['tagihan' => [$tLain->id]])->assertSessionHas('gagal');
        Http::assertNothingSent();
    }

    public function test_pengurus_mencatat_bayar_tunai(): void
    {
        $kk = $this->buatKeluarga();
        $t = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id]);

        $this->actingAs($this->admin())->post(route('iuran.lunas', $t), ['metode' => 'tunai'])->assertSessionHas('sukses');
        $this->assertTrue($t->fresh()->isLunas());
        $this->assertSame('tunai', $t->fresh()->metode);

        $this->get(route('iuran.index'))->assertOk()->assertSee($kk->nama_kepala);
        $this->get(route('iuran.export'))->assertOk();
    }

    public function test_qris_aktif_mencegah_qris_ganda_dan_catat_tunai(): void
    {
        [$p, $t] = $this->transaksiPending();
        $t2 = Tagihan::factory()->create(['kartu_keluarga_id' => $p->kartu_keluarga_id, 'periode' => now()->startOfMonth()->addMonthNoOverflow()]);
        $warga = User::factory()->warga($p->kartu_keluarga_id)->create();
        Http::fake();

        // Tagihan yang sama sudah ada di QRIS aktif lain -> diarahkan ke QRIS itu, tidak membuat baru
        $this->actingAs($warga)->post(route('pembayaran.store'), ['tagihan' => [$t->id, $t2->id]])
            ->assertRedirect(route('pembayaran.show', $p))->assertSessionHas('gagal');
        Http::assertNothingSent();
        $this->assertSame(1, Pembayaran::query()->count());

        // Pengurus tidak bisa mencatat tunai selama QRIS aktif
        $this->actingAs($this->admin())->post(route('iuran.lunas', $t), ['metode' => 'tunai'])->assertSessionHas('gagal');
        $this->assertFalse($t->fresh()->isLunas());
    }

    public function test_pembayaran_kedaluwarsa_tetap_bisa_menjadi_lunas_bila_aino_melaporkan_paid(): void
    {
        [$p, $t] = $this->transaksiPending();
        $p->update(['status' => 'expired']);
        Http::fake(['aino.test/payment/v1/inquiry' => Http::response($this->responInquiry($p->order_id, 50000, 'paid'))]);

        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'referenceNo' => $p->reference_no])->assertOk();

        $this->assertSame('paid', $p->fresh()->status);
        $this->assertTrue($t->fresh()->isLunas());
    }

    public function test_status_tidak_dikenal_tidak_dianggap_gagal(): void
    {
        $this->assertSame('pending', \App\Services\AinoClient::statusDariInquiry(['transactionStatusDesc' => 'processing']));
    }

    /** @return array{0: Pembayaran, 1: Tagihan} */
    private function transaksiPending(): array
    {
        $kk = $this->buatKeluarga();
        $t = Tagihan::factory()->create(['kartu_keluarga_id' => $kk->id, 'nominal' => 50000]);
        $p = Pembayaran::query()->create([
            'kartu_keluarga_id' => $kk->id, 'order_id' => (string) \Illuminate\Support\Str::uuid(), 'reference_no' => 'REF123',
            'jumlah_iuran' => 50000, 'total' => 50000, 'status' => 'pending', 'payment_content' => '000201',
            'expired_at' => now()->addMinutes(15),
        ]);
        $p->tagihans()->attach($t->id);

        return [$p, $t];
    }
}
