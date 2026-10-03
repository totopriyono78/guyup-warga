<?php

namespace App\Http\Controllers;

use App\Models\Rt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RtController extends Controller
{
    /** Halaman kelola wilayah: daftar RT & blok. */
    public function index()
    {
        $rts = $this->user()->managedRts()
            ->withCount(['bloks', 'rumahs'])
            ->with(['bloks' => fn ($q) => $q->withCount('rumahs')])
            ->get();

        return view('wilayah.index', compact('rts'));
    }

    public function store(Request $request)
    {
        Rt::query()->create($this->validasi($request));

        return back()->with('sukses', 'RT berhasil ditambahkan.');
    }

    public function update(Request $request, Rt $rt)
    {
        $rt->update($this->validasi($request, $rt));

        return back()->with('sukses', 'Data RT diperbarui.');
    }

    public function destroy(Rt $rt)
    {
        if ($rt->rumahs()->whereHas('kartuKeluargas')->exists()) {
            return back()->with('gagal', 'RT tidak bisa dihapus karena masih ada data keluarga di dalamnya.');
        }

        if (\App\Models\User::query()->where('rt_id', $rt->id)->exists()) {
            return back()->with('gagal', 'RT tidak bisa dihapus karena masih ada akun pengurus RT yang terhubung. Ubah atau hapus akun tersebut dahulu.');
        }

        $rt->delete();

        return back()->with('sukses', 'RT dihapus.');
    }

    private function validasi(Request $request, ?Rt $rt = null): array
    {
        $data = $request->validate([
            'nomor' => ['required', 'string', 'max:5', Rule::unique('rts', 'nomor')->ignore($rt?->id)],
            'nama_ketua' => ['nullable', 'string', 'max:255'],
            'no_hp_ketua' => ['nullable', 'string', 'max:20'],
            'warna' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['nomor'] = ctype_digit($data['nomor']) ? str_pad($data['nomor'], 2, '0', STR_PAD_LEFT) : $data['nomor'];
        $data['warna'] = $data['warna'] ?? ($rt?->warna ?? Rt::warnaBerikutnya());

        return $data;
    }
}
