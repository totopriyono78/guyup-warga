<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Donasi;
use App\Models\Galeri;
use App\Models\KartuKeluarga;
use App\Models\Pengaturan;
use App\Models\Pengumuman;
use App\Models\Rt;
use App\Models\Rumah;

/**
 * Halaman umum tanpa login: peta wilayah (rumah diwarnai per RT), informasi umum,
 * dan penggalangan dana. Hanya nama kepala keluarga (bila diizinkan) yang tampil; data pribadi lain tidak dikirim.
 */
class PublikController extends Controller
{
    public function index()
    {
        $rts = Rt::query()->orderBy('nomor')->withCount('rumahs')->get();

        // Kode rumah, RT, warna, koordinat; plus nama kepala keluarga bila diizinkan pengurus RW
        // (Pengaturan > "Nama di peta halaman umum"). NIK, No. KK, HP, dan anggota keluarga tidak pernah dikirim.
        $tampilNama = pengaturan('peta_umum_nama', '1') === '1';
        $titik = Rumah::query()
            ->whereNotNull('lat')->whereNotNull('lng')
            ->join('bloks', 'bloks.id', '=', 'rumahs.blok_id')
            ->join('rts', 'rts.id', '=', 'bloks.rt_id')
            ->when($tampilNama, fn ($q) => $q->with('keluargaAktif:id,rumah_id,nama_kepala'))
            ->get(['rumahs.id', 'rumahs.nomor', 'rumahs.lat', 'rumahs.lng', 'rumahs.status_hunian', 'rumahs.pemilik',
                'bloks.nama as blok', 'rts.id as rt_id', 'rts.nomor as rt', 'rts.warna'])
            ->map(function ($r) use ($tampilNama) {
                $t = [
                    'k' => $r->blok.'-'.$r->nomor,
                    'rt' => $r->rt,
                    'rtId' => $r->rt_id,
                    'w' => $r->warna,
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                ];
                if ($tampilNama) {
                    $nama = $r->keluargaAktif->pluck('nama_kepala')->filter()->values();
                    $t['n'] = $nama->all();                 // kepala keluarga yang tinggal di rumah ini
                    $t['p'] = $nama->isEmpty() ? ($r->pemilik ?: null) : null; // pemilik, bila belum ada KK
                    $t['s'] = $r->status_hunian === 'kosong' ? 'kosong' : ($r->status_hunian === 'usaha' ? 'usaha' : null);
                }

                return $t;
            })->values();

        // Cadangan bila belum ada titik di peta: denah blok sederhana
        $bloks = $titik->isEmpty()
            ? Blok::query()->with(['rt', 'rumahs:id,blok_id,nomor,baris,kolom'])
                ->join('rts', 'rts.id', '=', 'bloks.rt_id')->orderBy('rts.nomor')->orderBy('bloks.urutan')->orderBy('bloks.nama')
                ->select('bloks.*')->get()
            : collect();

        $donasi = Donasi::query()->publik()->with('rt')
            ->withCount('donaturs')->withSum('donaturs', 'nominal')
            ->orderByDesc('aktif')->latest()->limit(6)->get();

        $galeri = Galeri::query()->publik()->with(['sampul', 'fotos' => fn ($q) => $q->limit(1)])
            ->withCount('fotos')->whereHas('fotos')->terbaru()->limit(4)->get();

        return view('publik.index', [
            'galeri' => $galeri,
            'rts' => $rts,
            'titik' => $titik,
            'bloks' => $bloks,
            'statistik' => [
                'rt' => $rts->count(),
                'rumah' => Rumah::query()->count(),
                'kk' => KartuKeluarga::query()->aktif()->count(),
            ],
            'donasi' => $donasi,
            'pengumuman' => Pengumuman::query()->publik()->with('rt')->orderByDesc('penting')->orderByDesc('terbit_pada')->limit(6)->get(),
            'peta' => Pengaturan::petaJs(),
        ]);
    }

    public function pengumuman(Pengumuman $pengumuman)
    {
        abort_unless($pengumuman->publik && $pengumuman->terbit_pada && $pengumuman->terbit_pada->lte(now()), 404);

        return view('publik.pengumuman', ['p' => $pengumuman->load('rt')]);
    }

    public function donasi(Donasi $donasi)
    {
        abort_unless($donasi->publik, 404);

        $donasi->loadCount('donaturs')->loadSum('donaturs', 'nominal')->load('rt');

        return view('publik.donasi', [
            'd' => $donasi,
            // Nominal per donatur TIDAK dikirim ke tampilan
            'donaturs' => $donasi->donaturs()->get(['id', 'nama', 'anonim', 'tanggal'])
                ->map(fn ($x) => ['nama' => $x->nama_tampil, 'tanggal' => $x->tanggal]),
        ]);
    }

    public function galeri()
    {
        return view('publik.galeri', [
            'galeri' => Galeri::query()->publik()->with(['rt', 'sampul', 'fotos' => fn ($q) => $q->limit(1)])
                ->withCount('fotos')->whereHas('fotos')->terbaru()->paginate(12),
        ]);
    }

    public function galeriAlbum(Galeri $galeri)
    {
        abort_unless($galeri->publik, 404);

        return view('publik.galeri-album', ['g' => $galeri->load(['rt', 'fotos'])]);
    }
}
