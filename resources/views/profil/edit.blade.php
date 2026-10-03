@extends('layouts.app')
@section('title', 'Profil')

@section('content')
    <div class="mx-auto max-w-xl">
        <x-page-header judul="Profil saya" :sub="$user->roleLabel()" />
        <form method="post" action="{{ route('profil.update') }}" class="card card-body space-y-4">
            @csrf @method('put')
            <div><label class="label">Nama</label><input name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
            <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></div>
            <div><label class="label">No. HP</label><input name="no_hp" value="{{ old('no_hp', $user->no_hp) }}" class="input"></div>
            <div class="border-t border-slate-100 pt-4">
                <h2 class="card-title mb-3">Ganti password</h2>
                <div class="space-y-3">
                    <div><label class="label">Password lama</label><input type="password" name="password_lama" autocomplete="current-password" class="input @error('password_lama') input-error @enderror">@error('password_lama') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Password baru</label><input type="password" name="password" autocomplete="new-password" class="input @error('password') input-error @enderror">@error('password') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Ulangi password baru</label><input type="password" name="password_confirmation" autocomplete="new-password" class="input"></div>
                </div>
            </div>
            <button class="btn btn-primary">Simpan</button>
        </form>
    </div>
@endsection
