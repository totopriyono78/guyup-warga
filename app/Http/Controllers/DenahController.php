<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\KartuKeluarga;
use App\Models\Pengaturan;
use App\Models\Rt;
use App\Models\Rumah;
use Illuminate\Http\Request;

class DenahController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();
        $rts = Rt::query()->orderBy('nomor')->get();
        $rtId = $request->integer('rt') ?: null;
        $periode = now()->startOfMonth()->toDateString();
        $pengurus = $user->isPengurus();
        $mode = $pengurus && $request->input('mode') === 'iuran' ? 'iuran' : 'hunian';
        $privasiWarga = config('siwarga.peta.warga', 'nama');
        $rumahSaya = $user->kartuKeluarga?->rumah_id;

        $bloks = Blok::query()
            ->when($rtId, fn ($q) => $q->where('rt_id', $rtId))
            ->with([
                'rt',
                'rumahs.keluargaAktif' => fn ($q) => $q->with([
                    'anggota:id,kartu_keluarga_id,nama,hubungan,jenis_kelamin,foto,tanggal_lahir',
                    'tagihans' => fn ($t) => $t->where('periode', $periode),
                ]),
            ])
            ->join('rts', 'rts.id', '=', 'bloks.rt_id')
            ->orderBy('rts.nomor')->orderBy('bloks.urutan')->orderBy('bloks.nama')
            ->select('bloks.*')
            ->get();

        // Data untuk denah blok & peta (Alpine.js / Leaflet). Warga lain tidak pernah melihat NIK/No. KK.
        $detail = [];
        $adaLokasi = false;

        foreach ($bloks as $blok) {
            $kelola = $user->canManageRt($blok->rt_id);

            foreach ($blok->rumahs as $rumah) {
                // Setelah menyimpan form, kembali ke titik rumah ini di denah/peta
                $kembali = route('denah', array_filter(['tampilan' => $request->input('tampilan'), 'rt' => $rtId, 'mode' => $mode === 'iuran' ? 'iuran' : null])).'#rumah-'.$rumah->id;
                $milikSendiri = $rumahSaya && (int) $rumahSaya === (int) $rumah->id;
                // Privasi untuk warga biasa: rumah orang lain bisa ditampilkan tanpa nama/foto
                $privat = ! $pengurus && ! $milikSendiri && $privasiWarga !== 'nama';
                $sembunyikanDariPeta = ! $pengurus && ! $milikSendiri && $privasiWarga === 'sendiri';

                $adaLokasi = $adaLokasi || ($rumah->punyaLokasi() && ! $sembunyikanDariPeta);

                $detail[$rumah->id] = [
                    'kode' => $blok->nama.'-'.$rumah->nomor,
                    'kelola' => $kelola,
                    'alamat' => 'Blok '.$blok->nama.' No. '.$rumah->nomor.' · RT '.$blok->rt->nomor,
                    'status' => Rumah::STATUS[$rumah->status_hunian] ?? $rumah->status_hunian,
                    'kat' => $this->kategori($rumah, $mode, $kelola),
                    'warnaRt' => $blok->rt->warna,
                    'lat' => $sembunyikanDariPeta ? null : $rumah->lat,
                    'lng' => $sembunyikanDariPeta ? null : $rumah->lng,
                    'privat' => $privat,
                    'jumlahKk' => $rumah->keluargaAktif->count(),
                    'namaSingkat' => $privat ? null : optional($rumah->keluargaAktif->first(), fn ($k) => \Illuminate\Support\Str::of($k->nama_kepala)->before(' ')->toString()),
                    'edit' => $kelola ? route('blok.show', $blok).'#rumah-'.$rumah->id : null,
                    'tambahKk' => $kelola ? route('keluarga.create', ['rumah' => $rumah->id, 'kembali' => $kembali]) : null,
                    'keluarga' => $privat ? [] : $rumah->keluargaAktif->map(function ($kk) use ($user, $kelola, $kembali) {
                        $tagihan = $kk->tagihans->where('sukarela', false);

                        return [
                            'nama' => $kk->nama_kepala,
                            'status' => KartuKeluarga::STATUS_TINGGAL[$kk->status_tinggal] ?? $kk->status_tinggal,
                            'foto' => $kk->fotoUrl(),
                            'hp' => $kelola ? $kk->no_hp : null,
                            'iuran' => $kelola ? ($tagihan->isEmpty() ? 'Tidak ada tagihan' : ($tagihan->every->isLunas() ? 'Lunas' : 'Belum bayar')) : null,
                            'url' => ($kelola || (int) $user->kartu_keluarga_id === (int) $kk->id) ? route('keluarga.show', $kk) : null,
                            'urlEdit' => $kelola ? route('keluarga.edit', ['keluarga' => $kk, 'kembali' => $kembali]) : null,
                            'urlAnggota' => $kelola ? route('anggota.create', ['keluarga' => $kk, 'kembali' => $kembali]) : null,
                            'anggota' => $kk->anggota->map(fn ($a) => [
                                'nama' => $a->nama,
                                'hubungan' => $a->hubungan,
                                'foto' => $a->fotoUrl(),
                                'inisial' => inisial($a->nama),
                                'urlEdit' => $kelola ? route('anggota.edit', ['anggota' => $a, 'kembali' => $kembali]) : null,
                            ])->values(),
                        ];
                    })->values(),
                ];
            }
        }

        $tampilan = $request->input('tampilan');
        if (! in_array($tampilan, ['peta', 'blok'], true)) {
            $tampilan = $adaLokasi ? 'peta' : 'blok';
        }

        // Mode susun: pengurus memindah rumah antar petak/blok dengan seret & lepas
        $susun = $pengurus && $tampilan === 'blok' && $request->boolean('susun');

        return view('denah.index', [
            'susun' => $susun,
            'rts' => $rts,
            'rtId' => $rtId,
            'bloks' => $bloks,
            'detail' => $detail,
            'mode' => $mode,
            'tampilan' => $tampilan,
            'adaLokasi' => $adaLokasi,
            'tanpaLokasi' => $pengurus ? collect($detail)->filter(fn ($d, $id) => $d['lat'] === null)->count() : 0,
            'peta' => Pengaturan::petaJs(),
            'petaWilayah' => pengaturan('peta_wilayah'),
        ]);
    }

    /**
     * Kategori warna petak/titik rumah.
     * hunian: terisi | kontrakan | belum_didata | kosong | usaha
     * iuran : lunas | sebagian | belum | tidak_ada
     */
    private function kategori(Rumah $rumah, string $mode, bool $kelola): string
    {
        $kk = $rumah->keluargaAktif;

        if ($mode === 'iuran') {
            if (! $kelola) {
                return 'tidak_ada';
            }
            // semua tagihan wajib bulan ini (per jenis iuran), sukarela tidak dihitung
            $tagihan = $kk->flatMap(fn ($k) => $k->tagihans->where('sukarela', false));
            if ($tagihan->isEmpty()) {
                return 'tidak_ada';
            }
            $lunas = $tagihan->filter->isLunas()->count();

            return $lunas === $tagihan->count() ? 'lunas' : ($lunas > 0 ? 'sebagian' : 'belum');
        }

        return match (true) {
            $rumah->status_hunian === 'kosong' => 'kosong',
            $rumah->status_hunian === 'usaha' => 'usaha',
            $kk->isEmpty() => 'belum_didata',
            $rumah->status_hunian === 'kontrakan' => 'kontrakan',
            default => 'terisi',
        };
    }
}
