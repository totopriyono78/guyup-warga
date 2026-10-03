{{-- Kartu ringkas penggalangan dana (halaman umum). $d memuat donaturs_count & donaturs_sum_nominal. --}}
<a href="{{ route('publik.donasi', $d) }}{{ $d->bisaQris() ? '#donasi' : '' }}" class="card group flex flex-col overflow-hidden transition hover:border-brand-300 hover:shadow">
    @if ($d->gambarUrl())
        <img src="{{ $d->gambarUrl() }}" alt="" class="aspect-[16/9] w-full object-cover" loading="lazy">
    @else
        <div class="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-rose-100 via-amber-50 to-brand-100 text-rose-500">
            <x-icon name="heart" class="size-12" />
        </div>
    @endif
    <div class="flex flex-1 flex-col p-4">
        <div class="mb-1 flex flex-wrap items-center gap-1.5">
            @if ($d->berjalan())
                <span class="badge badge-green">Berjalan</span>
            @else
                <span class="badge badge-slate">Selesai</span>
            @endif
            <span class="badge badge-slate">{{ $d->lingkup }}</span>
            @if (! is_null($d->sisaHari()))
                <span class="text-xs text-slate-500">{{ $d->sisaHari() > 0 ? 'Sisa '.$d->sisaHari().' hari' : 'Hari terakhir' }}</span>
            @endif
        </div>
        <p class="font-semibold text-slate-900 group-hover:text-brand-800">{{ $d->judul }}</p>
        @if ($d->ringkasan)
            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $d->ringkasan }}</p>
        @endif
        <div class="mt-auto pt-4">
            @include('publik.partials.progres-donasi', ['d' => $d, 'ringkas' => true])
            @if ($d->bisaQris())
                <span class="btn btn-primary mt-3 w-full"><x-icon name="heart" class="size-4" /> Donasi sekarang</span>
            @endif
        </div>
    </div>
</a>
