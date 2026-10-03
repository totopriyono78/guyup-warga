<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Models\Rumah;
use Illuminate\Http\Request;

/** Editor titik rumah di peta (khusus pengurus). */
class PetaController extends Controller
{
    public function edit()
    {
        $rts = $this->user()->managedRts()
            ->with(['bloks.rumahs.keluargaAktif:id,rumah_id,nama_kepala,foto'])
            ->get();

        $bloks = [];
        $rumahs = [];
        foreach ($rts as $rt) {
            foreach ($rt->bloks as $blok) {
                $bloks[] = ['id' => $blok->id, 'label' => 'Blok '.$blok->nama.' · RT '.$rt->nomor, 'warna' => $rt->warna];
                foreach ($blok->rumahs as $rumah) {
                    $rumah->setRelation('blok', $blok->setRelation('rt', $rt));
                    $rumahs[] = self::dataRumah($rumah);
                }
            }
        }

        usort($rumahs, fn ($a, $b) => [$a['rt'], $a['blok'], strlen($a['nomor']), $a['nomor']] <=> [$b['rt'], $b['blok'], strlen($b['nomor']), $b['nomor']]);

        return view('wilayah.peta', [
            'rumahs' => $rumahs,
            'bloks' => $bloks,
            'peta' => Pengaturan::petaJs(),
            'statusList' => Rumah::STATUS,
        ]);
    }

    /** Simpan posisi & zoom awal peta (pengurus RW). */
    public function simpanAwal(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'zoom' => ['required', 'integer', 'between:3,22'],
        ]);

        Pengaturan::simpan(['peta_lat' => $data['lat'], 'peta_lng' => $data['lng'], 'peta_zoom' => $data['zoom']]);

        return response()->json(['ok' => true]);
    }

    public static function dataRumah(Rumah $rumah): array
    {
        $blok = $rumah->blok;
        $kembali = route('peta.edit').'#rumah-'.$rumah->id;

        return [
            'id' => $rumah->id,
            'nomor' => $rumah->nomor,
            'blok' => $blok->nama,
            'blok_id' => $blok->id,
            'rt' => $blok->rt->nomor,
            'warna' => $blok->rt->warna,
            'kode' => $blok->nama.'-'.$rumah->nomor,
            'status' => $rumah->status_hunian,
            'kk' => $rumah->keluargaAktif->pluck('nama_kepala')->join(', '),
            'keluarga' => $rumah->keluargaAktif->map(fn ($kk) => [
                'id' => $kk->id,
                'nama' => $kk->nama_kepala,
                'foto' => $kk->fotoUrl(),
                'urlEdit' => route('keluarga.edit', ['keluarga' => $kk, 'kembali' => $kembali]),
                'urlAnggota' => route('anggota.create', ['keluarga' => $kk, 'kembali' => $kembali]),
                'urlShow' => route('keluarga.show', $kk),
            ])->values()->all(),
            'lat' => $rumah->lat,
            'lng' => $rumah->lng,
            'urlBlok' => route('blok.show', $blok).'#rumah-'.$rumah->id,
            'urlKk' => route('keluarga.create', ['rumah' => $rumah->id, 'kembali' => $kembali]),
        ];
    }
}
