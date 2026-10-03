<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Rt extends Model
{
    use HasFactory;

    protected $table = 'rts';

    /** Warna pembeda RT di peta: RT 01 merah, RT 02 biru, RT 03 kuning, dst. */
    public const PALET = [
        '#dc2626', // merah
        '#2563eb', // biru
        '#eab308', // kuning
        '#16a34a', // hijau
        '#9333ea', // ungu
        '#ea580c', // oranye
        '#db2777', // merah muda
        '#0891b2', // biru toska
        '#92400e', // cokelat
        '#475569', // abu-abu
    ];

    protected $fillable = ['nomor', 'nama_ketua', 'no_hp_ketua', 'warna', 'keterangan'];

    /** Warna palet berikutnya untuk RT baru. */
    public static function warnaBerikutnya(): string
    {
        return self::PALET[static::query()->count() % count(self::PALET)];
    }

    public function bloks(): HasMany
    {
        return $this->hasMany(Blok::class)->orderBy('urutan')->orderBy('nama');
    }

    public function rumahs(): HasManyThrough
    {
        return $this->hasManyThrough(Rumah::class, Blok::class);
    }

    public function pengumumans(): HasMany
    {
        return $this->hasMany(Pengumuman::class);
    }

    public function getLabelAttribute(): string
    {
        return 'RT '.$this->nomor;
    }
}
