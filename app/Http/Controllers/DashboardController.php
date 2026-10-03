<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKeluarga;
use App\Models\Donasi;
use App\Models\KartuKeluarga;
use App\Models\Pengumuman;
use App\Models\Rumah;
use App\Models\Tagihan;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = $this->user();
        $periode = now()->startOfMonth()->toDateString();

        $pengumuman = Pengumuman::query()->untuk($user)->terbit()->with('rt')
            ->orderByDesc('penting')->orderByDesc('terbit_pada')->limit(5)->get();

        $data = [
            'pengumuman' => $pengumuman,
            'galangDana' => Donasi::query()->terlihatOleh($user)->where('aktif', true)
                ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', now()->toDateString()))
                ->withCount('donaturs')->withSum('donaturs', 'nominal')->latest()->limit(3)->get(),
        ];

        if ($user->isPengurus()) {
            $rtId = $user->isPengurusRt() ? $user->rt_id : null;

            $kk = KartuKeluarga::query()->aktif()->diRt($rtId);
            $tagihanBulanIni = Tagihan::query()->where('periode', $periode)
                ->whereHas('kartuKeluarga', fn ($q) => $q->diRt($rtId));

            $data['statistik'] = [
                'rumah' => Rumah::query()->when($rtId, fn ($q) => $q->whereHas('blok', fn ($b) => $b->where('rt_id', $rtId)))->count(),
                'kk' => (clone $kk)->count(),
                'jiwa' => AnggotaKeluarga::query()->whereHas('kartuKeluarga', fn ($q) => $q->aktif()->diRt($rtId))->count(),
                'tagihan_total' => (int) (clone $tagihanBulanIni)->where('sukarela', false)->sum('nominal'),
                'tagihan_lunas' => (int) (clone $tagihanBulanIni)->lunas()->sum(\Illuminate\Support\Facades\DB::raw('coalesce(nominal_dibayar, nominal)')),
                // jumlah KK (bukan jumlah tagihan): KK dianggap lunas bila semua tagihan wajibnya bulan ini lunas
                'kk_tagihan' => (clone $tagihanBulanIni)->where('sukarela', false)->distinct()->count('kartu_keluarga_id'),
                'kk_lunas' => (clone $tagihanBulanIni)->where('sukarela', false)->distinct()->count('kartu_keluarga_id')
                    - (clone $tagihanBulanIni)->where('sukarela', false)->belum()->distinct()->count('kartu_keluarga_id'),
            ];

            $data['tunggakan'] = Tagihan::query()->belum()->where('sukarela', false)
                ->where('periode', '<', $periode)
                ->whereHas('kartuKeluarga', fn ($q) => $q->aktif()->diRt($rtId))
                ->selectRaw('kartu_keluarga_id, count(*) as bulan, sum(nominal) as total')
                ->groupBy('kartu_keluarga_id')
                ->orderByDesc('bulan')
                ->limit(8)
                ->get()
                ->load('kartuKeluarga.rumah.blok');
        }

        if ($user->kartu_keluarga_id) {
            $data['keluarga'] = $user->kartuKeluarga()->with(['rumah.blok.rt', 'anggota'])->first();
            $data['tagihanSaya'] = Tagihan::query()->where('kartu_keluarga_id', $user->kartu_keluarga_id)
                ->belum()->orderBy('periode')->get();
        }

        return view('dashboard.index', $data);
    }
}
