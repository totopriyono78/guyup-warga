<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Pengaturan extends Model
{
    protected $table = 'pengaturans';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'nilai'];

    /** Semua pengaturan sebagai array [kunci => nilai], dengan default dari config. */
    public static function semua(): array
    {
        $default = [
            'nama_rw' => config('siwarga.nama_rw'),
            'dusun' => config('siwarga.dusun'),
            'kelurahan' => config('siwarga.kelurahan'),
            'kecamatan' => config('siwarga.kecamatan'),
            'kabupaten' => config('siwarga.kabupaten'),
            'provinsi' => config('siwarga.provinsi'),
            'alamat_sekretariat' => null,
            'kontak' => null,
            'peta_wilayah' => null,
            // '1' = nama kepala keluarga tampil saat titik rumah diklik di peta halaman umum (tanpa login)
            'peta_umum_nama' => '1',
        ];

        try {
            $db = Cache::get('pengaturan');
            if (! is_array($db)) {
                $db = [];
                // Hanya disimpan ke cache bila tabel sudah ada (hindari cache kosong sebelum migrate)
                if (Schema::hasTable('pengaturans')) {
                    $db = static::query()->pluck('nilai', 'kunci')->all();
                    Cache::forever('pengaturan', $db);
                }
            }
        } catch (\Throwable) {
            $db = [];
        }

        // '' disimpan = sengaja dikosongkan admin (tidak kembali ke default config)
        $db = array_filter($db, fn ($v) => $v !== null);
        // kompatibel dengan versi lama yang memakai kunci "kota"
        if (! isset($db['kabupaten']) && isset($db['kota'])) {
            $db['kabupaten'] = $db['kota'];
        }

        return array_merge($default, $db);
    }

    /** Posisi & zoom awal peta wilayah. */
    public static function petaAwal(): array
    {
        $p = static::semua();

        return [
            'lat' => isset($p['peta_lat']) ? (float) $p['peta_lat'] : (float) config('siwarga.peta.lat'),
            'lng' => isset($p['peta_lng']) ? (float) $p['peta_lng'] : (float) config('siwarga.peta.lng'),
            'zoom' => isset($p['peta_zoom']) ? (int) $p['peta_zoom'] : (int) config('siwarga.peta.zoom'),
            'tersimpan' => isset($p['peta_lat'], $p['peta_lng']),
        ];
    }

    /** Konfigurasi peta untuk JavaScript (tanpa rahasia server). */
    public static function petaJs(): array
    {
        return [
            'awal' => static::petaAwal(),
            'googleKey' => config('siwarga.peta.google_key') ?: null,
        ];
    }

    public static function ambil(string $kunci, mixed $default = null): mixed
    {
        return static::semua()[$kunci] ?? $default;
    }

    public static function simpan(array $data): void
    {
        foreach ($data as $kunci => $nilai) {
            static::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        }
        Cache::forget('pengaturan');
    }
}
