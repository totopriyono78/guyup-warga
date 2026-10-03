<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayarans';

    public const STATUS = [
        'pending' => 'Menunggu pembayaran',
        'paid' => 'Berhasil',
        'expired' => 'Kedaluwarsa',
        'failed' => 'Gagal',
        'canceled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'kartu_keluarga_id', 'donasi_id', 'donatur_nama', 'donatur_anonim', 'donatur_pesan', 'user_id', 'order_id', 'reference_no', 'acquire_reference_no',
        'payment_type', 'jumlah_iuran', 'biaya', 'total', 'status', 'payment_content',
        'expired_at', 'paid_at', 'response_generate', 'response_terakhir',
    ];

    protected $hidden = ['response_generate', 'response_terakhir'];

    protected function casts(): array
    {
        return [
            'jumlah_iuran' => 'integer',
            'biaya' => 'integer',
            'total' => 'integer',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'response_generate' => 'array',
            'response_terakhir' => 'array',
            'donatur_anonim' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_id';
    }

    public function kartuKeluarga(): BelongsTo
    {
        return $this->belongsTo(KartuKeluarga::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donasi(): BelongsTo
    {
        return $this->belongsTo(Donasi::class);
    }

    /** Transaksi donasi (bukan iuran). */
    public function isDonasi(): bool
    {
        return $this->donasi_id !== null;
    }

    public function tagihans(): BelongsToMany
    {
        return $this->belongsToMany(Tagihan::class, 'pembayaran_tagihan')->withPivot('nominal')->orderBy('periode');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isKedaluwarsa(): bool
    {
        return $this->isPending() && $this->expired_at && $this->expired_at->isPast();
    }

    public function isVa(): bool
    {
        return str_starts_with($this->payment_type, 'va_');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
