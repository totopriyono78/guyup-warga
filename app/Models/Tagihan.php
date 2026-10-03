<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tagihan extends Model
{
    use HasFactory;

    protected $table = 'tagihans';

    public const METODE = [
        'qris' => 'QRIS',
        'va' => 'Virtual Account',
        'tunai' => 'Tunai',
        'transfer' => 'Transfer manual',
    ];

    protected $fillable = [
        'kartu_keluarga_id', 'tarif_iuran_id', 'periode', 'judul', 'jatuh_tempo', 'nominal', 'sukarela',
        'nominal_dibayar', 'rincian', 'status', 'metode', 'dibayar_pada', 'dicatat_oleh', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'nominal_dibayar' => 'integer',
            'sukarela' => 'boolean',
            'jatuh_tempo' => 'date',
            'rincian' => 'array',
            'dibayar_pada' => 'datetime',
        ];
    }

    /** Periode selalu disimpan sebagai tanggal 1 (Y-m-d) dan dibaca sebagai Carbon. */
    protected function periode(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->startOfDay() : null,
            set: fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->startOfMonth()->format('Y-m-d') : null,
        );
    }

    public function kartuKeluarga(): BelongsTo
    {
        return $this->belongsTo(KartuKeluarga::class);
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(TarifIuran::class, 'tarif_iuran_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function pembayarans(): BelongsToMany
    {
        return $this->belongsToMany(Pembayaran::class, 'pembayaran_tagihan');
    }

    public function scopeBelum(Builder $q): Builder
    {
        return $q->where('status', 'belum');
    }

    public function scopeLunas(Builder $q): Builder
    {
        return $q->where('status', 'lunas');
    }

    public function isLunas(): bool
    {
        return $this->status === 'lunas';
    }

    /** Nama tagihan: judul per jenis (mis. "Kebersihan – Oktober 2026"), atau nama bulan untuk data lama. */
    public function getPeriodeLabelAttribute(): string
    {
        return $this->judul ?: 'Iuran '.$this->periode->translatedFormat('F Y');
    }

    /** Nominal yang tercatat masuk kas (untuk sukarela bisa lebih besar dari minimal). */
    public function getNominalMasukAttribute(): int
    {
        return (int) ($this->nominal_dibayar ?? $this->nominal);
    }

    public function jatuhTempo(): \Illuminate\Support\Carbon
    {
        if ($this->jatuh_tempo) {
            return $this->jatuh_tempo->copy()->endOfDay();
        }

        $tgl = min(max(1, (int) config('siwarga.jatuh_tempo_tanggal', 10)), 28);

        return $this->periode->copy()->day($tgl)->endOfDay();
    }

    public function terlambat(): bool
    {
        return ! $this->isLunas() && ! $this->sukarela && now()->greaterThan($this->jatuhTempo());
    }
}
