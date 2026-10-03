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

        if ((string) ($body['responseCode'] ?? '') !== self::KODE_INQUIRY_SUKSES) {
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
        $desc = strtolower((string) ($body['transactionStatusDesc'] ?? ''));

        if ($desc !== '') {
            return match ($desc) {
                'paid', 'success', 'settlement' => 'paid',
                'pending' => 'pending',
                'expired' => 'expired',
                'canceled', 'cancelled' => 'canceled',
                'fail', 'failed' => 'failed',
                default => 'pending', // status tak dikenal: jangan dianggap final
            };
        }

        // Cadangan: kode latestTransactionStatus (00/02 sukses, 01 menunggu, 05 batal, 07 tidak ditemukan)
        return match ((string) ($body['latestTransactionStatus'] ?? '')) {
            '00', '02' => 'paid',
            '05' => 'canceled',
            '07' => 'failed',
            default => 'pending',
        };
    }

    /** Nominal dari objek amount {value, currency}. */
    public static function nominal(array $body): ?int
    {
        $amount = $body['amount'] ?? null;

        if (is_array($amount) && isset($amount['value'])) {
            return (int) round((float) $amount['value']);
        }

        return is_numeric($amount) ? (int) round((float) $amount) : null;
    }

    /** referenceNo (dokumen tabel) atau referenceNumber (contoh respons inquiry). */
    public static function referenceNo(array $body): ?string
    {
        return $body['referenceNo'] ?? $body['referenceNumber'] ?? null;
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
        ]);

        if (! is_array($body) || $body === []) {
            throw new AinoException("Respons AINO tidak valid (HTTP {$response->status()}).");
        }

        return $body;
    }
}
