<?php

namespace App\Http\Controllers;

use App\Models\Pengumuman;
use App\Models\Rt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PengumumanController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();

        $pengumuman = Pengumuman::query()
            ->untuk($user)
            // Draf/terjadwal hanya terlihat oleh yang berhak mengelolanya
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w
                ->where(fn ($t) => $t->terbit())
                ->when($user->isPengurusRt(), fn ($x) => $x->orWhere('rt_id', $user->rt_id))))
            ->when($request->filled('lingkup'), fn ($q) => $request->input('lingkup') === 'rw'
                ? $q->whereNull('rt_id')
                : $q->where('rt_id', $request->integer('lingkup')))
            ->with(['rt', 'penulis'])
            ->orderByDesc('penting')
            ->orderByRaw('terbit_pada IS NULL DESC')
            ->orderByDesc('terbit_pada')
            ->paginate(10)
            ->withQueryString();

        return view('pengumuman.index', [
            'pengumuman' => $pengumuman,
            'rts' => Rt::query()->orderBy('nomor')->get(),
        ]);
    }

    public function show(Pengumuman $pengumuman)
    {
        $user = $this->user();
        $boleh = $this->bolehKelola($pengumuman)
            || Pengumuman::query()->untuk($user)->terbit()->whereKey($pengumuman->id)->exists();
        abort_unless($boleh, 404);

        return view('pengumuman.show', [
            'p' => $pengumuman->load(['rt', 'penulis']),
            'bolehKelola' => $this->bolehKelola($pengumuman),
        ]);
    }

    public function create()
    {
        abort_unless($this->user()->isPengurus(), 403);

        return view('pengumuman.form', [
            'p' => new Pengumuman(['terbit_pada' => now(), 'rt_id' => $this->user()->isPengurusRt() ? $this->user()->rt_id : null]),
            'rts' => $this->user()->managedRts()->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->user()->isPengurus(), 403);

        $data = $this->validasi($request);
        $data['user_id'] = $this->user()->id;

        $p = Pengumuman::query()->create($data);

        return redirect()->route('pengumuman.show', $p)->with('sukses', 'Pengumuman diterbitkan.');
    }

    public function edit(Pengumuman $pengumuman)
    {
        abort_unless($this->bolehKelola($pengumuman), 403);

        return view('pengumuman.form', ['p' => $pengumuman, 'rts' => $this->user()->managedRts()->get()]);
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        abort_unless($this->bolehKelola($pengumuman), 403);

        $data = $this->validasi($request, $pengumuman);
        $pengumuman->update($data);

        return redirect()->route('pengumuman.show', $pengumuman)->with('sukses', 'Pengumuman diperbarui.');
    }

    public function destroy(Pengumuman $pengumuman)
    {
        abort_unless($this->bolehKelola($pengumuman), 403);

        if ($pengumuman->lampiran) {
            Storage::disk('public')->delete($pengumuman->lampiran);
        }
        $pengumuman->delete();

        return redirect()->route('pengumuman.index')->with('sukses', 'Pengumuman dihapus.');
    }

    private function bolehKelola(Pengumuman $p): bool
    {
        $user = $this->user();

        // Admin RW: semua. Pengurus RT: hanya pengumuman RT-nya sendiri.
        return $user->isAdmin() || ($user->isPengurusRt() && $p->rt_id && (int) $p->rt_id === (int) $user->rt_id);
    }

    private function validasi(Request $request, ?Pengumuman $p = null): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string', 'max:20000'],
            'rt_id' => ['nullable', 'exists:rts,id'],
            'penting' => ['nullable', 'boolean'],
            'terbit_pada' => ['nullable', 'date'],
            'draf' => ['nullable', 'boolean'],
            'publik' => ['nullable', 'boolean'],
            'lampiran' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            'hapus_lampiran' => ['nullable', 'boolean'],
        ]);

        $user = $this->user();
        if ($user->isPengurusRt()) {
            $data['rt_id'] = $user->rt_id; // pengurus RT hanya bisa mengumumkan untuk RT-nya
        }

        $data['penting'] = $request->boolean('penting');
        // Hanya pengumuman tingkat RW / RT yang boleh ditampilkan di halaman umum
        $data['publik'] = $request->boolean('publik');
        $data['terbit_pada'] = $request->boolean('draf') ? null : ($data['terbit_pada'] ?? now());

        if ($request->hasFile('lampiran')) {
            if ($p?->lampiran) {
                Storage::disk('public')->delete($p->lampiran);
            }
            $file = $request->file('lampiran');
            $data['lampiran'] = $file->store('pengumuman/'.now()->format('Y/m'), 'public');
            $data['lampiran_nama'] = mb_substr($file->getClientOriginalName(), 0, 255);
        } elseif ($request->boolean('hapus_lampiran') && $p?->lampiran) {
            Storage::disk('public')->delete($p->lampiran);
            $data['lampiran'] = null;
            $data['lampiran_nama'] = null;
        } else {
            unset($data['lampiran']);
        }

        unset($data['draf'], $data['hapus_lampiran']);

        return $data;
    }
}
