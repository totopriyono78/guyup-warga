@extends('layouts.app')
@section('title', 'Akun Pengguna')

@section('content')
    <x-page-header judul="Akun Pengguna" sub="Akun untuk login pengurus dan warga.">
        <a href="{{ route('pengguna.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah akun</a>
    </x-page-header>

    <form method="get" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama, email, atau no. HP" class="input w-full sm:w-64">
        @if (auth()->user()->isAdmin())
            <select name="role" class="input w-auto flex-1 sm:flex-none" onchange="this.form.submit()">
                <option value="">Semua peran</option>
                @foreach (\App\Models\User::ROLES as $k => $l)
                    <option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn btn-secondary">Cari</button>
    </form>

    <div class="card overflow-hidden">
        {{-- HP: kartu --}}
        <div class="divide-y divide-slate-100 sm:hidden">
            @forelse ($pengguna as $p)
                <div class="flex items-start gap-3 px-4 py-3">
                    <x-avatar :src="$p->kartuKeluarga?->fotoUrl()" :nama="$p->name" size="size-10" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900">{{ $p->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $p->email }}{{ $p->no_hp ? ' · '.$p->no_hp : '' }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <span class="badge {{ ['admin' => 'badge-red', 'rt' => 'badge-blue'][$p->role] ?? 'badge-slate' }}">{{ $p->roleLabel() }}</span>
                            @unless ($p->aktif) <span class="badge badge-slate">Nonaktif</span> @endunless
                            @if ($p->kartuKeluarga)<span class="truncate text-xs text-slate-500">{{ $p->kartuKeluarga->rumah?->kode ?? '' }}</span>@endif
                        </div>
                    </div>
                    <div class="flex shrink-0">
                        <a href="{{ route('pengguna.edit', $p) }}" class="btn btn-ghost btn-sm" aria-label="Ubah"><x-icon name="pencil" class="size-4" /></a>
                        @unless ($p->is(auth()->user()))
                            <form method="post" action="{{ route('pengguna.destroy', $p) }}" onsubmit="return confirm('Hapus akun ini?')">
                                @csrf @method('delete')
                                <button class="btn btn-ghost btn-sm text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                            </form>
                        @endunless
                    </div>
                </div>
            @empty
                <x-empty icon="key" judul="Belum ada akun" />
            @endforelse
        </div>
        <div class="hidden overflow-x-auto sm:block">
            <table class="table">
                <thead><tr><th>Nama</th><th>Login</th><th>Peran</th><th>Keluarga</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pengguna as $p)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $p->name }}</td>
                            <td class="text-xs">{{ $p->email }}<br><span class="text-slate-500">{{ $p->no_hp }}</span></td>
                            <td><span class="badge {{ ['admin' => 'badge-red', 'rt' => 'badge-blue'][$p->role] ?? 'badge-slate' }}">{{ $p->roleLabel() }}</span></td>
                            <td class="text-xs">{{ $p->kartuKeluarga ? $p->kartuKeluarga->nama_kepala.' · '.($p->kartuKeluarga->rumah?->kode ?? '-') : '—' }}</td>
                            <td>@if ($p->aktif) <span class="badge badge-green">Aktif</span> @else <span class="badge badge-slate">Nonaktif</span> @endif</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('pengguna.edit', $p) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></a>
                                @unless ($p->is(auth()->user()))
                                    <form method="post" action="{{ route('pengguna.destroy', $p) }}" class="inline" onsubmit="return confirm('Hapus akun ini?')">
                                        @csrf @method('delete')
                                        <button class="btn btn-ghost btn-sm text-rose-600"><x-icon name="trash" class="size-4" /></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="key" judul="Belum ada akun" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $pengguna->links() }}</div>
    </div>
@endsection
