<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Album foto kegiatan warga. */
class Galeri extends Model
{
    protected $table = 'galeris';

    protected $fillable = ['rt_id', 'user_id', 'judul', 'deskripsi', 'tanggal', 'lokasi', 'publik', 'sampul_id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'publik' => 'boolean'];
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(GaleriFoto::class)->orderBy('urutan')->orderBy('id');
    }

    public function sampul(): BelongsTo
    {
        return $this->belongsTo(GaleriFoto::class, 'sampul_id');
    }

    public function scopePublik(Builder $q): Builder
    {
        return $q->where('publik', true);
    }

    /** Album terbaru dulu (tanggal kegiatan, lalu tanggal dibuat). */
    public function scopeTerbaru(Builder $q): Builder
    {
        return $q->orderByRaw('tanggal IS NULL')->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function sampulUrl(): ?string
    {
        $foto = $this->sampul
            ?? ($this->relationLoaded('fotos') ? $this->fotos->first() : $this->fotos()->first());

        return $foto?->url();
    }

    public function getLingkupAttribute(): string
    {
        return $this->rt_id ? 'RT '.($this->rt?->nomor ?? '') : pengaturan('nama_rw', 'RW');
    }
}
