<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';   // Pengurus RW
    public const ROLE_RT = 'rt';         // Pengurus RT
    public const ROLE_WARGA = 'warga';

    public const ROLES = [
        self::ROLE_ADMIN => 'Pengurus RW',
        self::ROLE_RT => 'Pengurus RT',
        self::ROLE_WARGA => 'Warga',
    ];

    protected $fillable = [
        'name', 'email', 'no_hp', 'password', 'role', 'rt_id', 'kartu_keluarga_id', 'aktif',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
        ];
    }

    /** Email selalu disimpan huruf kecil agar login tidak peka huruf besar/kecil. */
    protected function email(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn ($v) => $v === null ? null : strtolower(trim((string) $v)),
        );
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function kartuKeluarga(): BelongsTo
    {
        return $this->belongsTo(KartuKeluarga::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Pengurus RT hanya dianggap pengurus bila benar-benar terhubung ke sebuah RT. */
    public function isPengurusRt(): bool
    {
        return $this->role === self::ROLE_RT && $this->rt_id !== null;
    }

    public function isWarga(): bool
    {
        return $this->role === self::ROLE_WARGA;
    }

    /** Pengurus = admin RW atau pengurus RT. */
    public function isPengurus(): bool
    {
        return $this->isAdmin() || $this->isPengurusRt();
    }

    public function roleLabel(): string
    {
        $label = self::ROLES[$this->role] ?? $this->role;

        return $this->isPengurusRt() && $this->rt ? $label.' '.$this->rt->nomor : $label;
    }

    /** Apakah user boleh mengubah data di RT tertentu. */
    public function canManageRt(?int $rtId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->isPengurusRt() && $rtId !== null && (int) $this->rt_id === (int) $rtId;
    }

    /** RT yang datanya boleh dikelola user ini. */
    public function managedRts(): Builder
    {
        return Rt::query()
            ->when(! $this->isAdmin(), fn ($q) => $q->whereKey($this->isPengurusRt() ? $this->rt_id : 0))
            ->orderBy('nomor');
    }

    /** RT tempat warga ini tinggal (untuk warga) atau RT yang dikelola (untuk pengurus RT). */
    public function rtId(): ?int
    {
        if ($this->isPengurusRt()) {
            return $this->rt_id;
        }

        return $this->kartuKeluarga?->rumah?->blok?->rt_id ?? $this->rt_id;
    }
}
