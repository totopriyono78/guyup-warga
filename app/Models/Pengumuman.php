<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Pengumuman extends Model
{
    use HasFactory;

    protected $table = 'pengumumans';

    protected $fillable = ['user_id', 'rt_id', 'judul', 'isi', 'penting', 'publik', 'lampiran', 'lampiran_nama', 'terbit_pada'];

    protected function casts(): array
    {
        return [
            'penting' => 'boolean',
            'publik' => 'boolean',
            'terbit_pada' => 'datetime',
        ];
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /** Pengumuman yang boleh tampil di halaman umum (tanpa login). */
    public function scopePublik(Builder $q): Builder
    {
        return $q->where('publik', true)->terbit();
    }

    public function scopeTerbit(Builder $q): Builder
    {
        return $q->whereNotNull('terbit_pada')->where('terbit_pada', '<=', now());
    }

    /** Pengumuman yang boleh dilihat user: semua pengumuman RW + pengumuman RT-nya. */
    public function scopeUntuk(Builder $q, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $q;
        }

        $rtId = $user->rtId();

        return $q->where(fn ($w) => $w->whereNull('rt_id')->when($rtId, fn ($x) => $x->orWhere('rt_id', $rtId)));
    }

    public function getLingkupAttribute(): string
    {
        return $this->rt_id ? 'RT '.($this->rt?->nomor ?? '') : 'Seluruh RW';
    }

    public function ringkasan(int $panjang = 160): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->isi))), $panjang);
    }

    public function lampiranUrl(): ?string
    {
        return $this->lampiran ? Storage::disk('public')->url($this->lampiran) : null;
    }

    public function lampiranGambar(): bool
    {
        return $this->lampiran && preg_match('/\.(jpe?g|png|webp|gif)$/i', $this->lampiran);
    }
}
