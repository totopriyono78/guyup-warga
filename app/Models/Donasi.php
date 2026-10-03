<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Penggalangan dana / donasi (mis. renovasi pos kamling, santunan, bencana). */
class Donasi extends Model
{
    protected $table = 'donasis';

    protected $fillable = [
        'rt_id', 'user_id', 'judul', 'ringkasan', 'deskripsi', 'target', 'mulai', 'selesai',
        'gambar', 'cara_donasi', 'tampilkan_total', 'publik', 'aktif', 'terima_qris',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'mulai' => 'date',
            'selesai' => 'date',
            'tampilkan_total' => 'boolean',
            'publik' => 'boolean',
            'aktif' => 'boolean',
            'terima_qris' => 'boolean',
        ];
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function donaturs(): HasMany
    {
        return $this->hasMany(DonasiDonatur::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    /** Bisa menerima donasi lewat QRIS saat ini. */
    public function bisaQris(): bool
    {
        return $this->terima_qris && $this->berjalan() && app(\App\Services\AinoClient::class)->dikonfigurasi();
    }

    /** Penggalangan dana yang boleh dilihat user login: publik, tingkat RW, atau RT-nya sendiri. */
    public function scopeTerlihatOleh(Builder $q, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $q;
        }
        $rtId = $user->isPengurusRt() ? $user->rt_id : $user->rtId();

        return $q->where(fn ($w) => $w->where('publik', true)->orWhereNull('rt_id')
            ->when($rtId, fn ($x) => $x->orWhere('rt_id', $rtId)));
    }

    public function bolehDilihat(?User $user): bool
    {
        if ($this->publik) {
            return true;
        }

        return $user !== null && static::query()->whereKey($this->id)->terlihatOleh($user)->exists();
    }

    public function scopePublik(Builder $q): Builder
    {
        return $q->where('publik', true);
    }

    /** Masih menerima donasi: aktif dan belum melewati tanggal selesai. */
    public function berjalan(): bool
    {
        return $this->aktif && (! $this->selesai || $this->selesai->endOfDay()->isFuture());
    }

    public function getTerkumpulAttribute(): int
    {
        // tanpa ORDER BY (PostgreSQL menolak ORDER BY pada agregat)
        return (int) ($this->attributes['donaturs_sum_nominal'] ?? DonasiDonatur::query()->where('donasi_id', $this->id)->sum('nominal'));
    }

    public function getJumlahDonaturAttribute(): int
    {
        return (int) ($this->attributes['donaturs_count'] ?? DonasiDonatur::query()->where('donasi_id', $this->id)->count());
    }

    public function persen(): ?int
    {
        return $this->target ? (int) min(100, floor($this->terkumpul / max(1, $this->target) * 100)) : null;
    }

    public function sisaHari(): ?int
    {
        return $this->selesai && $this->berjalan() ? (int) now()->startOfDay()->diffInDays($this->selesai, false) : null;
    }

    public function gambarUrl(): ?string
    {
        return $this->gambar ? Storage::disk('public')->url($this->gambar) : null;
    }

    public function getLingkupAttribute(): string
    {
        return $this->rt_id ? 'RT '.($this->rt?->nomor ?? '') : pengaturan('nama_rw', 'RW');
    }
}
