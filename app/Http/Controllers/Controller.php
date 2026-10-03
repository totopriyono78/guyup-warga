<?php

namespace App\Http\Controllers;

use App\Models\KartuKeluarga;
use App\Models\User;

abstract class Controller
{
    /** URL "kembali" dari form (mis. ke titik rumah di peta). Hanya URL internal yang diterima. */
    protected function urlKembali(?string $default = null): ?string
    {
        $url = request()->input('kembali');

        if (! is_string($url) || $url === '') {
            return $default;
        }

        $base = rtrim(url('/'), '/');
        $internal = str_starts_with($url, $base.'/') || $url === $base
            || (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\'));

        return $internal ? $url : $default;
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    /** Hentikan dengan 403 bila user tidak boleh mengelola RT tsb. */
    protected function pastikanKelolaRt(?int $rtId): void
    {
        abort_unless($this->user()->canManageRt($rtId), 403, 'Data ini di luar wilayah yang Anda kelola.');
    }

    protected function pastikanKelolaKeluarga(KartuKeluarga $kk): void
    {
        $kk->loadMissing('rumah.blok');
        $user = $this->user();

        if ($user->isAdmin()) {
            return;
        }

        // KK tanpa rumah hanya bisa dikelola pengurus RW
        if ($user->isPengurusRt() && $kk->rtId() !== null && (int) $kk->rtId() === (int) $user->rt_id) {
            return;
        }

        abort(403, 'Data keluarga ini di luar wilayah yang Anda kelola.');
    }

    protected function bolehKelolaKeluarga(KartuKeluarga $kk): bool
    {
        $kk->loadMissing('rumah.blok');
        $user = $this->user();

        return $user->isAdmin()
            || ($user->isPengurusRt() && $kk->rtId() !== null && (int) $kk->rtId() === (int) $user->rt_id);
    }

    protected function bolehLihatDetailKeluarga(KartuKeluarga $kk): bool
    {
        $kk->loadMissing('rumah.blok');
        $user = $this->user();

        return $user->isAdmin()
            || ($user->isPengurusRt() && $kk->rtId() !== null && (int) $kk->rtId() === (int) $user->rt_id)
            || (int) $user->kartu_keluarga_id === (int) $kk->id;
    }
}
