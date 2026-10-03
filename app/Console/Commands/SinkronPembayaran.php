<?php

namespace App\Console\Commands;

use App\Models\Pembayaran;
use App\Services\AinoException;
use App\Services\PembayaranService;
use Illuminate\Console\Command;

class SinkronPembayaran extends Command
{
    protected $signature = 'pembayaran:sinkron {--hari=7 : Periksa transaksi pending sampai N hari ke belakang}';

    protected $description = 'Cek ulang status transaksi QRIS yang masih pending ke AINO (Query Payment)';

    public function handle(PembayaranService $service): int
    {
        $pending = Pembayaran::query()
            // pending, plus yang sudah kedaluwarsa < 1 hari (bisa saja dilaporkan lunas terlambat)
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($w) => $w->where('status', 'expired')->where('created_at', '>=', now()->subDay())))
            ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('hari'))))
            ->orderBy('id')
            ->get();

        foreach ($pending as $p) {
            try {
                $hasil = $service->sinkron($p);
                if ($hasil->status !== $p->status) {
                    $this->line("{$p->order_id}: {$hasil->status}");
                }
            } catch (AinoException $e) {
                // AINO tidak bisa dihubungi: biarkan tetap pending, dicoba lagi pada jadwal berikutnya
                $this->warn("{$p->order_id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
