<div class="flex h-14 items-center gap-2.5 border-b border-slate-200 px-5">
    <img src="{{ asset('img/logo.svg') }}" alt="" class="size-8">
    <div class="min-w-0 leading-tight">
        <p class="truncate text-sm font-bold text-slate-900">{{ $namaRw }}</p>
        <p class="truncate text-xs text-slate-500">{{ wilayah('singkat') ?: config('siwarga.aplikasi') }}</p>
    </div>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto p-3">
    <a href="{{ route('publik') }}" class="mb-3 flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50/60 px-3 py-2 text-sm font-medium text-brand-800 hover:bg-brand-100">
        <x-icon name="globe" /> <span class="flex-1">Lihat Website</span> <x-icon name="external" class="size-4 opacity-60" />
    </a>
    @foreach ($menu as $m)
        @if ($m['tampil'])
            <a href="{{ route($m['route']) }}" class="nav-link {{ $isAktif($m['aktif']) ? 'active' : '' }}">
                <x-icon :name="$m['icon']" /> {{ $m['label'] }}
            </a>
        @endif
    @endforeach

    @if ($u->isPengurus())
        <p class="px-3 pb-1 pt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">Pengurus</p>
        @foreach ($menuPengurus as $m)
            @if ($m['tampil'])
                <a href="{{ route($m['route']) }}" class="nav-link {{ $isAktif($m['aktif']) ? 'active' : '' }}">
                    <x-icon :name="$m['icon']" /> {{ $m['label'] }}
                </a>
            @endif
        @endforeach
    @endif
</nav>
<div class="px-3 pb-2">
    <img src="{{ asset('img/rukoon.svg') }}" alt="{{ config('siwarga.aplikasi') }}" class="ml-3 h-5 opacity-60">
</div>
<div class="border-t border-slate-200 p-3">
    <a href="{{ route('profil') }}" class="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
        <x-avatar :src="$u->kartuKeluarga?->fotoUrl()" :nama="$u->name" size="size-9" />
        <div class="min-w-0 flex-1 leading-tight">
            <p class="truncate text-sm font-medium text-slate-900">{{ $u->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ $u->roleLabel() }}</p>
        </div>
    </a>
    <form method="post" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button class="nav-link w-full"><x-icon name="logout" /> Keluar</button>
    </form>
</div>
