{{-- Kartu album galeri. $g (withCount fotos), $url --}}
<a href="{{ $url }}" class="card group block overflow-hidden transition hover:border-brand-300 hover:shadow">
    <div class="relative">
        @if ($g->sampulUrl())
            <img src="{{ $g->sampulUrl() }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-[1.02]">
        @else
            <div class="flex aspect-[4/3] w-full items-center justify-center bg-slate-100 text-slate-300"><x-icon name="photo" class="size-12" /></div>
        @endif
        <span class="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-xs font-medium text-white">
            <x-icon name="photo" class="size-3.5" /> {{ $g->fotos_count }}
        </span>
    </div>
    <div class="p-3">
        <p class="line-clamp-2 font-semibold text-slate-900 group-hover:text-brand-800">{{ $g->judul }}</p>
        <p class="mt-0.5 text-xs text-slate-500">
            {{ $g->tanggal?->translatedFormat('j M Y') ?? $g->created_at->translatedFormat('j M Y') }} · {{ $g->lingkup }}
            @if (($pengurus ?? false) && ! $g->publik) · <span class="text-amber-700">Khusus warga</span> @endif
        </p>
    </div>
</a>
