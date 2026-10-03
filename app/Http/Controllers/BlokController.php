<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Rumah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlokController extends Controller
{
    /** Editor denah satu blok: daftar rumah dan posisinya. */
    public function show(Blok $blok)
    {
        $this->pastikanKelolaRt($blok->rt_id);

        $blok->load(['rt', 'rumahs.keluargaAktif:id,rumah_id,nama_kepala']);

        return view('wilayah.blok', [
            'blok' => $blok,
            'maxBaris' => max(2, (int) $blok->rumahs->max('baris')),
            'maxKolom' => max(8, (int) $blok->rumahs->max('kolom')),
            'statusList' => Rumah::STATUS,
            'susun' => RumahController::dataSusun($blok),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rt_id' => ['required', 'exists:rts,id'],
            'nama' => ['required', 'string', 'max:30', Rule::unique('bloks')->where('rt_id', $request->input('rt_id'))],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->pastikanKelolaRt((int) $data['rt_id']);

        $data['urutan'] = (int) ($data['urutan'] ?? 0);
        $blok = Blok::query()->create($data);

        return redirect()->route('blok.show', $blok)->with('sukses', 'Blok ditambahkan. Sekarang tambahkan rumah-rumahnya.');
    }

    public function update(Request $request, Blok $blok)
    {
        $this->pastikanKelolaRt($blok->rt_id);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:30', Rule::unique('bloks')->where('rt_id', $blok->rt_id)->ignore($blok->id)],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $blok->update([
            'nama' => $data['nama'],
            'urutan' => (int) ($data['urutan'] ?? 0),
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return back()->with('sukses', 'Blok diperbarui.');
    }

    public function destroy(Blok $blok)
    {
        $this->pastikanKelolaRt($blok->rt_id);

        if ($blok->rumahs()->whereHas('kartuKeluargas')->exists()) {
            return back()->with('gagal', 'Blok tidak bisa dihapus karena masih ada keluarga yang terdaftar di rumahnya.');
        }

        $blok->delete();

        return redirect()->route('wilayah')->with('sukses', 'Blok dihapus.');
    }
}
