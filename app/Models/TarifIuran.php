<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Jenis iuran: kebersihan, sosial, keamanan, iuran acara, dll. */
class TarifIuran extends Model
{
    use HasFactory;

    protected $table = 'tarif_iurans';

    public const FREKUENSI = [
        'bulanan' => 'Bulanan',
        'tahunan' => 'Tahunan',
        'insidental' => 'Insidental / acara',
    ];

    protected $fillable = ['nama', 'nominal', 'frekuensi', 'bulan_tagih', 'tenggat', 'sukarela', 'diterbitkan_pada', 'rt_id', 'aktif', 'keterangan'];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'aktif' => 'boolean',
            'sukarela' => 'boolean',
            'bulan_tagih' => 'integer',
            'tenggat' => 'date',
            'diterbitkan_pada' => 'datetime',
        ];
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function tagihans(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    /** Jenis aktif yang berlaku untuk RT tertentu (iuran RW + iuran khusus RT). */
    public function scopeBerlakuUntuk(Builder $q, ?int $rtId): Builder
    {
        return $q->where('aktif', true)
            ->where(fn ($w) => $w->whereNull('rt_id')->when($rtId, fn ($x) => $x->orWhere('rt_id', $rtId)));
    }

    public function scopeFrekuensi(Builder $q, string $frekuensi): Builder
    {
        return $q->where('frekuensi', $frekuensi);
    }

    public function frekuensiLabel(): string
    {
        $label = self::FREKUENSI[$this->frekuensi] ?? $this->frekuensi;

        if ($this->frekuensi === 'tahunan' && $this->bulan_tagih) {
            $label .= ' (tiap '.Carbon::create(2000, $this->bulan_tagih, 1)->translatedFormat('F').')';
        }

        return $label;
    }

    /** Judul tagihan untuk periode tertentu. */
    public function judulTagihan(Carbon $periode): string
    {
        return match ($this->frekuensi) {
            'tahunan' => $this->nama.' '.$periode->year,
            'insidental' => $this->nama,
            default => $this->nama.' – '.$periode->translatedFormat('F Y'),
        };
    }

    /** Jatuh tempo tagihan untuk periode tertentu. */
    public function jatuhTempo(Carbon $periode): Carbon
    {
        if ($this->frekuensi === 'insidental') {
            return $this->tenggat ? $this->tenggat->copy() : now()->addDays(14)->startOfDay();
        }

        $tgl = min(max(1, (int) config('siwarga.jatuh_tempo_tanggal', 10)), 28);

        return $periode->copy()->startOfMonth()->day($tgl);
    }
}
