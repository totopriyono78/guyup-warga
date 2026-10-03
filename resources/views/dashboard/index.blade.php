@extends('layouts.app')
@section('title', 'Beranda')

@section('content')
    @php $u = auth()->user(); @endphp

    <div class="mb-6">
        <p class="text-sm text-slate-500">{{ now()->translatedFormat('l, j F Y') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Halo, {{ \Illuminate\Support\Str::of($u->name)->before(' ') }} 👋</h1>
    </div>

    @isset($statistik)
        @php
            $persen = $statistik['kk_tagihan'] > 0 ? round($statistik['kk_lunas'] / $statistik['kk_tagihan'] * 100) : 0;
        @endphp
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card card-body">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Rumah</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($statistik['rumah'], 0, ',', '.') }}</p>
            </div>
            <div class="card card-body">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Kepala Keluarga</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($statistik['kk'], 0, ',', '.') }}</p>
            </div>
            <div class="card card-body">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah Jiwa</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($statistik['jiwa'], 0, ',', '.') }}</p>
            </div>
            <a href="{{ route('iuran.index') }}" class="card card-body transition hover:border-brand-300">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Iuran {{ now()->translatedFormat('F') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $persen }}%</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-brand-600" style="width: {{ $persen }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-slate-500">{{ rupiah($statistik['tagihan_lunas']) }} dari {{ rupiah($statistik['tagihan_total']) }} · {{ $statistik['kk_lunas'] }}/{{ $statistik['kk_tagihan'] }} KK</p>
            </a>
        </div>
    @endisset

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Tagihan keluarga sendiri --}}
            @isset($tagihanSaya)
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 bg-gradient-to-r from-brand-700 to-teal-600 px-5 py-4 text-white">
                        <div>
                            <p class="text-sm text-brand-100">Iuran belum dibayar</p>
                            <p class="text-2xl font-bold">{{ rupiah($tagihanSaya->where('sukarela', false)->sum('nominal')) }}</p>
                            <p class="text-xs text-brand-100">{{ $tagihanSaya->count() }} tagihan</p>
                        </div>
                        @if ($tagihanSaya->isNotEmpty())
                            <a href="{{ route('iuran.saya') }}" class="btn bg-white text-brand-800 hover:bg-brand-50"><x-icon name="qr" /> Bayar QRIS</a>
                        @else
                            <span class="badge bg-white/20 text-white">Lunas semua ✓</span>
                        @endif
                    </div>
                    @if ($tagihanSaya->isNotEmpty())
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($tagihanSaya->take(4) as $t)
                                <li class="flex items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                                    <span class="min-w-0">{{ $t->periode_label }} @if ($t->terlambat()) <span class="badge badge-red ml-1">Lewat jatuh tempo</span> @endif</span>
                                    <span class="shrink-0 font-medium">{{ $t->sukarela ? 'Sukarela' : rupiah($t->nominal) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endisset

            {{-- Galang dana berjalan --}}
            @if ($galangDana->isNotEmpty())
                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <h2 class="card-title">Galang dana</h2>
                        <a href="{{ route('galang.index') }}" class="text-sm font-medium text-brand-700 hover:underline">Semua</a>
                    </div>
                    @foreach ($galangDana as $d)
                        <a href="{{ route('galang.show', $d) }}" class="block border-b border-slate-100 px-5 py-3.5 last:border-0 hover:bg-slate-50">
                            <p class="mb-2 font-medium text-slate-900">{{ $d->judul }}</p>
                            @include('publik.partials.progres-donasi', ['d' => $d, 'ringkas' => true])
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Pengumuman --}}
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <h2 class="card-title">Pengumuman terbaru</h2>
                    <a href="{{ route('pengumuman.index') }}" class="text-sm font-medium text-brand-700 hover:underline">Lihat semua</a>
                </div>
                @forelse ($pengumuman as $p)
                    <a href="{{ route('pengumuman.show', $p) }}" class="block border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            @if ($p->penting) <span class="badge badge-red">Penting</span> @endif
                            <span class="badge {{ $p->rt_id ? 'badge-blue' : 'badge-slate' }}">{{ $p->lingkup }}</span>
                            <span class="text-xs text-slate-500">{{ $p->terbit_pada?->diffForHumans() }}</span>
                        </div>
                        <p class="font-semibold text-slate-900">{{ $p->judul }}</p>
                        <p class="mt-0.5 line-clamp-2 text-sm text-slate-600">{{ $p->ringkasan() }}</p>
                    </a>
                @empty
                    <x-empty icon="megaphone" judul="Belum ada pengumuman" />
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            @isset($keluarga)
                <div class="card card-body">
                    <div class="flex items-center gap-3">
                        <x-avatar :src="$keluarga->fotoUrl()" :nama="$keluarga->nama_kepala" size="size-14" rounded="rounded-xl" />
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Keluarga saya</p>
                            <p class="truncate font-semibold text-slate-900">{{ $keluarga->nama_kepala }}</p>
                            <p class="text-sm text-slate-500">{{ $keluarga->rumah?->alamat ?? 'Belum terhubung ke rumah' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex -space-x-2">
                        @foreach ($keluarga->anggota->take(7) as $a)
                            <x-avatar :src="$a->fotoUrl()" :nama="$a->nama" size="size-9" class="ring-2 ring-white" />
                        @endforeach
                    </div>
                    <a href="{{ route('keluarga.show', $keluarga) }}" class="btn btn-secondary mt-4 w-full">Lihat data keluarga</a>
                </div>
            @endisset

            @isset($tunggakan)
                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h2 class="card-title">Tunggakan terbanyak</h2>
                        <p class="text-xs text-slate-500">Tagihan wajib periode sebelumnya yang belum dibayar</p>
                    </div>
                    @forelse ($tunggakan as $t)
                        <a href="{{ route('keluarga.show', $t->kartu_keluarga_id) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-800">{{ $t->kartuKeluarga?->nama_kepala }}</p>
                                <p class="text-xs text-slate-500">{{ $t->kartuKeluarga?->rumah?->kode }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-rose-600">{{ rupiah($t->total) }}</p>
                                <p class="text-xs text-slate-500">{{ $t->bulan }} tagihan</p>
                            </div>
                        </a>
                    @empty
                        <x-empty icon="check" judul="Tidak ada tunggakan" />
                    @endforelse
                </div>
            @endisset

            <div class="card card-body">
                <h2 class="card-title mb-3">Akses cepat</h2>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <a href="{{ route('denah') }}" class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 p-3 hover:bg-slate-50"><x-icon name="map" class="size-6 text-brand-700" />Denah</a>
                    <a href="{{ route('cari') }}" class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 p-3 hover:bg-slate-50"><x-icon name="search" class="size-6 text-brand-700" />Cari warga</a>
                    @if ($u->isPengurus())
                        <a href="{{ route('keluarga.create') }}" class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 p-3 hover:bg-slate-50"><x-icon name="plus" class="size-6 text-brand-700" />Tambah KK</a>
                        <a href="{{ route('pengumuman.create') }}" class="flex flex-col items-center gap-1 rounded-xl border border-slate-200 p-3 hover:bg-slate-50"><x-icon name="megaphone" class="size-6 text-brand-700" />Buat info</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
