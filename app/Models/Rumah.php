<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rumah extends Model
{
    use HasFactory;

    protected $table = 'rumahs';

    public const STATUS = [
        'dihuni' => 'Dihuni pemilik',
        'kontrakan' => 'Dikontrakkan',
        'kosong' => 'Kosong',
        'usaha' => 'Tempat usaha / fasum',
    ];

    protected $fillable = ['blok_id', 'nomor', 'baris', 'kolom', 'lat', 'lng', 'status_hunian', 'pemilik', 'keterangan'];

    protected function casts(): array
    {
        return ['baris' => 'integer', 'kolom' => 'integer', 'lat' => 'float', 'lng' => 'float'];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class);
    }

    public function kartuKeluargas(): HasMany
    {
        return $this->hasMany(KartuKeluarga::class);
    }

    public function keluargaAktif(): HasMany
    {
        return $this->hasMany(KartuKeluarga::class)->where('aktif', true);
    }

    public function punyaLokasi(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * Posisi baris/kolom kosong pertama di blok (untuk rumah yang dibuat dari peta).
     *
     * @return array{0:int, 1:int}
     */
    public static function posisiKosong(int $blokId): array
    {
        $terpakai = static::query()->where('blok_id', $blokId)->get(['baris', 'kolom'])
            ->map(fn ($r) => $r->baris.'-'.$r->kolom)->flip();

        for ($b = 1; $b <= 50; $b++) {
            for ($k = 1; $k <= 20; $k++) {
                if (! isset($terpakai[$b.'-'.$k])) {
                    return [$b, $k];
                }
            }
        }

        return [51, 1];
    }

    /** Contoh: "A-12" */
    public function getKodeAttribute(): string
    {
        return ($this->blok?->nama ?? '?').'-'.$this->nomor;
    }

    public function getAlamatAttribute(): string
    {
        $blok = $this->blok;

        return trim('Blok '.($blok?->nama ?? '-').' No. '.$this->nomor.($blok?->rt ? ', RT '.$blok->rt->nomor : ''));
    }
}
