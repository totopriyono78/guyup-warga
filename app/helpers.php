<?php

use App\Models\Pengaturan;

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $angka, bool $prefix = true): string
    {
        return ($prefix ? 'Rp ' : '').number_format((float) $angka, 0, ',', '.');
    }
}

if (! function_exists('inisial')) {
    /** "Budi Santoso" -> "BS" */
    function inisial(?string $nama): string
    {
        $kata = preg_split('/\s+/', trim((string) $nama)) ?: [];
        $hasil = '';
        foreach (array_slice($kata, 0, 2) as $k) {
            $hasil .= mb_strtoupper(mb_substr($k, 0, 1));
        }

        return $hasil ?: '?';
    }
}

if (! function_exists('pengaturan')) {
    function pengaturan(string $kunci, mixed $default = null): mixed
    {
        return Pengaturan::ambil($kunci, $default);
    }
}

if (! function_exists('wilayah')) {
    /**
     * Alamat wilayah dari Pengaturan.
     *   wilayah()          -> "Dusun Sidorejo, Kel. Selomartani, Kec. Kalasan, Kab. Sleman, D.I. Yogyakarta"
     *   wilayah('singkat') -> "Dusun Sidorejo · Selomartani"
     */
    function wilayah(string $bentuk = 'lengkap'): string
    {
        $p = Pengaturan::semua();
        $awalan = function (?string $nilai, string $prefix, array $sudah) {
            $nilai = trim((string) $nilai);
            if ($nilai === '') {
                return null;
            }
            foreach ($sudah as $s) {
                if (stripos($nilai, $s) === 0) {
                    return $nilai;
                }
            }

            return $prefix.$nilai;
        };

        $dusun = $awalan($p['dusun'] ?? null, 'Dusun ', ['dusun', 'padukuhan', 'dukuh', 'kampung', 'perum']);

        if ($bentuk === 'singkat') {
            return collect([$dusun, trim((string) ($p['kelurahan'] ?? '')) ?: null])->filter()->join(' · ');
        }

        return collect([
            $dusun,
            $awalan($p['kelurahan'] ?? null, 'Kel. ', ['kel', 'desa', 'kalurahan']),
            $awalan($p['kecamatan'] ?? null, 'Kec. ', ['kec', 'kapanewon', 'kemantren']),
            $awalan($p['kabupaten'] ?? null, 'Kab. ', ['kab', 'kota']),
            trim((string) ($p['provinsi'] ?? '')) ?: null,
        ])->filter()->join(', ');
    }
}
