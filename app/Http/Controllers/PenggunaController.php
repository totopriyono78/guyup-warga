<?php

namespace App\Http\Controllers;

use App\Models\KartuKeluarga;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Kelola akun. Pengurus RW: semua akun. Pengurus RT: hanya akun warga di RT-nya.
 */
class PenggunaController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();
        $q = trim((string) $request->input('q'));

        $pengguna = User::query()
            ->with(['rt', 'kartuKeluarga.rumah.blok'])
            ->when($user->isPengurusRt(), fn ($x) => $x->where('role', User::ROLE_WARGA)
                ->whereHas('kartuKeluarga', fn ($k) => $k->diRt($user->rt_id)))
            ->when($request->filled('role'), fn ($x) => $x->where('role', $request->input('role')))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->whereLike('name', "%{$q}%")->orWhereLike('email', "%{$q}%")->orWhereLike('no_hp', "%{$q}%")))
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'rt' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('pengguna.index', compact('pengguna'));
    }

    public function create(Request $request)
    {
        $kk = $request->integer('kk')
            ? KartuKeluarga::query()->dikelolaOleh($this->user())->find($request->integer('kk'))
            : null;

        return view('pengguna.form', [
            'pengguna' => new User([
                'role' => User::ROLE_WARGA,
                'aktif' => true,
                'kartu_keluarga_id' => $kk?->id,
                'name' => $kk?->nama_kepala,
                'no_hp' => $kk?->no_hp,
            ]),
            ...$this->opsi(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $pengguna = User::query()->create($data);

        return redirect()->route('pengguna.index')->with('sukses', "Akun {$pengguna->email} dibuat.");
    }

    public function edit(User $pengguna)
    {
        $this->pastikanBoleh($pengguna);

        return view('pengguna.form', ['pengguna' => $pengguna, ...$this->opsi()]);
    }

    public function update(Request $request, User $pengguna)
    {
        $this->pastikanBoleh($pengguna);

        $data = $this->validasi($request, $pengguna);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($pengguna->is($this->user())) {
            unset($data['role'], $data['aktif']); // jangan sampai mengunci diri sendiri
        }

        $pengguna->update($data);

        return redirect()->route('pengguna.index')->with('sukses', 'Akun diperbarui.');
    }

    public function destroy(User $pengguna)
    {
        $this->pastikanBoleh($pengguna);
        abort_if($pengguna->is($this->user()), 422, 'Tidak bisa menghapus akun sendiri.');

        $pengguna->delete();

        return back()->with('sukses', 'Akun dihapus.');
    }

    private function pastikanBoleh(User $target): void
    {
        $user = $this->user();
        if ($user->isAdmin()) {
            return;
        }

        $target->loadMissing('kartuKeluarga.rumah.blok');
        abort_unless(
            $target->isWarga() && $target->kartuKeluarga && (int) $target->kartuKeluarga->rtId() === (int) $user->rt_id,
            403
        );
    }

    private function opsi(): array
    {
        $user = $this->user();

        return [
            'rts' => $user->managedRts()->get(),
            'roles' => $user->isAdmin() ? User::ROLES : [User::ROLE_WARGA => 'Warga'],
            'kkOptions' => KartuKeluarga::query()->aktif()->dikelolaOleh($user)
                ->with('rumah.blok')->orderBy('nama_kepala')->get()
                ->mapWithKeys(fn ($kk) => [$kk->id => $kk->nama_kepala.($kk->rumah ? ' — '.$kk->rumah->kode : '')])
                ->all(),
        ];
    }

    private function validasi(Request $request, ?User $pengguna = null): array
    {
        $user = $this->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($pengguna?->id)],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys($user->isAdmin() ? User::ROLES : [User::ROLE_WARGA => 1]))],
            'rt_id' => ['nullable', 'required_if:role,rt', 'exists:rts,id'],
            'kartu_keluarga_id' => ['nullable', 'required_if:role,warga', 'exists:kartu_keluargas,id'],
            'password' => [$pengguna ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'aktif' => ['nullable', 'boolean'],
        ], [
            'rt_id.required_if' => 'Pilih RT yang dikelola.',
            'kartu_keluarga_id.required_if' => 'Pilih Kartu Keluarga untuk akun warga.',
        ]);

        $data += ['rt_id' => null, 'kartu_keluarga_id' => null, 'no_hp' => null];
        $data['aktif'] = $request->boolean('aktif');

        if ($data['role'] === User::ROLE_WARGA && $data['kartu_keluarga_id']) {
            $kk = KartuKeluarga::query()->dikelolaOleh($user)->find($data['kartu_keluarga_id']);
            abort_unless($kk, 403, 'KK tersebut di luar wilayah Anda.');
            $data['rt_id'] = null;
        }
        if ($data['role'] === User::ROLE_ADMIN) {
            $data['rt_id'] = null;
        }

        return $data;
    }
}
