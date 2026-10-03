@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2 text-sm" aria-label="Navigasi halaman">
        <p class="text-slate-500">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50">‹ Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm" rel="prev">‹ Sebelumnya</a>
            @endif
            <span class="px-2 text-slate-500">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm" rel="next">Berikutnya ›</a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50">Berikutnya ›</span>
            @endif
        </div>
    </nav>
@endif
