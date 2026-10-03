<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hari pasaran Jawa (Legi, Pahing, Pon, Wage, Kliwon).
 * Acuan: 17 Agustus 1945 = Jumat Legi; pasaran berulang tiap 5 hari.
 */
class Pasaran
{
    public const NAMA = ['Legi', 'Pahing', 'Pon', 'Wage', 'Kliwon'];

    public const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public static function nama(CarbonInterface $tanggal): string
    {
        // tanpa ekstensi "calendar": hitung selisih hari dari 17-08-1945 (Legi)
        $hari = (int) round(Carbon::create(1945, 8, 17, 0, 0, 0, 'UTC')->diffInDays(Carbon::create($tanggal->year, $tanggal->month, $tanggal->day, 0, 0, 0, 'UTC'), false));

        return self::NAMA[(($hari % 5) + 5) % 5];
    }

    /** "Minggu Pahing" */
    public static function hariPasaran(CarbonInterface $tanggal): string
    {
        return self::HARI[$tanggal->dayOfWeek].' '.self::nama($tanggal);
    }

    /** Tanggal berikutnya (termasuk hari ini bila cocok) dengan hari & pasaran tertentu, mis. Minggu Pahing. */
    public static function berikutnya(string $hari, string $pasaran, ?CarbonInterface $dari = null): Carbon
    {
        $t = Carbon::parse($dari ?? now())->startOfDay();
        for ($i = 0; $i < 35; $i++) {
            if (self::HARI[$t->dayOfWeek] === $hari && self::nama($t) === $pasaran) {
                return $t;
            }
            $t->addDay();
        }

        throw new \InvalidArgumentException("Hari/pasaran tidak dikenal: {$hari} {$pasaran}");
    }
}
