@extends('layouts.app')
@section('title', $pengguna->exists ? 'Ubah Akun' : 'Tambah Akun')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-page-header :judul="$pengguna->exists ? 'Ubah akun' : 'Tambah akun'" :kembali="route('pengguna.index')" />

        <form method="post" action="{{ $pengguna->exists ? route('pengguna.update', $pengguna) : route('pengguna.store') }}"
              class="card card-body space-y-4" x-data="{ role: @js(old('role', $pengguna->role)) }">
            @csrf
            @if ($pengguna->exists) @method('put') @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Nama</label>
                    <input name="name" value="{{ old('name', $pengguna->name) }}" required class="input">
                </div>
                <div>
                    <label class="label">Email (untuk login)</label>
                    <input type="email" name="email" value="{{ old('email', $pengguna->email) }}" required class="input @error('email') input-error @enderror">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">No. HP (bisa juga untuk login)</label>
                    <input name="no_hp" value="{{ old('no_hp', $pengguna->no_hp) }}" inputmode="tel" class="input">
                </div>
                <div>
                    <label class="label">Peran</label>
                    <select name="role" x-model="role" class="input">
                        @foreach ($roles as $k => $l)
                            <option value="{{ $k }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="role === 'rt'" x-cloak>
                    <label class="label">RT yang dikelola</label>
                    <select name="rt_id" class="input @error('rt_id') input-error @enderror">
                        <option value="">— Pilih RT —</option>
                        @foreach ($rts as $rt)
                            <option value="{{ $rt->id }}" @selected((int) old('rt_id', $pengguna->rt_id) === $rt->id)>RT {{ $rt->nomor }}</option>
                        @endforeach
                    </select>
                    @error('rt_id') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2" x-show="role !== 'admin'">
                    <label class="label">Kartu Keluarga <span x-show="role !== 'warga'" class="font-normal text-slate-500">(opsional — agar pengurus juga bisa bayar iuran keluarganya)</span></label>
                    <select name="kartu_keluarga_id" class="input @error('kartu_keluarga_id') input-error @enderror">
                        <option value="">— Pilih keluarga —</option>
                        @foreach ($kkOptions as $id => $label)
                            <option value="{{ $id }}" @selected((int) old('kartu_keluarga_id', $pengguna->kartu_keluarga_id) === (int) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('kartu_keluarga_id') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Password {{ $pengguna->exists ? '(kosongkan jika tidak diubah)' : '' }}</label>
                    <input type="password" name="password" autocomplete="new-password" {{ $pengguna->exists ? '' : 'required' }} class="input @error('password') input-error @enderror">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Ulangi password</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="input">
                </div>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $pengguna->aktif ?? true)) class="rounded border-slate-300 text-brand-700">
                    Akun aktif (bisa login)
                </label>
            </div>

            <div class="flex gap-2 border-t border-slate-100 pt-4">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('pengguna.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
