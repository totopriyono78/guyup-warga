@extends('layouts.publik')
@section('title', 'Galeri Kegiatan')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
        <a href="{{ route('publik') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="arrow-left" class="size-4" /> Beranda</a>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Galeri kegiatan</h1>
        <p class="mb-5 text-sm text-slate-500">Dokumentasi kegiatan warga {{ pengaturan('nama_rw') }}.</p>

        @if ($galeri->isEmpty())
            <div class="card"><x-empty icon="photo" judul="Belum ada album kegiatan" /></div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($galeri as $g)
                    @include('galeri.partials.kartu-album', ['g' => $g, 'url' => route('publik.galeri.show', $g)])
                @endforeach
            </div>
            <div class="mt-4">{{ $galeri->links() }}</div>
        @endif
    </div>
@endsection
