@extends('layouts.app')
@section('title', 'Galeri Kegiatan')

@section('content')
    <x-page-header judul="Galeri kegiatan" sub="Dokumentasi kegiatan warga RT & RW.">
        @if ($bolehBuat)
            <a href="{{ route('galeri.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Album baru</a>
        @endif
    </x-page-header>

    <form method="get" class="mb-4 max-w-sm">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari album…" class="input">
    </form>

    @if ($galeri->isEmpty())
        <div class="card"><x-empty icon="photo" judul="{{ request('q') ? 'Album tidak ditemukan' : 'Belum ada album' }}">
            @if ($bolehBuat) Buat album pertama dan unggah foto kegiatan, mis. kerja bakti atau pertemuan warga. @endif
        </x-empty></div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($galeri as $g)
                @include('galeri.partials.kartu-album', ['g' => $g, 'url' => route('galeri.show', $g), 'pengurus' => auth()->user()->isPengurus()])
            @endforeach
        </div>
        <div class="mt-4">{{ $galeri->links() }}</div>
    @endif
@endsection
