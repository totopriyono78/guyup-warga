@extends('layouts.publik')
@section('title', $d->judul)
@section('deskripsi', $d->ringkasan ?: \Illuminate\Support\Str::limit((string) $d->deskripsi, 150))

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-10">
        <a href="{{ route('publik') }}#galang-dana" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="arrow-left" class="size-4" /> Kembali</a>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_340px]">
            <div class="space-y-5">
                <div class="card overflow-hidden">
                    @if ($d->gambarUrl())
                        <img src="{{ $d->gambarUrl() }}" alt="" class="aspect-[16/9] w-full object-cover">
                    @endif
                    <div class="card-body sm:p-6">
                        <div class="mb-2 flex flex-wrap items-center gap-1.5">
                            @if ($d->berjalan())<span class="badge badge-green">Berjalan</span>@else<span class="badge badge-slate">Selesai</span>@endif
                            <span class="badge badge-slate">{{ $d->lingkup }}</span>
                            @if ($d->mulai || $d->selesai)
                                <span class="inline-flex items-center gap-1 text-xs text-slate-500"><x-icon name="calendar" class="size-3.5" />
                                    {{ $d->mulai?->translatedFormat('d M Y') ?? '…' }} – {{ $d->selesai?->translatedFormat('d M Y') ?? 'selesai' }}</span>
                            @endif
                        </div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $d->judul }}</h1>
                        @if ($d->ringkasan)<p class="mt-1 text-slate-600">{{ $d->ringkasan }}</p>@endif

                        {{-- Progres (HP: tampil di sini) --}}
                        <div class="mt-5 rounded-xl bg-slate-50 p-4 lg:hidden">
                            @include('publik.partials.progres-donasi', ['d' => $d])
                        </div>
                        @if ($d->bisaQris())
                            <a href="#donasi" class="btn btn-primary mt-4 w-full py-2.5 lg:hidden"><x-icon name="heart" class="size-5" /> Donasi sekarang via QRIS</a>
                        @endif
                        <x-bagikan class="mt-4" :judul="$d->judul" :url="route('publik.donasi', $d)" :teks="'🤝 Ayo bantu: '.$d->judul.' — '.pengaturan('nama_rw')" />

                        @if ($d->deskripsi)
                            <div class="prose-isi mt-5">{{ $d->deskripsi }}</div>
                        @endif
                    </div>
                </div>

                @if ($d->berjalan() && ($d->bisaQris() || $d->cara_donasi))
                    <div class="card card-body border-brand-200 bg-brand-50/50 sm:p-6">
                        <h2 class="mb-3 font-semibold text-brand-900">Cara berdonasi</h2>
                        @if ($d->bisaQris())
                            {{-- QRIS selalu tercantum otomatis bila aktif --}}
                            <div class="mb-3 flex gap-3 rounded-xl bg-white p-3 ring-1 ring-brand-100">
                                <span class="mt-0.5 h-fit rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-black tracking-wider text-white">QRIS</span>
                                <div class="text-sm text-slate-700">
                                    <p class="font-semibold text-slate-900">Bayar langsung dengan QRIS</p>
                                    <p>Isi formulir <a href="#donasi" class="font-medium text-brand-700 underline">Donasi sekarang</a>, lalu scan kode QR dengan m-banking atau e-wallet apa pun (GoPay, OVO, DANA, ShopeePay, dll). Donasi otomatis tercatat, tanpa perlu konfirmasi.</p>
                                </div>
                            </div>
                        @endif
                        @if ($d->cara_donasi)
                            @if ($d->bisaQris())<p class="mb-1 text-xs font-semibold uppercase tracking-wide text-brand-800">Atau</p>@endif
                            <div class="prose-isi text-sm">{{ $d->cara_donasi }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <aside class="space-y-5">
                <div class="card card-body hidden lg:block">
                    @include('publik.partials.progres-donasi', ['d' => $d])
                </div>

                @if ($d->bisaQris())
                    @include('publik.partials.form-donasi', ['d' => $d])
                @endif

                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 class="font-semibold text-slate-900">Daftar donatur</h2>
                        <span class="badge badge-slate">{{ $donaturs->count() }}</span>
                    </div>
                    @forelse ($donaturs as $x)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 last:border-0">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $x['nama'] === 'Donatur anonim' ? 'bg-slate-100 text-slate-400' : 'bg-rose-50 text-rose-500' }}">
                                <x-icon name="heart" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $x['nama'] }}</p>
                                <p class="text-xs text-slate-500">{{ $x['tanggal']?->translatedFormat('d M Y') }}</p>
                            </div>
                        </div>
                    @empty
                        <x-empty icon="heart" judul="Belum ada donatur">Jadilah yang pertama berdonasi.</x-empty>
                    @endforelse
                    <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">Nominal masing-masing donatur tidak ditampilkan untuk menjaga privasi.</p>
                </div>
            </aside>
        </div>
    </div>
@endsection
