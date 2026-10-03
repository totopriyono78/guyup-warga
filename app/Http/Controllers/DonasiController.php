<?php

namespace App\Http\Controllers;

use App\Models\Donasi;
use App\Models\DonasiDonatur;
use App\Models\KartuKeluarga;
use App\Models\Pembayaran;
use App\Services\AinoException;
use App\Services\PembayaranService;
use App\Support\FotoUploader;
use Illuminate\Http\Request;

/** Kelola penggalangan dana (pengurus RW untuk semua, pengurus RT untuk RT-nya). */
class DonasiController extends Controller
{
    public function index()
    {
        $user = $this->user();

        $donasi = Donasi::query()
            ->with('rt')
            ->when($user->isPengurusRt(), fn ($q) => $q->where(fn ($w) => $w->whereNull('rt_id')->orWhere('rt_id', $user->rt_id)))
            ->withCount('donaturs')->withSum('donaturs', 'nominal')
            ->orderByDesc('aktif')->latest()
            ->paginate(20);

        return view('donasi.index', compact('donasi'));
    }

    public function create()
    {
        return view('donasi.form', [
            'd' => new Donasi(['publik' => true, 'aktif' => true, 'tampilkan_total' => true, 'terima_qris' => true, 'mulai' => now()]),
            'rts' => $this->user()->managedRts()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $data['user_id'] = $this->user()->id;
        $data['gambar'] = $request->hasFile('gambar') ? FotoUploader::simpan($request->file('gambar'), 'donasi') : null;

        $d = Donasi::query()->create($data);

        return redirect()->route('donasi.show', $d)->with('sukses', 'Penggalangan dana dibuat. Catat donatur yang masuk di halaman ini.');
    }

    public function show(Donasi $donasi)
    {
        $this->pastikanBolehLihat($donasi);
        $donasi->loadCount('donaturs')->loadSum('donaturs', 'nominal')->load('rt');

        return view('donasi.show', [
            'd' => $donasi,
            'donaturs' => $donasi->donaturs()->with(['pencatat:id,name', 'kartuKeluarga.rumah.blok'])->get(),
            'bolehKelola' => $this->bolehKelola($donasi),
            'qris' => $donasi->pembayarans()->latest()->limit(20)->get(),
            'kkOptions' => KartuKeluarga::query()->aktif()->dikelolaOleh($this->user())->with('rumah.blok')
                ->orderBy('nama_kepala')->get()
                ->mapWithKeys(fn ($kk) => [$kk->id => $kk->nama_kepala.($kk->rumah ? ' — '.$kk->rumah->kode : '')]),
        ]);
    }

    public function edit(Donasi $donasi)
    {
        abort_unless($this->bolehKelola($donasi), 403);

        return view('donasi.form', ['d' => $donasi, 'rts' => $this->user()->managedRts()->get()]);
    }

    public function update(Request $request, Donasi $donasi)
    {
        abort_unless($this->bolehKelola($donasi), 403);

        $data = $this->validasi($request, $donasi);
        if ($request->hasFile('gambar')) {
            FotoUploader::hapus($donasi->gambar);
            $data['gambar'] = FotoUploader::simpan($request->file('gambar'), 'donasi');
        } elseif ($request->boolean('hapus_gambar')) {
            FotoUploader::hapus($donasi->gambar);
            $data['gambar'] = null;
        }

        $donasi->update($data);

        return redirect()->route('donasi.show', $donasi)->with('sukses', 'Penggalangan dana diperbarui.');
    }

    public function destroy(Donasi $donasi)
    {
        abort_unless($this->bolehKelola($donasi), 403);

        if ($donasi->donaturs()->exists()) {
            $donasi->update(['aktif' => false, 'publik' => false]);

            return redirect()->route('donasi.index')->with('sukses', 'Penggalangan dana sudah punya donatur, jadi ditutup dan disembunyikan dari halaman umum (tidak dihapus).');
        }

        FotoUploader::hapus($donasi->gambar);
        $donasi->delete();

        return redirect()->route('donasi.index')->with('sukses', 'Penggalangan dana dihapus.');
    }

    public function tambahDonatur(Request $request, Donasi $donasi)
    {
        abort_unless($this->bolehKelola($donasi), 403);

        $data = $request->validate([
            'kartu_keluarga_id' => ['nullable', 'exists:kartu_keluargas,id'],
            'nama' => ['required_without:kartu_keluarga_id', 'nullable', 'string', 'max:255'],
            'nominal' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'anonim' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'nama.required_without' => 'Isi nama donatur atau pilih keluarga warga.',
        ]);

        if ($data['kartu_keluarga_id'] ?? null) {
            $kk = KartuKeluarga::query()->dikelolaOleh($this->user())->find($data['kartu_keluarga_id']);
            abort_unless($kk, 403, 'Keluarga tersebut di luar wilayah Anda.');
            $data['nama'] = filled($data['nama'] ?? null) ? $data['nama'] : 'Kel. '.$kk->nama_kepala;
        }

        $donasi->donaturs()->create([
            ...$data,
            'anonim' => $request->boolean('anonim'),
            'dicatat_oleh' => $this->user()->id,
        ]);

        return back()->with('sukses', 'Donasi '.rupiah($data['nominal']).' dari '.($request->boolean('anonim') ? 'donatur anonim' : $data['nama']).' dicatat.');
    }

    public function hapusDonatur(DonasiDonatur $donatur)
    {
        abort_unless($this->bolehKelola($donatur->donasi), 403);
        $donatur->delete();

        return back()->with('sukses', 'Catatan donatur dihapus.');
    }

    /** Pengurus: cek ulang status transaksi donasi QRIS ke AINO. */
    public function cekQris(Pembayaran $pembayaran, PembayaranService $service)
    {
        abort_unless($pembayaran->donasi && $this->bolehKelola($pembayaran->donasi), 403);

        try {
            $hasil = $service->sinkron($pembayaran);
        } catch (AinoException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', "Status transaksi: {$hasil->statusLabel()}.");
    }

    private function bolehKelola(Donasi $d): bool
    {
        $user = $this->user();

        return $d->rt_id ? $user->canManageRt($d->rt_id) : $user->isAdmin();
    }

    private function pastikanBolehLihat(Donasi $d): void
    {
        $user = $this->user();
        abort_unless($user->isAdmin() || ! $d->rt_id || $user->canManageRt($d->rt_id), 403);
    }

    private function validasi(Request $request, ?Donasi $d = null): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'ringkasan' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:20000'],
            'target' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'cara_donasi' => ['nullable', 'string', 'max:2000'],
            'rt_id' => ['nullable', 'exists:rts,id'],
            'gambar' => ['nullable', 'image', 'max:'.config('siwarga.foto_max_kb')],
            'tampilkan_total' => ['nullable', 'boolean'],
            'publik' => ['nullable', 'boolean'],
            'aktif' => ['nullable', 'boolean'],
            'terima_qris' => ['nullable', 'boolean'],
        ]);

        $user = $this->user();
        if ($user->isPengurusRt()) {
            $data['rt_id'] = $user->rt_id;
        }
        foreach (['tampilkan_total', 'publik', 'aktif', 'terima_qris'] as $b) {
            $data[$b] = $request->boolean($b);
        }
        $data['target'] = ($data['target'] ?? null) ?: null;
        unset($data['gambar'], $data['hapus_gambar']);

        return $data;
    }
}
