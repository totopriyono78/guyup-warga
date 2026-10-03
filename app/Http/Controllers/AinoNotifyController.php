<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Services\AinoException;
use App\Services\PembayaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Finish Notify — AINO memberi tahu status pembayaran.
 *
 * Body: referenceNo, acquireReferenceNo, orderId, orderDate, paymentType,
 *       amount, statusCode (1 pending, 2 expired, 3 paid, 4 failed, 5 canceled), statusLabel
 * Respons: {"responseCode": "200", "responseMessage": "Successful"}
 *
 * Karena notifikasi tidak ditandatangani, isi body TIDAK dipercaya begitu saja.
 * Status akhir selalu diambil dari API Query Payment AINO.
 */
class AinoNotifyController extends Controller
{
    public function __invoke(Request $request, PembayaranService $service): JsonResponse
    {
        $body = $request->json()->all() ?: $request->all();

        Log::info('AINO notify diterima', [
            'ip' => $request->ip(),
            'orderId' => $body['orderId'] ?? null,
            'referenceNo' => $body['referenceNo'] ?? null,
            'statusCode' => $body['statusCode'] ?? null,
            'statusLabel' => $body['statusLabel'] ?? null,
            'amount' => $body['amount'] ?? null,
        ]);

        // Opsional: batasi IP pengirim (isi AINO_ALLOWED_IPS, dipisah koma)
        $izin = config('aino.allowed_ips', []);
        if ($izin && ! in_array($request->ip(), $izin, true)) {
            Log::warning('AINO notify ditolak: IP tidak diizinkan', ['ip' => $request->ip()]);

            return $this->balas('403', 'Forbidden', 403);
        }

        $orderId = $body['orderId'] ?? $body['order_id'] ?? null;
        if (! is_scalar($orderId) || ! Str::isUuid((string) $orderId)) {
            return $this->balas('400', 'Invalid Order ID', 400);
        }
        $orderId = (string) $orderId;

        $pembayaran = Pembayaran::query()->where('order_id', $orderId)->first();
        if (! $pembayaran) {
            return $this->balas('400', 'Invalid Order ID', 400);
        }

        // reference_no hanya diambil dari respons Generate (bukan dari callback yang tak bertanda tangan)
        $ref = $body['referenceNo'] ?? null;
        if (is_scalar($ref) && (string) $ref !== '' && $pembayaran->reference_no && (string) $ref !== (string) $pembayaran->reference_no) {
            return $this->balas('400', 'Invalid Reference Number', 400);
        }

        if ($pembayaran->isPaid()) {
            return $this->balas('200', 'Successful');
        }

        try {
            $hasil = $service->sinkron($pembayaran); // verifikasi ke Query Payment

            $acq = $body['acquireReferenceNo'] ?? null;
            if ($hasil->isPaid() && is_scalar($acq) && (string) $acq !== '' && blank($hasil->acquire_reference_no)) {
                $hasil->forceFill(['acquire_reference_no' => mb_substr((string) $acq, 0, 64)])->save();
            }
        } catch (AinoException $e) {
            Log::error('AINO notify: gagal verifikasi via Query Payment', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            // Scheduler pembayaran:sinkron akan mencoba lagi setiap 5 menit
            return $this->balas('500', 'Temporary failure, will reconcile', 503);
        }

        return $this->balas('200', 'Successful');
    }

    private function balas(string $kode, string $pesan, int $http = 200): JsonResponse
    {
        return response()->json(['responseCode' => $kode, 'responseMessage' => $pesan], $http);
    }
}
