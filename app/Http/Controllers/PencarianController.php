<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKeluarga;
use App\Models\Pengumuman;
use App\Models\Rumah;
use Illuminate\Http\Request;

class PencarianController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $this->user();
        $q = trim((string) $request->input('q'));
        $hasil = ['warga' => collect(), 'rumah' => collect(), 'pengumuman' => collect()];

        if (mb_strlen($q) >= 2) {
            $pengurus = $user->isPengurus();
            $rtKelola = $user->isPengurusRt() ? $user->rt_id : null;

            // Warga: cari nama (semua pengguna); NIK / No. KK hanya untuk pengurus
            $hasil['warga'] = AnggotaKeluarga::query()
                ->whereHas('kartuKeluarga', fn ($k) => $k->aktif())
                ->where(function ($w) use ($q, $pengurus, $rtKelola) {
                    $w->whereLike('nama', "%{$q}%");
                    if ($pengurus) {
                        // NIK / No. KK / No. HP hanya bisa dicari di wilayah yang dikelola
                        $w->orWhere(fn ($s) => $s
                            ->when($rtKelola, fn ($x) => $x->whereHas('kartuKeluarga', fn ($k) => $k->diRt($rtKelola)))
                            ->where(fn ($x) => $x->whereLike('nik', "%{$q}%")
                                ->orWhereLike('no_hp', "%{$q}%")
                                ->orWhereHas('kartuKeluarga', fn ($k) => $k->whereLike('no_kk', "%{$q}%"))));
                    }
                })
                ->with('kartuKeluarga.rumah.blok.rt')
                ->orderBy('nama')
                ->limit(50)
                ->get()
                ->map(function (AnggotaKeluarga $a) use ($user, $rtKelola) {
                    $kk = $a->kartuKeluarga;
                    $a->boleh_detail = $user->isAdmin()
                        || ($user->isPengurusRt() && (int) $kk->rtId() === (int) $rtKelola)
                        || (int) $user->kartu_keluarga_id === (int) $kk->id;

                    return $a;
                });

            // Rumah: "A-12", "A 12", "12", "Blok A"
            $normal = preg_replace('/^blok\s*/i', '', $q);
            [$blok, $nomor] = array_pad(preg_split('/[\s\-\/]+/', $normal, 2), 2, null);

            $hasil['rumah'] = Rumah::query()
                ->with(['blok.rt', 'keluargaAktif:id,rumah_id,nama_kepala,foto'])
                ->where(function ($w) use ($blok, $nomor, $normal) {
                    if ($nomor !== null) {
                        $w->whereHas('blok', fn ($b) => $b->whereLike('nama', $blok))->where('nomor', $nomor);
                    } else {
                        $w->where('nomor', $normal)->orWhereHas('blok', fn ($b) => $b->whereLike('nama', $normal));
                    }
                })
                ->limit(40)
                ->get();

            $hasil['pengumuman'] = Pengumuman::query()->untuk($user)->terbit()
                ->where(fn ($w) => $w->whereLike('judul', "%{$q}%")->orWhereLike('isi', "%{$q}%"))
                ->latest('terbit_pada')->limit(10)->get();
        }

        return view('pencarian.index', compact('q', 'hasil'));
    }
}
