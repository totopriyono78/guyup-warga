@extends('layouts.app')
@section('title', $g->exists ? 'Ubah Album' : 'Album Baru')

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="mx-auto max-w-2xl">
        <x-page-header :judul="$g->exists ? 'Ubah album' : 'Album kegiatan baru'" :kembali="$g->exists ? route('galeri.show', $g) : route('galeri.index')" />

        <form method="post" enctype="multipart/form-data" class="card card-body space-y-4"
              action="{{ $g->exists ? route('galeri.update', $g) : route('galeri.store') }}">
            @csrf
            @if ($g->exists) @method('put') @endif

            <div>
                <label class="label">Nama kegiatan</label>
                <input name="judul" value="{{ old('judul', $g->judul) }}" required maxlength="255" placeholder="mis. Kerja bakti bersih saluran" class="input text-base font-medium @error('judul') input-error @enderror">
                @error('judul') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Tanggal kegiatan</label>
                    <input type="date" name="tanggal" value="{{ old('tanggal', $g->tanggal?->toDateString()) }}" class="input">
                </div>
                <div>
                    <label class="label">Lokasi</label>
                    <input name="lokasi" value="{{ old('lokasi', $g->lokasi) }}" maxlength="255" placeholder="mis. Balai Dusun" class="input">
                </div>
            </div>
            <div>
                <label class="label">Lingkup</label>
                @if ($u->isAdmin())
                    <select name="rt_id" class="input">
                        <option value="">Kegiatan RW</option>
                        @foreach ($rts as $rt)
                            <option value="{{ $rt->id }}" @selected((int) old('rt_id', $g->rt_id) === $rt->id)>RT {{ $rt->nomor }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="input" value="RT {{ $u->rt?->nomor }}" disabled>
                @endif
            </div>
            <div>
                <label class="label">Cerita singkat (opsional)</label>
                <textarea name="deskripsi" rows="4" class="input">{{ old('deskripsi', $g->deskripsi) }}</textarea>
            </div>
            @unless ($g->exists)
                <div>
                    <label class="label">Foto (bisa pilih banyak, maks. {{ \App\Http\Controllers\GaleriController::MAKS_FOTO_SEKALI }})</label>
                    <x-pilih-file name="fotos[]" accept="image/*" multiple label="Pilih foto kegiatan" hint="Bisa pilih banyak sekaligus" />
                    <p class="hint">Foto otomatis diperkecil. Bisa ditambah lagi nanti dari halaman album.</p>
                    @error('fotos') <p class="error">{{ $message }}</p> @enderror
                    @error('fotos.*') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless
            <label class="flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-sm">
                <input type="checkbox" name="publik" value="1" @checked(old('publik', $g->publik)) class="mt-0.5 rounded border-slate-300 text-brand-700">
                <span>Tampilkan di halaman umum <span class="text-slate-500">(bisa dilihat tanpa login)</span></span>
            </label>
            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <button class="btn btn-primary">{{ $g->exists ? 'Simpan perubahan' : 'Buat album' }}</button>
                <a href="{{ $g->exists ? route('galeri.show', $g) : route('galeri.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>

        @if ($g->exists)
            <form method="post" action="{{ route('galeri.destroy', $g) }}" class="mt-3 text-right" onsubmit="return confirm('Hapus album beserta semua fotonya?')">
                @csrf @method('delete')
                <button class="btn btn-ghost text-rose-600"><x-icon name="trash" class="size-4" /> Hapus album</button>
            </form>
        @endif
    </div>
@endsection
