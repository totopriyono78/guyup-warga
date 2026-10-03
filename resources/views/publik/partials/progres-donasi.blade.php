{{-- Progres penggalangan dana. Total hanya tampil bila tampilkan_total aktif; nominal per donatur tidak pernah ditampilkan. --}}
@php $persen = $d->tampilkan_total ? $d->persen() : null; @endphp
@if (! is_null($persen))
    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-teal-500" style="width: {{ max(2, $persen) }}%"></div>
    </div>
@endif
<div class="mt-2 flex items-end justify-between gap-2 text-sm">
    <div class="min-w-0">
        @if ($d->tampilkan_total)
            <p class="font-bold text-slate-900 {{ ($ringkas ?? false) ? '' : 'text-lg' }}">{{ rupiah($d->terkumpul) }}</p>
            <p class="text-xs text-slate-500">terkumpul{{ $d->target ? ' dari '.rupiah($d->target) : '' }}</p>
        @else
            <p class="text-xs text-slate-500">Total donasi diumumkan oleh pengurus</p>
        @endif
    </div>
    <div class="shrink-0 text-right">
        <p class="font-bold text-slate-900">{{ $d->jumlah_donatur }}</p>
        <p class="text-xs text-slate-500">donatur</p>
    </div>
</div>
