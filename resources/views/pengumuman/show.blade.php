@extends('layouts.app')
@section('title', $p->judul)

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-page-header :judul="$p->judul" :kembali="route('pengumuman.index')">
            @if ($bolehKelola)
                <a href="{{ route('pengumuman.edit', $p) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Ubah</a>
                <form method="post" action="{{ route('pengumuman.destroy', $p) }}" onsubmit="return confirm('Hapus pengumuman ini?')">
                    @csrf @method('delete')
                    <button class="btn btn-ghost text-rose-600"><x-icon name="trash" class="size-4" /></button>
                </form>
            @endif
        </x-page-header>

        <article class="card card-body">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                @if ($p->penting) <span class="badge badge-red">Penting</span> @endif
                <span class="badge {{ $p->rt_id ? 'badge-blue' : 'badge-slate' }}">{{ $p->lingkup }}</span>
                <span>{{ $p->penulis?->name ?? 'Pengurus' }}</span> ·
                <span>{{ $p->terbit_pada?->translatedFormat('l, j F Y · H:i') ?? 'Draf (belum terbit)' }}</span>
            </div>

            @if ($p->lampiranGambar())
                <a href="{{ $p->lampiranUrl() }}" target="_blank"><img src="{{ $p->lampiranUrl() }}" alt="" class="mb-5 w-full rounded-xl"></a>
            @endif

            <div class="prose-isi text-[15px]">{{ $p->isi }}</div>

            @if ($p->lampiran && ! $p->lampiranGambar())
                <a href="{{ $p->lampiranUrl() }}" target="_blank" class="mt-6 flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                    <x-icon name="paperclip" class="size-5 text-slate-500" />
                    <span class="flex-1 truncate text-sm font-medium text-slate-800">{{ $p->lampiran_nama ?? 'Lampiran' }}</span>
                    <x-icon name="download" class="size-5 text-brand-700" />
                </a>
            @endif

            <div class="mt-6 border-t border-slate-100 pt-4">
                <a href="https://wa.me/?text={{ urlencode($p->judul."\n\n".$p->isi."\n\n".route('pengumuman.show', $p)) }}" target="_blank" rel="noopener"
                   class="btn btn-secondary btn-sm">Bagikan ke WhatsApp</a>
            </div>
        </article>
    </div>
@endsection
