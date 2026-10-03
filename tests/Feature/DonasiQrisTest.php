<?php

namespace Tests\Feature;

use App\Models\Donasi;
use App\Models\DonasiDonatur;
use App\Models\Pembayaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DonasiQrisTest extends TestCase
{
    use Concerns, RefreshDatabase;

    private ?string $orderId = null;

    private function fakeAino(string $statusInquiry = 'paid', ?int $nominalInquiry = null): void
    {
        Http::fake([
            'aino.test/payment/v1/request' => function (Request $r) {
                $this->orderId = $r['transaction_details']['order_id'];

                return Http::response([
                    'responseCode' => '2004700', 'responseMessage' => 'Successful',
                    'referenceNo' => '7712026100300001', 'partnerReferenceNo' => $this->orderId,
                    'expiryDate' => now()->addMinutes(15)->toIso8601String(), 'paymentType' => 'qr',
                    'amount' => ['value' => $r['transaction_details']['gross_amount'], 'currency' => 'IDR'],
                    'paymentContent' => '00020101021226570011ID.DANA.WWW0118936009150638993912',
                ]);
            },
            'aino.test/payment/v1/inquiry' => fn (Request $r) => Http::response([
                'responseCode' => '2005500', 'responseMessage' => 'Successful',
                'referenceNumber' => '7712026100300001', 'partnerReferenceNumber' => $r['order_id'],
                'amount' => ['value' => $nominalInquiry ?? Pembayaran::query()->where('order_id', $r['order_id'])->value('total'), 'currency' => 'IDR'],
                'transactionStatusDesc' => $statusInquiry,
                'paidTime' => $statusInquiry === 'paid' ? now()->toIso8601String() : null,
            ]),
        ]);
    }

    private function donasi(array $atribut = []): Donasi
    {
        return Donasi::query()->create($atribut + ['judul' => 'Renovasi Pos', 'publik' => true, 'aktif' => true, 'terima_qris' => true, 'target' => 1000000]);
    }

    public function test_tamu_donasi_qris_sampai_tercatat_sebagai_donatur(): void
    {
        config(['aino.biaya_persen' => 1]);
        $this->fakeAino();
        $d = $this->donasi(['tampilkan_total' => false]);

        $this->get(route('publik.donasi', $d))->assertOk()->assertSee('Lanjut bayar dengan QRIS');

        $this->post(route('publik.donasi.qris', $d), ['nominal' => '50.000', 'nama' => 'Pak Budi', 'pesan' => 'Semoga lancar'])
            ->assertRedirect();
        $p = Pembayaran::query()->where('order_id', $this->orderId)->firstOrFail();
        $this->assertSame($d->id, $p->donasi_id);
        $this->assertNull($p->kartu_keluarga_id);
        $this->assertSame(50000, $p->jumlah_iuran);
        $this->assertSame(50500, $p->total);   // + biaya layanan 1%

        $this->get(route('publik.donasi.bayar', $p))->assertOk()->assertSee('Scan untuk berdonasi')->assertSee('Rp 50.500');

        // Finish Notify dari AINO -> diverifikasi lewat inquiry -> donatur tercatat
        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'referenceNo' => '7712026100300001', 'statusCode' => 3])->assertOk();
        $this->assertTrue($p->fresh()->isPaid());

        $donatur = DonasiDonatur::query()->where('pembayaran_id', $p->id)->firstOrFail();
        $this->assertSame('Pak Budi', $donatur->nama);
        $this->assertSame(50000, $donatur->nominal);   // tanpa biaya layanan
        $this->assertFalse($donatur->anonim);
        $this->assertSame(50000, $d->fresh()->terkumpul);

        // notifikasi berulang tidak menggandakan donatur
        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'statusCode' => 3])->assertOk();
        $this->getJson(route('publik.donasi.status', $p))->assertOk()->assertJson(['status' => 'paid']);
        $this->assertSame(1, DonasiDonatur::query()->count());

        // halaman umum: nama tampil, nominal tidak
        $this->get(route('publik.donasi', $d))->assertSee('Pak Budi')->assertDontSee('50.000');
    }

    public function test_donasi_anonim_dan_warga_login(): void
    {
        $this->fakeAino();
        $d = $this->donasi();
        $kk = $this->buatKeluarga(null, 'Sutrisno Raharjo');
        $warga = User::factory()->warga($kk->id)->create();

        $this->actingAs($warga)->post(route('publik.donasi.qris', $d), ['nominal' => '25000', 'anonim' => '1'])->assertRedirect();
        $p = Pembayaran::query()->where('order_id', $this->orderId)->firstOrFail();
        $this->assertSame($kk->id, $p->kartu_keluarga_id);
        $this->assertSame('Kel. Sutrisno Raharjo', $p->donatur_nama);

        $this->getJson(route('publik.donasi.status', $p))->assertJson(['status' => 'paid']);
        $donatur = DonasiDonatur::query()->firstOrFail();
        $this->assertTrue($donatur->anonim);
        auth()->logout();
        $this->get(route('publik.donasi', $d))->assertSee('Donatur anonim')->assertDontSee('Sutrisno Raharjo');

        // transaksi donasi tidak tercampur dengan riwayat/rekap iuran
        $this->actingAs($warga)->get(route('iuran.saya'))->assertOk();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('iuran.transaksi'))->assertOk()->assertDontSee(substr($p->order_id, 0, 8));
        $this->get(route('donasi.show', $d))->assertOk()->assertSee('Transaksi QRIS');
    }

    public function test_validasi_dan_donasi_tertutup(): void
    {
        $this->fakeAino();
        $d = $this->donasi();

        $this->post(route('publik.donasi.qris', $d), ['nominal' => '5000'])->assertSessionHasErrors('nominal');
        $this->assertSame(0, Pembayaran::query()->count());

        $d->update(['aktif' => false]);
        $this->post(route('publik.donasi.qris', $d), ['nominal' => '50000'])->assertSessionHasErrors('nominal');
        $this->get(route('publik.donasi', $d))->assertDontSee('Lanjut bayar dengan QRIS');

        $tanpaQris = $this->donasi(['judul' => 'Tanpa QRIS', 'terima_qris' => false]);
        $this->post(route('publik.donasi.qris', $tanpaQris), ['nominal' => '50000'])->assertSessionHasErrors('nominal');

        $internal = $this->donasi(['judul' => 'Internal', 'publik' => false]);
        $this->post(route('publik.donasi.qris', $internal), ['nominal' => '50000'])->assertNotFound();
        $this->assertSame(0, Pembayaran::query()->count());
    }

    public function test_mode_uji_coba_boleh_rp1(): void
    {
        $this->fakeAino();
        $d = $this->donasi();

        $this->post(route('publik.donasi.qris', $d), ['nominal' => '1'])->assertSessionHasErrors('nominal');
        $this->get(route('publik.donasi', $d))->assertDontSee('Rp 1 · uji');

        config(['aino.donasi_uji' => true]);
        $this->get(route('publik.donasi', $d))->assertSee('Rp 1 · uji')->assertSee('Bayar langsung dengan QRIS');
        $this->post(route('publik.donasi.qris', $d), ['nominal' => '1'])->assertRedirect();
        $this->assertSame(1, Pembayaran::query()->firstOrFail()->total);
    }

    public function test_nominal_tidak_cocok_tidak_mencatat_donatur(): void
    {
        $this->fakeAino('paid', 1);
        $d = $this->donasi();
        $this->post(route('publik.donasi.qris', $d), ['nominal' => '50000'])->assertRedirect();
        $p = Pembayaran::query()->firstOrFail();

        $this->postJson(route('aino.notify'), ['orderId' => $p->order_id, 'statusCode' => 3])->assertOk();
        $this->assertTrue($p->fresh()->isPending());
        $this->assertSame(0, DonasiDonatur::query()->count());
    }

    public function test_halaman_bayar_iuran_tidak_bisa_dibuka_lewat_rute_donasi(): void
    {
        $kk = $this->buatKeluarga();
        $p = Pembayaran::query()->create([
            'kartu_keluarga_id' => $kk->id, 'order_id' => (string) \Illuminate\Support\Str::uuid(),
            'jumlah_iuran' => 1000, 'total' => 1000, 'status' => 'pending',
        ]);

        $this->get(route('publik.donasi.bayar', $p))->assertNotFound();
        $this->getJson(route('publik.donasi.status', $p))->assertNotFound();
    }

    public function test_respons_aino_tanpa_tanda_hubung_dan_status_teks_lain_tetap_lunas(): void
    {
        Http::fake([
            'aino.test/payment/v1/request' => function (Request $r) {
                $this->orderId = $r['transaction_details']['order_id'];

                return Http::response(['responseCode' => '2004700', 'paymentContent' => '000201',
                    'data' => ['referenceNo' => 'REFX'], 'expiryDate' => now()->addMinutes(15)->toIso8601String()]);
            },
            // inquiry: kode 2005100, status "Payment Success", order id tanpa tanda hubung, nominal string desimal
            'aino.test/payment/v1/inquiry' => fn (Request $r) => Http::response([
                'responseCode' => '2005100', 'responseMessage' => 'Successful',
                'data' => ['partnerReferenceNo' => strtoupper(str_replace('-', '', $r['order_id'])), 'transactionStatus' => 'Payment Success',
                    'amount' => ['value' => '25000.00', 'currency' => 'IDR']],
            ]),
        ]);
        $d = $this->donasi();

        $this->post(route('publik.donasi.qris', $d), ['nominal' => '25000', 'nama' => 'Bu Ani'])->assertRedirect();
        $p = Pembayaran::query()->firstOrFail();
        $this->assertSame('REFX', $p->reference_no);

        $this->getJson(route('publik.donasi.status', $p))->assertJson(['status' => 'paid']);
        $this->assertSame('Bu Ani', DonasiDonatur::query()->firstOrFail()->nama);
    }

    public function test_pengurus_bisa_menandai_berhasil_manual(): void
    {
        $d = $this->donasi();
        $p = Pembayaran::query()->create(['donasi_id' => $d->id, 'donatur_nama' => 'Pak Dedi', 'order_id' => (string) \Illuminate\Support\Str::uuid(),
            'jumlah_iuran' => 40000, 'total' => 40000, 'status' => 'expired']);

        $warga = User::factory()->warga($this->buatKeluarga()->id)->create();
        $this->actingAs($warga)->post(route('donasi.qris.berhasil', $p))->assertForbidden();

        $this->actingAs($this->admin())->post(route('donasi.qris.berhasil', $p))->assertSessionHas('sukses');
        $this->assertTrue($p->fresh()->isPaid());
        $this->assertSame(40000, DonasiDonatur::query()->where('pembayaran_id', $p->id)->value('nominal'));
    }
}
