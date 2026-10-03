@extends('layouts.app')
@section('title', 'Data Warga')

@section('content')
    <x-page-header judul="Data Warga" sub="Kartu keluarga beserta anggotanya.">
        <a href="{{ route('keluarga.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah KK</a>
    </x-page-header>

    @php $filterAktif = $filter['rtId'] || $filter['blokId'] || $filter['status'] !== 'aktif'; @endphp
    <form method="get" class="card card-body mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" x-data="{ filter: @js($filterAktif) }">
        <div class="sm:col-span-2 lg:col-span-2">
            <label class="label max-sm:sr-only">Cari</label>
            <div class="flex gap-2">
                <input type="search" name="q" value="{{ $filter['q'] }}" placeholder="Nama, NIK, atau No. KK" class="input">
                <button type="button" class="btn btn-secondary shrink-0 sm:hidden" @click="filter = !filter" :aria-expanded="filter">
                    Filter @if ($filterAktif)<span class="size-2 rounded-full bg-brand-600"></span>@endif
                </button>
            </div>
        </div>
        @if (auth()->user()->isAdmin())
            <div class="{{ $filterAktif ? '' : 'max-sm:hidden' }}" :class="{ 'max-sm:hidden': !filter }">
                <label class="label">RT</label>
                <select name="rt" class="input" onchange="this.form.blok.value=''; this.form.submit()">
                    <option value="">Semua RT</option>
                    @foreach ($rts as $rt)
                        <option value="{{ $rt->id }}" @selected($filter['rtId'] === $rt->id)>RT {{ $rt->nomor }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="{{ $filterAktif ? '' : 'max-sm:hidden' }}" :class="{ 'max-sm:hidden': !filter }">
            <label class="label">Blok</label>
            <select name="blok" class="input">
                <option value="">Semua blok</option>
                @foreach ($bloks as $b)
                    <option value="{{ $b->id }}" @selected($filter['blokId'] === $b->id)>Blok {{ $b->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="{{ $filterAktif ? '' : 'max-sm:hidden' }}" :class="{ 'max-sm:hidden': !filter }">
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="aktif" @selected($filter['status'] === 'aktif')>Masih tinggal</option>
                <option value="pindah" @selected($filter['status'] === 'pindah')>Sudah pindah</option>
                <option value="semua" @selected($filter['status'] === 'semua')>Semua</option>
            </select>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5 {{ $filterAktif ? '' : 'max-sm:hidden' }}" :class="{ 'max-sm:hidden': !filter }">
            <button class="btn btn-primary">Terapkan</button>
            <a href="{{ route('keluarga.index') }}" class="btn btn-ghost">Reset</a>
        </div>
    </form>

    @if ($keluarga->isEmpty())
        <div class="card"><x-empty icon="users" judul="Tidak ada data keluarga">Coba ubah filter, atau tambahkan KK baru.</x-empty></div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($keluarga as $kk)
                <a href="{{ route('keluarga.show', $kk) }}" class="card group flex gap-3 p-3 transition sm:gap-4 sm:p-4 hover:border-brand-300 hover:shadow">
                    <x-avatar :src="$kk->fotoUrl()" :nama="$kk->nama_kepala" size="size-16 sm:size-20" rounded="rounded-xl" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900 group-hover:text-brand-800">{{ $kk->nama_kepala }}</p>
                        <p class="text-sm text-slate-600">{{ $kk->rumah ? 'Blok '.$kk->rumah->blok->nama.' No. '.$kk->rumah->nomor : 'Belum ada rumah' }}</p>
                        <p class="text-xs text-slate-500">{{ $kk->rumah ? 'RT '.$kk->rumah->blok->rt->nomor.' · ' : '' }}{{ $kk->anggota_count }} anggota</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span class="badge {{ $kk->status_tinggal === 'tetap' ? 'badge-green' : 'badge-blue' }}">{{ \App\Models\KartuKeluarga::STATUS_TINGGAL[$kk->status_tinggal] ?? $kk->status_tinggal }}</span>
                            @unless ($kk->aktif) <span class="badge badge-slate">Sudah pindah</span> @endunless
                            @unless ($kk->no_kk) <span class="badge badge-amber">No. KK kosong</span> @endunless
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-5">{{ $keluarga->links() }}</div>
    @endif
@endsection
