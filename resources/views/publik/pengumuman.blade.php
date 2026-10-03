@extends('layouts.publik')
@section('title', $p->judul)
@section('deskripsi', \Illuminate\Support\Str::limit(strip_tags($p->isi), 150))

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
        <a href="{{ route('publik') }}#info" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="arrow-left" class="size-4" /> Kembali</a>
        <div class="card card-body sm:p-8">
            <div class="mb-2 flex flex-wrap items-center gap-1.5">
                @if ($p->penting)<span class="badge badge-red">Penting</span>@endif
                <span class="badge badge-slate">{{ $p->rt ? 'RT '.$p->rt->nomor : pengaturan('nama_rw', 'RW') }}</span>
                <span class="text-xs text-slate-500">{{ $p->terbit_pada->translatedFormat('l, d F Y · H:i') }}</span>
            </div>
            <h1 class="mb-5 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $p->judul }}</h1>
            @if ($p->lampiranGambar())
                <a href="{{ $p->lampiranUrl() }}" target="_blank"><img src="{{ $p->lampiranUrl() }}" alt="" class="mb-5 w-full rounded-xl"></a>
            @endif
            <div class="prose-isi">{{ $p->isi }}</div>
            @if ($p->lampiran && ! $p->lampiranGambar())
                <a href="{{ $p->lampiranUrl() }}" target="_blank" class="mt-6 flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                    <x-icon name="paperclip" class="size-5 text-slate-400" />
                    <span class="flex-1 truncate text-sm font-medium text-slate-800">{{ $p->lampiran_nama ?? 'Lampiran' }}</span>
                    <x-icon name="download" class="size-5 text-slate-400" />
                </a>
            @endif
            <x-bagikan class="mt-6 border-t border-slate-100 pt-4" :judul="$p->judul" :url="route('publik.pengumuman', $p)" :teks="'📢 '.$p->judul.' — '.pengaturan('nama_rw')" />
        </div>
    </article>
@endsection
