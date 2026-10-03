<?php

namespace App\Http\Controllers;

use App\Models\Galeri;
use App\Models\GaleriFoto;
use App\Support\FotoUploader;
use Illuminate\Http\Request;

/**
 * Galeri foto kegiatan. Semua warga yang login dapat melihat;
 * pengurus RW mengelola semua album, pengurus RT mengelola album RT-nya.
 */
class GaleriController extends Controller
{
    public const MAKS_FOTO_SEKALI = 20;

    public function index(Request $request)
    {
        $galeri = Galeri::query()
            ->with(['rt', 'sampul', 'fotos' => fn ($q) => $q->limit(1)])
            ->withCount('fotos')
            ->when($request->filled('q'), fn ($q) => $q->whereLike('judul', '%'.$request->string('q')->trim().'%'))
            ->terbaru()
            ->paginate(12)->withQueryString();

        return view('galeri.index', [
            'galeri' => $galeri,
            'bolehBuat' => $this->user()->isPengurus() && ($this->user()->isAdmin() || $this->user()->rt_id),
        ]);
    }

    public function show(Galeri $galeri)
    {
        $galeri->load(['rt', 'pembuat:id,name', 'fotos']);

        return view('galeri.show', [
            'g' => $galeri,
            'bolehKelola' => $this->bolehKelola($galeri),
        ]);
    }

    public function create()
    {
        abort_unless($this->user()->isPengurus(), 403);

        return view('galeri.form', [
            'g' => new Galeri(['publik' => true, 'tanggal' => now()]),
            'rts' => $this->user()->managedRts()->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->user()->isPengurus(), 403);

        $data = $this->validasi($request);
        $request->validate($this->aturanFoto(true), $this->pesanFoto());

        $galeri = Galeri::query()->create($data + ['user_id' => $this->user()->id]);
        $jumlah = $this->simpanFoto($request, $galeri);

        return redirect()->route('galeri.show', $galeri)->with('sukses', "Album dibuat dengan {$jumlah} foto.");
    }

    public function edit(Galeri $galeri)
    {
        abort_unless($this->bolehKelola($galeri), 403);

        return view('galeri.form', ['g' => $galeri, 'rts' => $this->user()->managedRts()->get()]);
    }

    public function update(Request $request, Galeri $galeri)
    {
        abort_unless($this->bolehKelola($galeri), 403);

        $galeri->update($this->validasi($request));

        return redirect()->route('galeri.show', $galeri)->with('sukses', 'Album diperbarui.');
    }

    public function destroy(Galeri $galeri)
    {
        abort_unless($this->bolehKelola($galeri), 403);

        foreach ($galeri->fotos as $foto) {
            FotoUploader::hapus($foto->path);
        }
        $galeri->delete();

        return redirect()->route('galeri.index')->with('sukses', 'Album dan semua fotonya dihapus.');
    }

    public function tambahFoto(Request $request, Galeri $galeri)
    {
        abort_unless($this->bolehKelola($galeri), 403);
        $request->validate($this->aturanFoto(false), $this->pesanFoto());

        $jumlah = $this->simpanFoto($request, $galeri);

        return back()->with('sukses', "{$jumlah} foto ditambahkan.");
    }

    public function ubahFoto(Request $request, GaleriFoto $foto)
    {
        abort_unless($this->bolehKelola($foto->galeri), 403);

        $data = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:255'],
            'sampul' => ['nullable', 'boolean'],
        ]);

        if ($request->has('keterangan')) {
            $foto->update(['keterangan' => $data['keterangan'] ?? null]);
        }
        if ($request->boolean('sampul')) {
            $foto->galeri->update(['sampul_id' => $foto->id]);
        }

        return back()->with('sukses', $request->boolean('sampul') ? 'Sampul album diganti.' : 'Keterangan foto disimpan.');
    }

    public function hapusFoto(GaleriFoto $foto)
    {
        $galeri = $foto->galeri;
        abort_unless($this->bolehKelola($galeri), 403);

        FotoUploader::hapus($foto->path);
        if ((int) $galeri->sampul_id === (int) $foto->id) {
            $galeri->update(['sampul_id' => null]);
        }
        $foto->delete();

        return back()->with('sukses', 'Foto dihapus.');
    }

    private function bolehKelola(Galeri $g): bool
    {
        $user = $this->user();

        return $g->rt_id ? $user->canManageRt($g->rt_id) : $user->isAdmin();
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'tanggal' => ['nullable', 'date'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'rt_id' => ['nullable', 'exists:rts,id'],
            'publik' => ['nullable', 'boolean'],
        ]);

        $user = $this->user();
        if (! $user->isAdmin()) {
            abort_unless($user->rt_id, 403);
            $data['rt_id'] = $user->rt_id;
        }
        $data['publik'] = $request->boolean('publik');

        return $data;
    }

    private function aturanFoto(bool $bolehKosong): array
    {
        return [
            'fotos' => [$bolehKosong ? 'nullable' : 'required', 'array', 'max:'.self::MAKS_FOTO_SEKALI],
            'fotos.*' => ['image', 'max:'.config('siwarga.foto_max_kb')],
        ];
    }

    private function pesanFoto(): array
    {
        return [
            'fotos.required' => 'Pilih minimal satu foto.',
            'fotos.max' => 'Maksimal '.self::MAKS_FOTO_SEKALI.' foto sekali unggah.',
            'fotos.*.image' => 'Berkas harus berupa gambar (JPG/PNG/WebP).',
            'fotos.*.max' => 'Ukuran tiap foto maksimal '.round(config('siwarga.foto_max_kb') / 1024).' MB.',
        ];
    }

    private function simpanFoto(Request $request, Galeri $galeri): int
    {
        $urutan = (int) GaleriFoto::query()->where('galeri_id', $galeri->id)->max('urutan'); // tanpa ORDER BY (PostgreSQL)
        $jumlah = 0;
        // foto kegiatan sedikit lebih besar dari foto profil supaya tetap tajam saat diperbesar
        config(['siwarga.foto_max_px' => max(1600, (int) config('siwarga.foto_max_px'))]);

        foreach ((array) $request->file('fotos', []) as $file) {
            $galeri->fotos()->create([
                'path' => FotoUploader::simpan($file, 'galeri/'.$galeri->id),
                'urutan' => ++$urutan,
            ]);
            $jumlah++;
        }

        return $jumlah;
    }
}
