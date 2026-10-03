@extends('layouts.app')
@section('title', $d->judul)

@section('content')
    @php $kkSaya = auth()->user()->kartu_keluarga_id; @endphp
    <x-page-header :judul="$d->judul" :kembali="route('galang.index')"
                   :sub="$d->lingkup.($d->mulai || $d->selesai ? ' · '.($d->mulai?->translatedFormat('d M Y') ?? '…').' – '.($d->selesai?->translatedFormat('d M Y') ?? 'selesai') : '')">
        @if ($d->publik)
            <a href="{{ route('publik.donasi', $d) }}" target="_blank" class="btn btn-secondary"><x-icon name="globe" class="size-4" /> Halaman umum</a>
        @endif
        @if ($bolehKelola)
            <a href="{{ route('donasi.show', $d) }}" class="btn btn-secondary"><x-icon name="cog" class="size-4" /> Kelola</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px]">
        <div class="min-w-0 space-y-5">
            <div class="card overflow-hidden">
                @if ($d->gambarUrl())
                    <img src="{{ $d->gambarUrl() }}" alt="" class="aspect-[16/9] w-full object-cover">
                @endif
                <div class="card-body">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5">
                        @if ($d->berjalan())<span class="badge badge-green">Berjalan</span>@else<span class="badge badge-slate">Selesai</span>@endif
                        @unless ($d->publik)<span class="badge badge-amber">Khusus warga</span>@endunless
                    </div>
                    @if ($d->ringkasan)<p class="mb-4 text-slate-600">{{ $d->ringkasan }}</p>@endif
                    <div class="rounded-xl bg-slate-50 p-4">@include('publik.partials.progres-donasi', ['d' => $d])</div>
                    @if ($d->bisaQris())
                        <a href="#donasi" class="btn btn-primary mt-4 w-full py-2.5 lg:hidden"><x-icon name="heart" class="size-5" /> Donasi sekarang via QRIS</a>
                    @endif
                    @if ($d->deskripsi)<div class="prose-isi mt-5 text-sm">{{ $d->deskripsi }}</div>@endif
                </div>
            </div>

            @if ($d->cara_donasi && $d->berjalan())
                <div class="card card-body border-brand-200 bg-brand-50/50">
                    <h2 class="mb-2 font-semibold text-brand-900">{{ $d->bisaQris() ? 'Cara donasi lainnya' : 'Cara berdonasi' }}</h2>
                    <div class="prose-isi text-sm">{{ $d->cara_donasi }}</div>
                </div>
            @endif

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="card-title">Daftar donatur</h2>
                    <span class="badge badge-slate">{{ $donaturs->count() }}</span>
                </div>
                @forelse ($donaturs as $x)
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 last:border-0">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $x->anonim ? 'bg-slate-100 text-slate-400' : 'bg-rose-50 text-rose-500' }}"><x-icon name="heart" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">
                                {{ $x->nama_tampil }}
                                @if ($kkSaya && (int) $x->kartu_keluarga_id === (int) $kkSaya)<span class="badge badge-blue ml-1">Keluarga Anda</span>@endif
                            </p>
                            <p class="text-xs text-slate-500">{{ $x->tanggal->translatedFormat('d M Y') }}</p>
                        </div>
                    </div>
                @empty
                    <x-empty icon="heart" judul="Belum ada donatur">Jadilah yang pertama berdonasi.</x-empty>
                @endforelse
                <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">Nominal masing-masing donatur tidak ditampilkan.</p>
            </div>
        </div>

        <aside class="space-y-5">
            @if ($d->bisaQris())
                @include('publik.partials.form-donasi', ['d' => $d])
            @elseif ($d->berjalan() && $d->terima_qris)
                <div class="card card-body text-sm text-slate-600">Pembayaran QRIS belum diaktifkan pengurus. Silakan berdonasi lewat cara yang tercantum.</div>
            @endif

            @if ($pembayaranSaya->isNotEmpty())
                <div class="card">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="card-title">Donasi saya di program ini</h2></div>
                    @foreach ($pembayaranSaya as $p)
                        <a href="{{ route('publik.donasi.bayar', $p) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5 last:border-0 hover:bg-slate-50">
                            <span class="text-xs text-slate-500">{{ $p->created_at->translatedFormat('d M Y H:i') }}</span>
                            <span class="flex items-center gap-2"><b class="text-sm text-slate-900">{{ rupiah($p->jumlah_iuran) }}</b> @include('galang._status', ['p' => $p])</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
@endsection
