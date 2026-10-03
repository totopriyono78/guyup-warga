@extends('layouts.app')
@section('title', 'Galang Dana')

@section('content')
    <x-page-header judul="Galang dana" sub="Program penggalangan dana warga. Donasi bisa langsung dibayar dengan QRIS.">
        @if (auth()->user()->isPengurus())
            <a href="{{ route('donasi.index') }}" class="btn btn-secondary"><x-icon name="cog" class="size-4" /> Kelola</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Sedang berjalan</h2>
                @forelse ($berjalan as $d)
                    @if ($loop->first)<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">@endif
                    <a href="{{ route('galang.show', $d) }}" class="card group flex flex-col overflow-hidden transition hover:border-brand-300 hover:shadow">
                        @if ($d->gambarUrl())
                            <img src="{{ $d->gambarUrl() }}" alt="" class="aspect-[16/9] w-full object-cover" loading="lazy">
                        @endif
                        <div class="flex flex-1 flex-col p-4">
                            <div class="mb-1 flex flex-wrap items-center gap-1.5">
                                <span class="badge badge-slate">{{ $d->lingkup }}</span>
                                @unless ($d->publik)<span class="badge badge-amber">Khusus warga</span>@endunless
                                @if (! is_null($d->sisaHari()))<span class="text-xs text-slate-500">{{ $d->sisaHari() > 0 ? 'Sisa '.$d->sisaHari().' hari' : 'Hari terakhir' }}</span>@endif
                            </div>
                            <p class="font-semibold text-slate-900 group-hover:text-brand-800">{{ $d->judul }}</p>
                            @if ($d->ringkasan)<p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $d->ringkasan }}</p>@endif
                            <div class="mt-auto pt-4">
                                @include('publik.partials.progres-donasi', ['d' => $d, 'ringkas' => true])
                                <span class="btn btn-primary mt-3 w-full"><x-icon name="heart" class="size-4" /> {{ $d->bisaQris() ? 'Donasi sekarang' : 'Lihat detail' }}</span>
                            </div>
                        </div>
                    </a>
                    @if ($loop->last)</div>@endif
                @empty
                    <div class="card"><x-empty icon="heart" judul="Belum ada penggalangan dana yang berjalan" /></div>
                @endforelse
            </section>

            @if ($selesai->isNotEmpty())
                <section x-data="{ buka: false }">
                    <button type="button" @click="buka = !buka" class="mb-3 flex items-center gap-1 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Sudah selesai ({{ $selesai->count() }}) <span x-text="buka ? '▲' : '▼'" class="text-[10px]"></span>
                    </button>
                    <div x-cloak x-show="buka" class="card divide-y divide-slate-100">
                        @foreach ($selesai as $d)
                            <a href="{{ route('galang.show', $d) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                                <span class="min-w-0 truncate font-medium text-slate-800">{{ $d->judul }}</span>
                                <span class="shrink-0 text-xs text-slate-500">{{ $d->jumlah_donatur }} donatur</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Riwayat donasi milik sendiri --}}
        <aside class="space-y-6">
            <div class="card">
                <div class="border-b border-slate-100 px-4 py-3"><h2 class="card-title">Donasi saya</h2></div>
                @forelse ($pembayaran as $p)
                    <a href="{{ route('publik.donasi.bayar', $p) }}" class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50">
                        <span class="rounded bg-slate-900 px-1.5 py-0.5 text-[9px] font-black tracking-wider text-white">QRIS</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $p->donasi?->judul ?? 'Penggalangan dana' }}</p>
                            <p class="text-xs text-slate-500">{{ $p->created_at->translatedFormat('d M Y H:i') }}{{ $p->donatur_anonim ? ' · anonim' : '' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-slate-900">{{ rupiah($p->jumlah_iuran) }}</p>
                            @include('galang._status', ['p' => $p])
                        </div>
                    </a>
                @empty
                    @if ($tercatat->isEmpty())
                        <x-empty icon="heart" judul="Belum ada donasi">Donasi yang Anda bayar lewat QRIS akan muncul di sini.</x-empty>
                    @endif
                @endforelse
                @foreach ($tercatat as $t)
                    <div class="flex items-center gap-3 border-t border-slate-100 px-4 py-3">
                        <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold text-slate-700">TUNAI</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $t->donasi?->judul }}</p>
                            <p class="text-xs text-slate-500">{{ $t->tanggal->translatedFormat('d M Y') }} · dicatat pengurus</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-slate-900">{{ rupiah($t->nominal) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="px-1 text-xs text-slate-500">Nominal donasi Anda hanya terlihat oleh Anda dan pengurus. Di halaman umum hanya nama (atau "Donatur anonim") yang ditampilkan.</p>
        </aside>
    </div>
@endsection
