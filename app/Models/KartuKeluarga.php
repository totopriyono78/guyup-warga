<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class KartuKeluarga extends Model
{
    use HasFactory;

    protected $table = 'kartu_keluargas';

    public const STATUS_TINGGAL = [
        'tetap' => 'Warga tetap',
        'kontrak' => 'Kontrak / sewa',
        'kos' => 'Kos',
    ];

    protected $fillable = [
        'rumah_id', 'no_kk', 'nama_kepala', 'status_tinggal', 'no_hp', 'foto',
        'tanggal_masuk', 'aktif', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'aktif' => 'boolean',
        ];
    }

    public function rumah(): BelongsTo
    {
        return $this->belongsTo(Rumah::class);
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaKeluarga::class)
            ->orderByRaw("CASE WHEN hubungan = 'Kepala Keluarga' THEN 0 ELSE 1 END")
            ->orderBy('urutan')
            ->orderBy('tanggal_lahir');
    }

    public function kepala(): HasOne
    {
        return $this->hasOne(AnggotaKeluarga::class)->where('hubungan', 'Kepala Keluarga');
    }

    public function tagihans(): HasMany
    {
        return $this->hasMany(Tagihan::class)->orderByDesc('periode');
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class)->latest();
    }

    public function akun(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    /** Batasi ke RT tertentu (melalui rumah -> blok). */
    public function scopeDiRt(Builder $q, ?int $rtId): Builder
    {
        return $rtId ? $q->whereHas('rumah.blok', fn ($b) => $b->where('rt_id', $rtId)) : $q;
    }

    /** Data KK yang boleh dikelola user (admin: semua, pengurus RT: RT-nya). */
    public function scopeDikelolaOleh(Builder $q, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $q;
        }

        if ($user->isPengurusRt()) {
            return $q->diRt($user->rt_id);
        }

        return $q->whereKey($user->kartu_keluarga_id ?? 0);
    }

    public function rtId(): ?int
    {
        return $this->rumah?->blok?->rt_id;
    }

    public function fotoUrl(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    /** Sinkronkan nama_kepala dengan anggota berstatus Kepala Keluarga. */
    public function sinkronNamaKepala(): void
    {
        $kepala = $this->kepala()->first();
        if ($kepala && $kepala->nama !== $this->nama_kepala) {
            $this->forceFill(['nama_kepala' => $kepala->nama])->save();
        }
    }
}
