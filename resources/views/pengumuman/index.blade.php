@extends('layouts.app')
@section('title', 'Pengumuman')

@section('content')
    @php $u = auth()->user(); @endphp
    <x-page-header judul="Pengumuman" sub="Informasi dari pengurus RW dan RT.">
        @if ($u->isPengurus())
            <a href="{{ route('pengumuman.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Buat pengumuman</a>
        @endif
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('pengumuman.index') }}" class="badge px-3 py-1.5 {{ ! request('lingkup') ? 'bg-brand-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300' }}">Semua</a>
        <a href="{{ route('pengumuman.index', ['lingkup' => 'rw']) }}" class="badge px-3 py-1.5 {{ request('lingkup') === 'rw' ? 'bg-brand-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300' }}">Dari RW</a>
        @foreach ($rts as $rt)
            @if ($u->isAdmin() || $u->rtId() === $rt->id)
                <a href="{{ route('pengumuman.index', ['lingkup' => $rt->id]) }}" class="badge px-3 py-1.5 {{ (string) request('lingkup') === (string) $rt->id ? 'bg-brand-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300' }}">RT {{ $rt->nomor }}</a>
            @endif
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($pengumuman as $p)
            <a href="{{ route('pengumuman.show', $p) }}" class="card block p-5 transition hover:border-brand-300 {{ $p->penting ? 'border-l-4 border-l-rose-500' : '' }}">
                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                    @if ($p->penting) <span class="badge badge-red">Penting</span> @endif
                    <span class="badge {{ $p->rt_id ? 'badge-blue' : 'badge-slate' }}">{{ $p->lingkup }}</span>
                    @if (! $p->terbit_pada) <span class="badge badge-amber">Draf</span>
                    @elseif ($p->terbit_pada->isFuture()) <span class="badge badge-amber">Terjadwal {{ $p->terbit_pada->translatedFormat('j M H:i') }}</span> @endif
                    @if ($p->lampiran) <x-icon name="paperclip" class="size-4 text-slate-400" /> @endif
                </div>
                <h2 class="text-lg font-semibold text-slate-900">{{ $p->judul }}</h2>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $p->ringkasan(220) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $p->penulis?->name ?? 'Pengurus' }} · {{ $p->terbit_pada?->translatedFormat('j F Y, H:i') ?? 'belum terbit' }}</p>
            </a>
        @empty
            <div class="card"><x-empty icon="megaphone" judul="Belum ada pengumuman" /></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $pengumuman->links() }}</div>
@endsection
