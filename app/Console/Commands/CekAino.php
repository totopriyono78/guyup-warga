<?php

namespace App\Console\Commands;

use App\Models\Pembayaran;
use App\Services\AinoClient;
use App\Services\PembayaranService;
use Illuminate\Console\Command;

/**
 * Diagnosis transaksi QRIS: tampilkan respons mentah Query Payment AINO lalu terapkan statusnya.
 *   php artisan aino:cek                 (transaksi terbaru)
 *   php artisan aino:cek <order_id>
 */
class CekAino extends Command
{
    protected $signature = 'aino:cek {order? : order_id (UUID) transaksi} {--tanpa-simpan : hanya tampilkan, jangan ubah status}';

    protected $description = 'Cek status transaksi QRIS ke AINO dan tampilkan respons mentahnya';

    public function handle(AinoClient $aino, PembayaranService $service): int
    {
        $p = $this->argument('order')
            ? Pembayaran::query()->where('order_id', $this->argument('order'))->first()
            : Pembayaran::query()->latest()->first();

        if (! $p) {
            $this->error('Transaksi tidak ditemukan.');

            return self::FAILURE;
        }

        $this->line("Order    : {$p->order_id}");
        $this->line("Ref      : ".($p->reference_no ?: '(kosong)'));
        $this->line("Total    : {$p->total}");
        $this->line("Status   : {$p->status}".($p->donasi_id ? ' (donasi)' : ' (iuran)'));
        $this->line('Generate : '.json_encode(array_diff_key((array) $p->response_generate, ['paymentContent' => 1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $respons = $aino->inquiry($p->order_id, (string) ($p->reference_no ?: AinoClient::referenceNo((array) $p->response_generate)));
        } catch (\App\Services\AinoException $e) {
            $this->error('Inquiry gagal: '.$e->getMessage());
            $this->line(json_encode($e->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $this->line('Inquiry  : '.json_encode($respons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Dibaca sebagai: '.AinoClient::statusDariInquiry($respons).' · nominal '.var_export(AinoClient::nominal($respons), true).' · partnerRef '.var_export(AinoClient::partnerRef($respons), true));

        if (! $this->option('tanpa-simpan')) {
            $hasil = $service->sinkron($p);
            $this->info("Status sekarang: {$hasil->status}");
        }

        return self::SUCCESS;
    }
}
