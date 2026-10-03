@extends('layouts.app')
@section('title', 'Pencarian')

@section('content')
    <x-page-header judul="Pencarian" sub="Cari warga berdasarkan nama{{ auth()->user()->isPengurus() ? ', NIK, No. KK, atau No. HP' : '' }}, atau rumah (contoh: A-12)." />

    <form method="get" class="mb-6">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400"><x-icon name="search" /></span>
            <input type="search" name="q" value="{{ $q }}" autofocus placeholder="Ketik minimal 2 huruf…" class="input rounded-xl py-3 pl-12 text-base">
        </div>
    </form>

    @if (mb_strlen($q) >= 2)
        @php $total = $hasil['warga']->count() + $hasil['rumah']->count() + $hasil['pengumuman']->count(); @endphp
        @if ($total === 0)
            <div class="card"><x-empty icon="search" judul="Tidak ditemukan">Tidak ada hasil untuk “{{ $q }}”. Periksa ejaan atau coba kata lain.</x-empty></div>
        @endif

        @if ($hasil['warga']->isNotEmpty())
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Warga ({{ $hasil['warga']->count() }})</h2>
            <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($hasil['warga'] as $a)
                    @php $kk = $a->kartuKeluarga; $r = $kk->rumah; @endphp
                    <div class="card flex items-center gap-4 p-4">
                        <x-avatar :src="$a->fotoUrl() ?? $kk->fotoUrl()" :nama="$a->nama" size="size-16" rounded="rounded-xl" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900">{{ $a->nama }}</p>
                            <p class="text-xs text-slate-500">{{ $a->hubungan }}{{ $a->hubungan !== 'Kepala Keluarga' ? ' dari '.$kk->nama_kepala : '' }}</p>
                            <p class="text-sm text-slate-700">{{ $r ? 'Blok '.$r->blok->nama.' No. '.$r->nomor.' · RT '.$r->blok->rt->nomor : 'Alamat belum diisi' }}</p>
                            <div class="mt-1.5 flex gap-2">
                                @if ($a->boleh_detail)
                                    <a href="{{ route('keluarga.show', $kk) }}" class="text-xs font-medium text-brand-700 hover:underline">Detail keluarga</a>
                                @endif
                                @if ($r)
                                    <a href="{{ route('denah', ['rt' => $r->blok->rt_id]) }}#rumah-{{ $r->id }}" class="text-xs font-medium text-brand-700 hover:underline">Lihat di denah</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($hasil['rumah']->isNotEmpty())
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Rumah ({{ $hasil['rumah']->count() }})</h2>
            <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($hasil['rumah'] as $r)
                    <a href="{{ route('denah', ['rt' => $r->blok->rt_id]) }}#rumah-{{ $r->id }}" class="card flex items-center gap-3 p-4 hover:border-brand-300">
                        <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-lg bg-brand-50 font-bold text-brand-800">{{ $r->blok->nama }}-{{ $r->nomor }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $r->keluargaAktif->pluck('nama_kepala')->join(', ') ?: 'Belum ada KK' }}</p>
                            <p class="text-xs text-slate-500">RT {{ $r->blok->rt->nomor }} · {{ \App\Models\Rumah::STATUS[$r->status_hunian] ?? '' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($hasil['pengumuman']->isNotEmpty())
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Pengumuman ({{ $hasil['pengumuman']->count() }})</h2>
            <div class="card divide-y divide-slate-100">
                @foreach ($hasil['pengumuman'] as $p)
                    <a href="{{ route('pengumuman.show', $p) }}" class="block px-5 py-3 hover:bg-slate-50">
                        <p class="font-medium text-slate-900">{{ $p->judul }}</p>
                        <p class="text-xs text-slate-500">{{ $p->terbit_pada?->translatedFormat('j F Y') }} · {{ $p->lingkup }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endsection
