@props(['judul', 'sub' => null, 'kembali' => null])
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($kembali)
            <a href="{{ $kembali }}" class="mb-1 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
                <x-icon name="arrow-left" class="size-4" /> Kembali
            </a>
        @endif
        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $judul }}</h1>
        @if ($sub)
            <p class="mt-0.5 text-sm text-slate-500">{{ $sub }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
