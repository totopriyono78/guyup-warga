<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Rumah;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class RumahController extends Controller
{
    public function store(Request $request, Blok $blok)
    {
        $this->pastikanKelolaRt($blok->rt_id);

        $data = $this->validasi($request, $blok);
        $blok->rumahs()->create($data);

        return back()->with('sukses', "Rumah No. {$data['nomor']} ditambahkan.");
    }

    /** Tambah banyak rumah sekaligus, mis. nomor 1–20 dalam 2 baris. */
    public function storeMassal(Request $request, Blok $blok)
    {
        $this->pastikanKelolaRt($blok->rt_id);

        $data = $request->validate([
            'dari' => ['required', 'integer', 'min:1', 'max:9999'],
            'sampai' => ['required', 'integer', 'gte:dari', 'max:9999'],
            'jumlah_baris' => ['required', 'integer', 'min:1', 'max:6'],
            'awalan' => ['nullable', 'string', 'max:4'],
        ]);

        $jumlah = $data['sampai'] - $data['dari'] + 1;
        abort_if($jumlah > 200, 422, 'Maksimal 200 rumah sekali tambah.');

        $perBaris = (int) ceil($jumlah / $data['jumlah_baris']);
        $barisAwal = (int) Rumah::query()->where('blok_id', $blok->id)->max('baris');
        $dibuat = 0;

        for ($i = 0; $i < $jumlah; $i++) {
            $nomor = ($data['awalan'] ?? '').($data['dari'] + $i);
            if ($blok->rumahs()->where('nomor', $nomor)->exists()) {
                continue;
            }

            $blok->rumahs()->create([
                'nomor' => $nomor,
                'baris' => $barisAwal + intdiv($i, $perBaris) + 1,
                'kolom' => $i % $perBaris + 1,
                'status_hunian' => 'dihuni',
            ]);
            $dibuat++;
        }

        return back()->with('sukses', "{$dibuat} rumah ditambahkan ke Blok {$blok->nama}.");
    }

    /**
     * Susun ulang denah blok lewat drag & drop (AJAX).
     *  - pindah       : rumah_id ke baris/kolom kosong
     *  - tukar_posisi : dua rumah bertukar baris/kolom
     *  - tukar_nomor  : dua rumah bertukar nomor (posisi, titik peta & KK tetap di tempatnya)
     *  - nomor        : ganti nomor satu rumah
     */
    public function susun(Request $request, Blok $blok)
    {
        $this->pastikanKelolaRt($blok->rt_id);

        $data = $request->validate([
            'aksi' => ['required', Rule::in(['pindah', 'tukar_posisi', 'tukar_nomor', 'nomor'])],
            'rumah_id' => ['required', 'integer'],
            'target_id' => ['required_if:aksi,tukar_posisi,tukar_nomor', 'nullable', 'integer', 'different:rumah_id'],
            'baris' => ['required_if:aksi,pindah', 'nullable', 'integer', 'min:1', 'max:50'],
            'kolom' => ['required_if:aksi,pindah', 'nullable', 'integer', 'min:1', 'max:100'],
            'nomor' => ['required_if:aksi,nomor', 'nullable', 'string', 'max:10'],
        ]);

        DB::transaction(function () use ($blok, $data) {
            $ids = array_filter([$data['rumah_id'], $data['target_id'] ?? null]);
            $rumah = Rumah::query()->where('blok_id', $blok->id)->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $a = $rumah->get($data['rumah_id']);
            $b = isset($data['target_id']) ? $rumah->get($data['target_id']) : null;
            if (! $a || (in_array($data['aksi'], ['tukar_posisi', 'tukar_nomor'], true) && ! $b)) {
                throw ValidationException::withMessages(['rumah_id' => 'Rumah tidak ditemukan di blok ini. Muat ulang halaman.']);
            }

            switch ($data['aksi']) {
                case 'pindah':
                    $terisi = Rumah::query()->where('blok_id', $blok->id)->where('baris', $data['baris'])
                        ->where('kolom', $data['kolom'])->whereKeyNot($a->id)->first();
                    if ($terisi) {
                        throw ValidationException::withMessages(['kolom' => "Posisi itu sudah dipakai rumah No. {$terisi->nomor}."]);
                    }
                    $a->update(['baris' => $data['baris'], 'kolom' => $data['kolom']]);
                    break;

                case 'tukar_posisi':
                    [$ba, $ka, $bb, $kb] = [$a->baris, $a->kolom, $b->baris, $b->kolom];
                    $a->update(['baris' => 0, 'kolom' => 0]); // posisi sementara agar tidak bentrok unique
                    $b->update(['baris' => $ba, 'kolom' => $ka]);
                    $a->update(['baris' => $bb, 'kolom' => $kb]);
                    break;

                case 'tukar_nomor':
                    [$na, $nb] = [$a->nomor, $b->nomor];
                    $a->update(['nomor' => mb_substr('~'.$a->id, 0, 10)]); // nomor sementara
                    $b->update(['nomor' => $na]);
                    $a->update(['nomor' => $nb]);
                    break;

                case 'nomor':
                    $nomor = trim($data['nomor']);
                    $dipakai = Rumah::query()->where('blok_id', $blok->id)->where('nomor', $nomor)->whereKeyNot($a->id)->exists();
                    if ($nomor === '' || $dipakai) {
                        throw ValidationException::withMessages(['nomor' => "Nomor {$nomor} sudah dipakai rumah lain di Blok {$blok->nama}. Gunakan \"tukar nomor\"."]);
                    }
                    $a->update(['nomor' => $nomor]);
                    break;
            }
        });

        return response()->json(['ok' => true, 'rumahs' => self::dataSusun($blok)]);
    }

    /**
     * Pindahkan rumah lewat drag & drop di "Denah blok" (AJAX), di blok yang sama maupun antar blok.
     * Keluarga (KK) tetap melekat pada rumahnya, jadi warga ikut pindah. Titik peta tidak berubah.
     *  - pindah : rumah_id ke petak kosong blok_id/baris/kolom (opsional nomor baru bila nomornya bentrok)
     *  - tukar  : rumah_id dan target_id bertukar tempat (blok + baris/kolom)
     */
    public function pindahDenah(Request $request)
    {
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['pindah', 'tukar'])],
            'rumah_id' => ['required', 'integer'],
            'target_id' => ['required_if:aksi,tukar', 'nullable', 'integer', 'different:rumah_id'],
            'blok_id' => ['required_if:aksi,pindah', 'nullable', 'integer'],
            'baris' => ['required_if:aksi,pindah', 'nullable', 'integer', 'min:1', 'max:50'],
            'kolom' => ['required_if:aksi,pindah', 'nullable', 'integer', 'min:1', 'max:100'],
            'nomor' => ['nullable', 'string', 'max:10'],
        ]);

        $pesan = DB::transaction(function () use ($data) {
            $a = Rumah::query()->with('blok')->lockForUpdate()->findOrFail($data['rumah_id']);
            $this->pastikanKelolaRt($a->blok->rt_id);
            $asal = $a->blok;

            if ($data['aksi'] === 'tukar') {
                $b = Rumah::query()->with('blok')->lockForUpdate()->findOrFail($data['target_id']);
                $this->pastikanKelolaRt($b->blok->rt_id);
                $blokA = $a->blok;
                $blokB = $b->blok;

                if ($blokA->id !== $blokB->id) {
                    foreach ([[$a, $blokB, $b], [$b, $blokA, $a]] as [$r, $tujuan, $kecuali]) {
                        $bentrok = Rumah::query()->where('blok_id', $tujuan->id)->where('nomor', $r->nomor)
                            ->whereKeyNot($kecuali->id)->exists();
                        if ($bentrok) {
                            throw ValidationException::withMessages([
                                'nomor' => "Tidak bisa bertukar: nomor {$r->nomor} sudah ada di Blok {$tujuan->nama}. Seret ke petak kosong saja, nanti Anda bisa memberi nomor baru.",
                            ]);
                        }
                    }
                }

                [$posA, $posB] = [[$a->blok_id, $a->baris, $a->kolom], [$b->blok_id, $b->baris, $b->kolom]];
                // posisi & nomor sementara agar tidak bentrok dengan aturan unik
                $nomorA = $a->nomor;
                $a->update(['baris' => 0, 'kolom' => 0, 'nomor' => mb_substr('~'.$a->id, 0, 10)]);
                $b->update(['blok_id' => $posA[0], 'baris' => $posA[1], 'kolom' => $posA[2]]);
                $a->update(['blok_id' => $posB[0], 'baris' => $posB[1], 'kolom' => $posB[2], 'nomor' => $nomorA]);

                return $blokA->id === $blokB->id
                    ? "Rumah No. {$nomorA} dan No. {$b->nomor} bertukar tempat."
                    : "Rumah {$blokA->nama}-{$nomorA} dan {$blokB->nama}-{$b->nomor} bertukar blok.";
            }

            $tujuan = Blok::query()->findOrFail($data['blok_id']);
            $this->pastikanKelolaRt($tujuan->rt_id);

            $terisi = Rumah::query()->where('blok_id', $tujuan->id)->where('baris', $data['baris'])
                ->where('kolom', $data['kolom'])->whereKeyNot($a->id)->first();
            if ($terisi) {
                throw ValidationException::withMessages(['kolom' => "Petak itu sudah dipakai rumah No. {$terisi->nomor}."]);
            }

            $nomor = trim((string) ($data['nomor'] ?? '')) ?: $a->nomor;
            $dipakai = Rumah::query()->where('blok_id', $tujuan->id)->where('nomor', $nomor)->whereKeyNot($a->id)->exists();
            if ($dipakai) {
                throw new HttpResponseException(response()->json([
                    'message' => "Nomor {$nomor} sudah ada di Blok {$tujuan->nama}. Beri nomor baru untuk rumah ini.",
                    'nomor_bentrok' => true,
                    'saran' => self::saranNomor($tujuan->id),
                    'blok' => $tujuan->nama,
                ], 422));
            }

            $lama = $asal->nama.'-'.$a->nomor;
            $a->update(['blok_id' => $tujuan->id, 'baris' => $data['baris'], 'kolom' => $data['kolom'], 'nomor' => $nomor]);

            return $asal->id === $tujuan->id && $nomor === explode('-', $lama, 2)[1]
                ? "Rumah {$lama} dipindah."
                : "Rumah {$lama} dipindah ke Blok {$tujuan->nama} No. {$nomor}.";
        });

        return response()->json(['ok' => true, 'pesan' => $pesan]);
    }

    /** Nomor rumah berikutnya yang belum dipakai di blok (angka terbesar + 1). */
    public static function saranNomor(int $blokId): string
    {
        $nomor = Rumah::query()->where('blok_id', $blokId)->pluck('nomor');
        $n = (int) $nomor->map(fn ($x) => (int) preg_replace('/\D.*/', '', $x))->max() + 1;
        while ($nomor->contains((string) $n)) {
            $n++;
        }

        return (string) $n;
    }

    /** Data ringkas rumah di blok untuk editor susunan. */
    public static function dataSusun(Blok $blok): array
    {
        return Rumah::query()->where('blok_id', $blok->id)
            ->with('keluargaAktif:id,rumah_id,nama_kepala')
            ->get()
            ->map(fn (Rumah $r) => [
                'id' => $r->id,
                'nomor' => $r->nomor,
                'baris' => $r->baris,
                'kolom' => $r->kolom,
                'status' => $r->status_hunian,
                'kk' => $r->keluargaAktif->pluck('nama_kepala')->join(', '),
                'adaTitik' => $r->punyaLokasi(),
            ])->values()->all();
    }

    /** Simpan / hapus titik rumah di peta (AJAX dari editor peta). */
    public function lokasi(Request $request, Rumah $rumah)
    {
        $this->pastikanKelolaRt($rumah->blok->rt_id);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ]);

        $rumah->update(['lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null]);

        return response()->json(['ok' => true, 'rumah' => PetaController::dataRumah($rumah->fresh('blok.rt', 'keluargaAktif'))]);
    }

    /** Buat rumah baru langsung dari titik di peta (AJAX). */
    public function storeDiPeta(Request $request)
    {
        $blok = Blok::query()->findOrFail($request->integer('blok_id'));
        $this->pastikanKelolaRt($blok->rt_id);

        $data = $request->validate([
            'blok_id' => ['required', 'exists:bloks,id'],
            'nomor' => ['required', 'string', 'max:10', Rule::unique('rumahs')->where('blok_id', $blok->id)],
            'status_hunian' => ['nullable', Rule::in(array_keys(Rumah::STATUS))],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'nomor.unique' => 'Nomor rumah ini sudah ada di Blok '.$blok->nama.'. Pilih rumahnya dari daftar untuk memindahkan titiknya.',
        ]);

        [$baris, $kolom] = Rumah::posisiKosong($blok->id);

        $rumah = $blok->rumahs()->create([
            'nomor' => $data['nomor'],
            'baris' => $baris,
            'kolom' => $kolom,
            'status_hunian' => $data['status_hunian'] ?? 'dihuni',
            'lat' => $data['lat'],
            'lng' => $data['lng'],
        ]);

        return response()->json(['ok' => true, 'rumah' => PetaController::dataRumah($rumah->load('blok.rt', 'keluargaAktif'))], 201);
    }

    public function update(Request $request, Rumah $rumah)
    {
        $this->pastikanKelolaRt($rumah->blok->rt_id);

        $rumah->update($this->validasi($request, $rumah->blok, $rumah));

        return back()->with('sukses', "Rumah No. {$rumah->nomor} diperbarui.");
    }

    public function destroy(Rumah $rumah)
    {
        $this->pastikanKelolaRt($rumah->blok->rt_id);

        if ($rumah->kartuKeluargas()->exists()) {
            return back()->with('gagal', 'Rumah tidak bisa dihapus karena masih ada KK yang terdaftar. Pindahkan KK terlebih dahulu.');
        }

        $rumah->delete();

        return back()->with('sukses', 'Rumah dihapus.');
    }

    private function validasi(Request $request, Blok $blok, ?Rumah $rumah = null): array
    {
        $data = $request->validate([
            'nomor' => ['required', 'string', 'max:10', Rule::unique('rumahs')->where('blok_id', $blok->id)->ignore($rumah?->id)],
            'baris' => ['required', 'integer', 'min:1', 'max:50'],
            'kolom' => ['required', 'integer', 'min:1', 'max:100'],
            'status_hunian' => ['required', Rule::in(array_keys(Rumah::STATUS))],
            'pemilik' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'nomor.unique' => 'Nomor rumah ini sudah ada di Blok '.$blok->nama.'.',
        ]);

        $bentrok = Rumah::query()->where('blok_id', $blok->id)
            ->where('baris', $data['baris'])->where('kolom', $data['kolom'])
            ->when($rumah, fn ($q) => $q->whereKeyNot($rumah->id))
            ->first();

        if ($bentrok) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kolom' => "Posisi baris {$data['baris']} kolom {$data['kolom']} sudah dipakai rumah No. {$bentrok->nomor}.",
            ]);
        }

        return $data;
    }
}
