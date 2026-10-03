<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKeluarga;
use App\Models\Blok;
use App\Models\KartuKeluarga;
use App\Models\Rt;
use App\Support\FotoUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KeluargaController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();

        if (! $user->isPengurus()) {
            abort_unless($user->kartu_keluarga_id, 403, 'Akun Anda belum terhubung ke data Kartu Keluarga. Hubungi pengurus RT.');

            return redirect()->route('keluarga.show', $user->kartu_keluarga_id);
        }

        $rtId = $user->isPengurusRt() ? $user->rt_id : ($request->integer('rt') ?: null);
        $blokId = $request->integer('blok') ?: null;
        $q = trim((string) $request->input('q'));
        $status = $request->input('status', 'aktif');

        $keluarga = KartuKeluarga::query()
            ->diRt($rtId)
            ->when($blokId, fn ($x) => $x->whereHas('rumah', fn ($r) => $r->where('blok_id', $blokId)))
            ->when($status === 'aktif', fn ($x) => $x->where('aktif', true))
            ->when($status === 'pindah', fn ($x) => $x->where('aktif', false))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->whereLike('nama_kepala', "%{$q}%")
                ->orWhereLike('no_kk', "%{$q}%")
                ->orWhereHas('anggota', fn ($a) => $a->whereLike('nama', "%{$q}%")->orWhereLike('nik', "%{$q}%"))))
            ->leftJoin('rumahs', 'rumahs.id', '=', 'kartu_keluargas.rumah_id')
            ->leftJoin('bloks', 'bloks.id', '=', 'rumahs.blok_id')
            ->select('kartu_keluargas.*')
            ->with(['rumah.blok.rt'])
            ->withCount('anggota')
            ->orderBy('bloks.nama')
            ->orderByRaw('LENGTH(rumahs.nomor), rumahs.nomor')
            ->orderBy('nama_kepala')
            ->paginate(24)
            ->withQueryString();

        return view('keluarga.index', [
            'keluarga' => $keluarga,
            'rts' => $user->managedRts()->get(),
            'bloks' => Blok::query()->when($rtId, fn ($b) => $b->where('rt_id', $rtId))->orderBy('nama')->get(),
            'filter' => compact('rtId', 'blokId', 'q', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->user()->isPengurus(), 403);

        return view('keluarga.form', [
            'kembali' => $this->urlKembali(),
            'kk' => new KartuKeluarga(['status_tinggal' => 'tetap', 'aktif' => true, 'rumah_id' => $request->integer('rumah') ?: null]),
            'kepala' => new AnggotaKeluarga(['jenis_kelamin' => 'L', 'hubungan' => 'Kepala Keluarga']),
            'rumahOptions' => $this->rumahOptions(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->user()->isPengurus(), 403);

        $data = $this->validasiKk($request);
        $kepala = $request->validate(AnggotaController::aturan(null, 'kepala_'));
        $this->pastikanRumahDikelola($data['rumah_id'] ?? null);

        $kk = DB::transaction(function () use ($request, $data, $kepala) {
            $kepalaData = collect($kepala)->mapWithKeys(fn ($v, $k) => [substr($k, 7) => $v])->except('foto')->all();

            $kk = KartuKeluarga::query()->create([
                ...collect($data)->except(['foto', 'hapus_foto'])->all(),
                'nama_kepala' => $kepalaData['nama'],
                'foto' => $request->hasFile('foto') ? FotoUploader::simpan($request->file('foto'), 'keluarga') : null,
            ]);

            $kk->anggota()->create([
                ...$kepalaData,
                'hubungan' => 'Kepala Keluarga',
                'no_hp' => $kepalaData['no_hp'] ?? $kk->no_hp,
                'foto' => $request->hasFile('kepala_foto') ? FotoUploader::simpan($request->file('kepala_foto'), 'anggota') : null,
            ]);

            return $kk;
        });

        return redirect($this->urlKembali(route('keluarga.show', $kk)))->with('sukses', 'Data keluarga '.$kk->nama_kepala.' tersimpan. Silakan tambahkan anggota keluarga lainnya.');
    }

    public function show(KartuKeluarga $keluarga)
    {
        $keluarga->load(['rumah.blok.rt', 'anggota', 'akun']);
        abort_unless($this->bolehLihatDetailKeluarga($keluarga), 403, 'Anda hanya dapat melihat data keluarga sendiri.');

        return view('keluarga.show', [
            'kk' => $keluarga,
            'bolehKelola' => $this->bolehKelolaKeluarga($keluarga),
            'tagihan' => $keluarga->tagihans()->limit(24)->get(),
        ]);
    }

    public function edit(KartuKeluarga $keluarga)
    {
        $this->pastikanKelolaKeluarga($keluarga);

        return view('keluarga.form', [
            'kembali' => $this->urlKembali(),
            'kk' => $keluarga,
            'kepala' => null,
            'rumahOptions' => $this->rumahOptions(),
        ]);
    }

    public function update(Request $request, KartuKeluarga $keluarga)
    {
        $this->pastikanKelolaKeluarga($keluarga);

        $data = $this->validasiKk($request, $keluarga);
        $this->pastikanRumahDikelola($data['rumah_id'] ?? null);

        if ($request->hasFile('foto')) {
            FotoUploader::hapus($keluarga->foto);
            $data['foto'] = FotoUploader::simpan($request->file('foto'), 'keluarga');
        } elseif ($request->boolean('hapus_foto')) {
            FotoUploader::hapus($keluarga->foto);
            $data['foto'] = null;
        } else {
            unset($data['foto']);
        }
        unset($data['hapus_foto']);

        $keluarga->update($data);

        return redirect($this->urlKembali(route('keluarga.show', $keluarga)))->with('sukses', 'Data keluarga '.$keluarga->nama_kepala.' diperbarui.');
    }

    public function destroy(KartuKeluarga $keluarga)
    {
        $this->pastikanKelolaKeluarga($keluarga);

        if ($keluarga->tagihans()->lunas()->exists()) {
            return back()->with('gagal', 'KK ini sudah memiliki riwayat pembayaran iuran. Tandai sebagai "Sudah pindah" saja agar riwayat kas tetap utuh.');
        }

        DB::transaction(function () use ($keluarga) {
            foreach ($keluarga->anggota as $a) {
                FotoUploader::hapus($a->foto);
            }
            FotoUploader::hapus($keluarga->foto);
            $keluarga->delete();
        });

        return redirect()->route('keluarga.index')->with('sukses', 'Data keluarga dihapus.');
    }

    private function validasiKk(Request $request, ?KartuKeluarga $kk = null): array
    {
        $data = $request->validate([
            'rumah_id' => [$this->user()->isAdmin() ? 'nullable' : 'required', 'exists:rumahs,id'],
            'no_kk' => ['nullable', 'digits:16', Rule::unique('kartu_keluargas', 'no_kk')->ignore($kk?->id)],
            'status_tinggal' => ['required', Rule::in(array_keys(KartuKeluarga::STATUS_TINGGAL))],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'tanggal_masuk' => ['nullable', 'date', 'before_or_equal:today'],
            'aktif' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'max:'.config('siwarga.foto_max_kb')],
            'hapus_foto' => ['nullable', 'boolean'],
        ], [
            'no_kk.digits' => 'Nomor KK harus 16 digit angka.',
            'no_kk.unique' => 'Nomor KK ini sudah terdaftar.',
        ]);

        $data['aktif'] = $kk ? $request->boolean('aktif') : true;

        return $data;
    }

    private function pastikanRumahDikelola(?int $rumahId): void
    {
        if (! $rumahId) {
            return;
        }

        $rtId = \App\Models\Rumah::query()->with('blok')->find($rumahId)?->blok?->rt_id;
        $this->pastikanKelolaRt($rtId);
    }

    /** Opsi rumah dikelompokkan per RT & blok untuk <select>. */
    private function rumahOptions(): array
    {
        $opsi = [];
        $rts = $this->user()->managedRts()->with('bloks.rumahs.keluargaAktif:id,rumah_id')->get();

        foreach ($rts as $rt) {
            foreach ($rt->bloks as $blok) {
                $grup = "RT {$rt->nomor} · Blok {$blok->nama}";
                foreach ($blok->rumahs->sortBy(fn ($r) => [strlen($r->nomor), $r->nomor]) as $r) {
                    $isi = $r->keluargaAktif->count();
                    $opsi[$grup][$r->id] = "{$blok->nama}-{$r->nomor}".($isi ? " ({$isi} KK)" : '');
                }
            }
        }

        return $opsi;
    }
}
