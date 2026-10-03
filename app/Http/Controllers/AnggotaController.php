<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKeluarga;
use App\Models\KartuKeluarga;
use App\Support\FotoUploader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnggotaController extends Controller
{
    /** Aturan validasi anggota; $prefix dipakai di form KK baru ("kepala_"). */
    public static function aturan(?AnggotaKeluarga $anggota = null, string $prefix = ''): array
    {
        $r = [
            'nik' => ['nullable', 'digits:16', Rule::unique('anggota_keluargas', 'nik')->ignore($anggota?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'agama' => ['nullable', Rule::in(AnggotaKeluarga::AGAMA)],
            'pendidikan' => ['nullable', Rule::in(AnggotaKeluarga::PENDIDIKAN)],
            'pekerjaan' => ['nullable', 'string', 'max:60'],
            'status_perkawinan' => ['nullable', Rule::in(AnggotaKeluarga::STATUS_PERKAWINAN)],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'foto' => ['nullable', 'image', 'max:'.config('siwarga.foto_max_kb')],
        ];

        if ($prefix === '') {
            $r['hubungan'] = ['required', Rule::in(AnggotaKeluarga::HUBUNGAN)];
            $r['urutan'] = ['nullable', 'integer', 'min:0', 'max:99'];
        }

        return collect($r)->mapWithKeys(fn ($v, $k) => [$prefix.$k => $v])->all();
    }

    public function create(KartuKeluarga $keluarga)
    {
        $this->pastikanKelolaKeluarga($keluarga);

        return view('anggota.form', [
            'kembali' => $this->urlKembali(),
            'kk' => $keluarga,
            'anggota' => new AnggotaKeluarga([
                'hubungan' => $keluarga->kepala()->exists() ? 'Anak' : 'Kepala Keluarga',
                'jenis_kelamin' => 'L',
            ]),
        ]);
    }

    public function store(Request $request, KartuKeluarga $keluarga)
    {
        $this->pastikanKelolaKeluarga($keluarga);

        $data = $request->validate(self::aturan(), $this->pesan());
        $this->cekKepalaGanda($keluarga, $data['hubungan']);

        $data['foto'] = $request->hasFile('foto') ? FotoUploader::simpan($request->file('foto'), 'anggota') : null;
        $data['urutan'] = (int) ($data['urutan'] ?? AnggotaKeluarga::query()->where('kartu_keluarga_id', $keluarga->id)->count());

        $keluarga->anggota()->create($data);
        $keluarga->sinkronNamaKepala();

        return redirect($this->urlKembali(route('keluarga.show', $keluarga)))->with('sukses', "{$data['nama']} ditambahkan ke keluarga.");
    }

    public function edit(AnggotaKeluarga $anggota)
    {
        $this->pastikanKelolaKeluarga($anggota->kartuKeluarga);

        return view('anggota.form', ['kk' => $anggota->kartuKeluarga, 'anggota' => $anggota, 'kembali' => $this->urlKembali()]);
    }

    public function update(Request $request, AnggotaKeluarga $anggota)
    {
        $kk = $anggota->kartuKeluarga;
        $this->pastikanKelolaKeluarga($kk);

        $data = $request->validate(self::aturan($anggota), $this->pesan());
        $this->cekKepalaGanda($kk, $data['hubungan'], $anggota);

        if ($request->hasFile('foto')) {
            FotoUploader::hapus($anggota->foto);
            $data['foto'] = FotoUploader::simpan($request->file('foto'), 'anggota');
        } elseif ($request->boolean('hapus_foto')) {
            FotoUploader::hapus($anggota->foto);
            $data['foto'] = null;
        } else {
            unset($data['foto']);
        }
        $data['urutan'] = (int) ($data['urutan'] ?? $anggota->urutan);

        $anggota->update($data);
        $kk->sinkronNamaKepala();

        return redirect($this->urlKembali(route('keluarga.show', $kk)))->with('sukses', 'Data '.$anggota->nama.' diperbarui.');
    }

    public function destroy(AnggotaKeluarga $anggota)
    {
        $kk = $anggota->kartuKeluarga;
        $this->pastikanKelolaKeluarga($kk);

        if ($anggota->hubungan === 'Kepala Keluarga' && AnggotaKeluarga::query()->where('kartu_keluarga_id', $kk->id)->count() > 1) {
            return back()->with('gagal', 'Kepala keluarga tidak bisa dihapus selama masih ada anggota lain. Ubah dulu status kepala keluarga ke anggota lain.');
        }

        FotoUploader::hapus($anggota->foto);
        $anggota->delete();

        return redirect($this->urlKembali(route('keluarga.show', $kk)))->with('sukses', 'Anggota keluarga dihapus.');
    }

    private function cekKepalaGanda(KartuKeluarga $kk, string $hubungan, ?AnggotaKeluarga $kecuali = null): void
    {
        if ($hubungan !== 'Kepala Keluarga') {
            return;
        }

        $ada = $kk->anggota()->where('hubungan', 'Kepala Keluarga')
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))->exists();

        if ($ada) {
            throw ValidationException::withMessages(['hubungan' => 'Keluarga ini sudah memiliki Kepala Keluarga. Ubah dulu status kepala keluarga yang lama.']);
        }
    }

    private function pesan(): array
    {
        return [
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK ini sudah terdaftar pada warga lain.',
        ];
    }
}
