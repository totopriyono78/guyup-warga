<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien API AINO Payment Gateway — QRIS MPM / Virtual Account.
 *
 *  - generate() : POST /payment/v1/request   (sukses: responseCode 2004700)
 *  - inquiry()  : POST /payment/v1/inquiry   (sukses: responseCode 2005500)
 */
class AinoClient
{
    public const KODE_GENERATE_SUKSES = '2004700';
    public const KODE_INQUIRY_SUKSES = '2005500';

    public function __construct(private readonly array $config) {}

    public function dikonfigurasi(): bool
    {
        return filled($this->config['merchant_code'] ?? null) && filled($this->config['secret_key'] ?? null);
    }

    /**
     * Membuat QRIS / VA. Mengembalikan body respons AINO.
     *
     * @throws AinoException
     */
    public function generate(string $orderId, int $grossAmount, string $callbackUrl, ?string $paymentType = null): array
    {
        $payload = [
            'payment_type' => $paymentType ?? ($this->config['payment_type'] ?? 'qr'),
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'callback' => $callbackUrl,
        ];

        $body = $this->kirim($this->config['generate_path'] ?? '/payment/v1/request', $payload, 'generate');

        if ((string) ($body['responseCode'] ?? '') !== self::KODE_GENERATE_SUKSES || blank($body['paymentContent'] ?? null)) {
            throw new AinoException(
                'Gagal membuat QRIS: '.($body['responseMessage'] ?? 'respons tidak dikenal'),
                (string) ($body['responseCode'] ?? ''),
                $body,
            );
        }

        return $body;
    }

    /**
     * Menanyakan status pembayaran ke AINO.
     *
     * @throws AinoException
     */
    public function inquiry(string $orderId, string $referenceNo): array
    {
        $body = $this->kirim($this->config['inquiry_path'] ?? '/payment/v1/inquiry', [
            'order_id' => $orderId,
            'reference_no' => $referenceNo,
        ], 'inquiry');

        $kode = (string) ($body['responseCode'] ?? '');
        // beberapa lingkungan AINO mengirim kode 200xxxx lain untuk inquiry yang berhasil
        $berhasil = $kode === self::KODE_INQUIRY_SUKSES || (str_starts_with($kode, '200') && static::adaStatus($body));
        if (! $berhasil) {
            throw new AinoException(
                'Gagal cek status: '.($body['responseMessage'] ?? 'respons tidak dikenal'),
                (string) ($body['responseCode'] ?? ''),
                $body,
            );
        }

        return $body;
    }

    /**
     * Terjemahkan respons Query Payment menjadi status internal:
     * paid | pending | failed | canceled
     */
    public static function statusDariInquiry(array $body): string
    {
        $data = static::datar($body);

        // 1) Teks status (transactionStatusDesc / statusLabel / transactionStatus / status)
        // (kolom "status" umum sengaja tidak dipakai: sering berarti status panggilan API, bukan status transaksi)
        foreach (['transactionStatusDesc', 'statusLabel', 'transactionStatus', 'paymentStatus'] as $k) {
            $nilai = $data[strtolower($k)] ?? null;
            if (is_string($nilai) && trim($nilai) !== '' && ! is_numeric($nilai)) {
                return static::dariTeks($nilai);
            }
        }

        // 2) statusCode seperti pada Finish Notify: 1 pending, 2 expired, 3 paid, 4 failed, 5 canceled
        $kode = $data['statuscode'] ?? null;
        if (is_scalar($kode) && (string) $kode !== '') {
            return match ((string) $kode) {
                '3' => 'paid', '2' => 'expired', '4' => 'failed', '5' => 'canceled', default => 'pending',
            };
        }

        // 3) Cadangan SNAP: latestTransactionStatus (00/02 sukses, 01 menunggu, 05 batal, 07 tidak ditemukan)
        return match ((string) ($data['latesttransactionstatus'] ?? '')) {
            '00', '02' => 'paid',
            '05' => 'canceled',
            '07' => 'failed',
            default => 'pending',
        };
    }

    private static function dariTeks(string $teks): string
    {
        $t = strtolower(trim($teks));

        return match (true) {
            in_array($t, ['paid', 'success', 'successful', 'settlement', 'settled', 'sukses', 'berhasil', 'lunas', 'completed', 'complete', 'capture'], true),
            str_contains($t, 'success'), str_contains($t, 'paid') && ! str_contains($t, 'unpaid') => 'paid',
            str_contains($t, 'expir'), str_contains($t, 'kedaluwarsa') => 'expired',
            str_contains($t, 'cancel'), str_contains($t, 'batal') => 'canceled',
            in_array($t, ['fail', 'failed', 'failure', 'gagal', 'rejected', 'declined'], true) => 'failed',
            default => 'pending', // status tak dikenal: jangan dianggap final
        };
    }

