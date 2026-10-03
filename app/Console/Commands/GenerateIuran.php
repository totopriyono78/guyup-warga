<?php

namespace App\Console\Commands;

use App\Services\TagihanService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateIuran extends Command
{
    protected $signature = 'iuran:generate {periode? : Format YYYY-MM, default bulan ini} {--rt= : ID RT (opsional)}';

    protected $description = 'Membuat tagihan iuran bulanan untuk semua KK aktif';

    public function handle(TagihanService $service): int
    {
        $periode = $this->argument('periode')
            ? Carbon::createFromFormat('!Y-m', $this->argument('periode'))->startOfMonth()
            : now()->startOfMonth();

        $jumlah = $service->generate($periode, $this->option('rt') ? (int) $this->option('rt') : null);

        $this->info("{$jumlah} tagihan baru dibuat untuk periode {$periode->translatedFormat('F Y')}.");

        return self::SUCCESS;
    }
}
