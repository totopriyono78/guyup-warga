@extends('layouts.publik')
@section('title', $g->judul)
@section('deskripsi', $g->deskripsi ? \Illuminate\Support\Str::limit($g->deskripsi, 150) : 'Foto kegiatan '.$g->judul)

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
        <a href="{{ route('publik.galeri') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="arrow-left" class="size-4" /> Semua album</a>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $g->judul }}</h1>
        <p class="mb-4 text-sm text-slate-500">
            {{ collect([$g->tanggal ? \App\Support\Pasaran::hariPasaran($g->tanggal).', '.$g->tanggal->translatedFormat('j F Y') : null, $g->lokasi, $g->lingkup])->filter()->join(' · ') }}
        </p>
        @if ($g->deskripsi)
            <div class="prose-isi mb-4 max-w-3xl text-sm">{{ $g->deskripsi }}</div>
        @endif
        <x-bagikan class="mb-5" :judul="$g->judul" :url="route('publik.galeri.show', $g)" :teks="'📷 Foto kegiatan: '.$g->judul" />

        @if ($g->fotos->isEmpty())
            <div class="card"><x-empty icon="photo" judul="Belum ada foto" /></div>
        @else
            @include('galeri.partials.foto-grid', ['fotos' => $g->fotos, 'kelola' => false])
        @endif
    </div>
@endsection
