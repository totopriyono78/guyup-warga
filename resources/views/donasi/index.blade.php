@extends('layouts.app')
@section('title', 'Galang Dana')

@section('content')
    <x-page-header judul="Penggalangan dana" sub="Kelola program donasi dan catat donatur. Program yang ditandai publik tampil di halaman umum.">
        <a href="{{ route('publik') }}#galang-dana" target="_blank" class="btn btn-secondary"><x-icon name="globe" class="size-4" /> Lihat halaman umum</a>
        <a href="{{ route('donasi.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Buat baru</a>
    </x-page-header>

    @if ($donasi->isEmpty())
        <div class="card"><x-empty icon="heart" judul="Belum ada penggalangan dana">Buat program pertama, mis. renovasi pos kamling atau santunan warga.</x-empty></div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($donasi as $d)
                <a href="{{ route('donasi.show', $d) }}" class="card card-body flex flex-col transition hover:border-brand-300 hover:shadow">
                    <div class="mb-1 flex flex-wrap items-center gap-1.5">
                        @if ($d->berjalan())<span class="badge badge-green">Berjalan</span>@else<span class="badge badge-slate">{{ $d->aktif ? 'Selesai' : 'Ditutup' }}</span>@endif
                        <span class="badge badge-slate">{{ $d->lingkup }}</span>
                        @if ($d->publik)<span class="badge badge-blue">Publik</span>@else<span class="badge badge-amber">Internal</span>@endif
                    </div>
                    <p class="font-semibold text-slate-900">{{ $d->judul }}</p>
                    @if ($d->ringkasan)<p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $d->ringkasan }}</p>@endif
                    <div class="mt-auto pt-4">
                        @php $persen = $d->persen(); @endphp
                        @if (! is_null($persen))
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ max(2, $persen) }}%"></div></div>
                        @endif
                        <div class="mt-2 flex justify-between text-sm">
                            <span><b class="text-slate-900">{{ rupiah($d->terkumpul) }}</b>@if ($d->target)<span class="text-slate-500"> / {{ rupiah($d->target) }}</span>@endif</span>
                            <span class="text-slate-500">{{ $d->jumlah_donatur }} donatur</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $donasi->links() }}</div>
    @endif
@endsection
