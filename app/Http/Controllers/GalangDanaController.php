<?php

namespace App\Http\Controllers;

use App\Models\Donasi;
use App\Models\DonasiDonatur;
use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Builder;

/**
 * Galang dana untuk semua warga yang login: daftar program, donasi via QRIS,
 * dan riwayat donasi milik sendiri (QRIS maupun yang dicatat pengurus).
 */
class GalangDanaController extends Controller
{
    public function index()
    {
        $user = $this->user();

        $semua = Donasi::query()->terlihatOleh($user)->with('rt')
            ->withCount('donaturs')->withSum('donaturs', 'nominal')
            ->orderByDesc('aktif')->latest()->get();

        return view('galang.index', [
            'berjalan' => $semua->filter->berjalan()->values(),
            'selesai' => $semua->reject->berjalan()->values(),
            'pembayaran' => $this->pembayaranSaya()->with('donasi:id,judul')->latest()->limit(20)->get(),
            'tercatat' => $user->kartu_keluarga_id
                ? DonasiDonatur::query()->where('kartu_keluarga_id', $user->kartu_keluarga_id)->whereNull('pembayaran_id')
                    ->with('donasi:id,judul')->latest('tanggal')->limit(20)->get()
                : collect(),
        ]);
    }

    public function show(Donasi $donasi)
    {
        $user = $this->user();
        abort_unless($donasi->bolehDilihat($user), 404);
        $donasi->loadCount('donaturs')->loadSum('donaturs', 'nominal')->load('rt');

        return view('galang.show', [
            'd' => $donasi,
            'donaturs' => $donasi->donaturs()->get(['id', 'nama', 'anonim', 'tanggal', 'kartu_keluarga_id']),
            'pembayaranSaya' => $this->pembayaranSaya()->where('donasi_id', $donasi->id)->latest()->limit(10)->get(),
            'bolehKelola' => $donasi->rt_id ? $user->canManageRt($donasi->rt_id) : $user->isAdmin(),
        ]);
    }

    /** Transaksi donasi QRIS milik user ini (atau keluarganya). */
    private function pembayaranSaya(): Builder
    {
        $user = $this->user();

        return Pembayaran::query()->whereNotNull('donasi_id')
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->when($user->kartu_keluarga_id, fn ($w) => $w->orWhere('kartu_keluarga_id', $user->kartu_keluarga_id)));
    }
}
