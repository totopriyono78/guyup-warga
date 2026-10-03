<div class="-mx-4 mb-5 flex gap-1 overflow-x-auto border-b border-slate-200 px-4 text-sm sm:mx-0 sm:px-0">
    @foreach ([['iuran.index', 'Rekap bulanan', 'Rekap'], ['iuran.transaksi', 'Transaksi QRIS', 'Transaksi'], ['tarif.index', 'Jenis iuran', 'Jenis iuran']] as [$r, $l, $pendek])
        <a href="{{ route($r) }}" class="-mb-px flex-1 whitespace-nowrap border-b-2 px-3 py-2.5 text-center font-medium sm:flex-none {{ request()->routeIs($r) ? 'border-brand-700 text-brand-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <span class="sm:hidden">{{ $pendek }}</span><span class="hidden sm:inline">{{ $l }}</span>
        </a>
    @endforeach
</div>
