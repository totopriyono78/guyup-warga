@extends('layouts.app')
@section('title', $g->judul)

@section('content')
    <x-page-header :judul="$g->judul" :kembali="route('galeri.index')"
                   :sub="collect([$g->tanggal ? \App\Support\Pasaran::hariPasaran($g->tanggal).', '.$g->tanggal->translatedFormat('j F Y') : null, $g->lokasi, $g->lingkup])->filter()->join(' · ')">
        @if ($g->publik)
            <a href="{{ route('publik.galeri.show', $g) }}" target="_blank" class="btn btn-secondary"><x-icon name="globe" class="size-4" /> Halaman umum</a>
        @endif
        @if ($bolehKelola)
            <a href="{{ route('galeri.edit', $g) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Ubah album</a>
        @endif
    </x-page-header>

    @if ($g->deskripsi)
        <div class="card card-body prose-isi mb-4 text-sm">{{ $g->deskripsi }}</div>
    @endif

    @if ($bolehKelola)
        <form method="post" action="{{ route('galeri.foto.store', $g) }}" enctype="multipart/form-data" class="card card-body mb-4 flex flex-col gap-3 sm:flex-row sm:items-center"
              x-data="{ n: 0 }" x-on:berkas-dipilih="n = $event.detail">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="label">Tambah foto (maks. {{ \App\Http\Controllers\GaleriController::MAKS_FOTO_SEKALI }} sekaligus)</label>
                <x-pilih-file name="fotos[]" accept="image/*" multiple required label="Pilih foto" hint="Bisa pilih banyak sekaligus" />
                @error('fotos') <p class="error">{{ $message }}</p> @enderror
                @error('fotos.*') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button class="btn btn-primary shrink-0" :disabled="!n"><x-icon name="plus" class="size-4" /> <span x-text="n ? 'Unggah ' + n + ' foto' : 'Unggah'">Unggah</span></button>
        </form>
    @endif

    @if ($g->fotos->isEmpty())
        <div class="card"><x-empty icon="photo" judul="Belum ada foto di album ini" /></div>
    @else
        @include('galeri.partials.foto-grid', ['fotos' => $g->fotos, 'kelola' => $bolehKelola, 'sampulId' => $g->sampul_id])
    @endif

    <p class="mt-4 text-xs text-slate-500">
        {{ $g->fotos->count() }} foto
        @if ($g->pembuat) · diunggah oleh {{ $g->pembuat->name }} @endif
        · {{ $g->publik ? 'Tampil di halaman umum' : 'Khusus warga terdaftar' }}
    </p>
@endsection