    /** Ada informasi status di respons? */
    public static function adaStatus(array $body): bool
    {
        $d = static::datar($body);
        foreach (['transactionstatusdesc', 'statuslabel', 'transactionstatus', 'paymentstatus', 'statuscode', 'latesttransactionstatus'] as $k) {
            if (isset($d[$k]) && $d[$k] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Ratakan respons (termasuk objek "data"/"result"/"transaction") menjadi [kunci_kecil => nilai skalar],
     * supaya variasi format respons AINO tetap terbaca. Kunci tingkat atas didahulukan.
     */
    public static function datar(array $body): array
    {
        $hasil = [];
        $tambah = function (array $arr) use (&$hasil) {
            foreach ($arr as $k => $v) {
                if (is_string($k) && (is_scalar($v) || $v === null) && ! array_key_exists(strtolower($k), $hasil)) {
                    $hasil[strtolower($k)] = $v;
                }
            }
        };
        $tambah($body);
        foreach (['data', 'result', 'transaction', 'transactionDetails', 'transaction_details', 'additionalInfo'] as $sub) {
            if (isset($body[$sub]) && is_array($body[$sub])) {
                $tambah($body[$sub]);
            }
        }

        return $hasil;
    }

    /** Nominal dari objek amount {value, currency}. */
    public static function nominal(array $body): ?int
    {
        foreach ([$body, $body['data'] ?? [], $body['result'] ?? []] as $sumber) {
            if (! is_array($sumber)) {
                continue;
            }
            foreach (['amount', 'grossAmount', 'gross_amount', 'paidAmount', 'totalAmount'] as $k) {
                $amount = $sumber[$k] ?? null;
                if (is_array($amount) && isset($amount['value']) && is_numeric($amount['value'])) {
                    return (int) round((float) $amount['value']);
                }
                if (is_numeric($amount)) {
                    return (int) round((float) $amount);
                }
            }
        }

        return null;
    }

    /** referenceNo (dokumen tabel) atau referenceNumber (contoh respons inquiry), juga di dalam "data". */
    public static function referenceNo(array $body): ?string
    {
        $d = static::datar($body);
        $ref = $d['referenceno'] ?? $d['referencenumber'] ?? $d['reference_no'] ?? null;

        return is_scalar($ref) && (string) $ref !== '' ? (string) $ref : null;
    }

    /** partnerReferenceNo / order_id yang dikembalikan AINO (bila ada). */
    public static function partnerRef(array $body): ?string
    {
        $d = static::datar($body);
        $ref = $d['partnerreferenceno'] ?? $d['partnerreferencenumber'] ?? $d['orderid'] ?? $d['order_id'] ?? null;

        return is_scalar($ref) && (string) $ref !== '' ? (string) $ref : null;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withBasicAuth((string) $this->config['merchant_code'], (string) $this->config['secret_key'])
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 8))
            ->connectTimeout(5);
    }

    private function kirim(string $path, array $payload, string $aksi): array
    {
        if (! $this->dikonfigurasi()) {
            throw new AinoException('Payment gateway AINO belum dikonfigurasi (AINO_MERCHANT_CODE / AINO_SECRET_KEY).');
        }

        try {
            $response = $this->http()->post($path, $payload);
        } catch (ConnectionException $e) {
            Log::warning("AINO {$aksi}: koneksi gagal", ['error' => $e->getMessage()]);

            throw new AinoException('Tidak dapat terhubung ke payment gateway. Coba lagi beberapa saat.');
        }

        $body = $response->json() ?? [];

        Log::info("AINO {$aksi}", [
            'http' => $response->status(),
            'order_id' => $payload['transaction_details']['order_id'] ?? $payload['order_id'] ?? null,
            'responseCode' => $body['responseCode'] ?? null,
            'responseMessage' => $body['responseMessage'] ?? null,
            // isi respons lengkap (tanpa string QR yang panjang) untuk diagnosis
            'body' => is_array($body) ? array_diff_key($body, ['paymentContent' => 1]) : $response->body(),
        ]);

        if (! is_array($body) || $body === []) {
            throw new AinoException("Respons AINO tidak valid (HTTP {$response->status()}).");
        }

        return $body;
    }
}
