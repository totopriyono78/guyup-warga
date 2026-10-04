@extends('layouts.app')
@section('title', $anggota->exists ? 'Ubah Anggota' : 'Tambah Anggota')

@section('content')
    <x-page-header :judul="$anggota->exists ? 'Ubah data '.$anggota->nama : 'Tambah anggota keluarga'"
                   :sub="'Keluarga '.$kk->nama_kepala.($kk->rumah ? ' · '.$kk->rumah->alamat : '')"
                   :kembali="$kembali ?? route('keluarga.show', $kk)" />

    <form method="post" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-5"
          action="{{ $anggota->exists ? route('anggota.update', $anggota) : route('anggota.store', $kk) }}">
        @csrf
        @if ($anggota->exists) @method('put') @endif
        @if (! empty($kembali)) <input type="hidden" name="kembali" value="{{ $kembali }}"> @endif

        @include('anggota._fields', ['a' => $anggota, 'p' => '', 'denganHubungan' => true])

        <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary">{{ $anggota->exists ? 'Simpan perubahan' : 'Tambah anggota' }}</button>
            <a href="{{ $kembali ?? route('keluarga.show', $kk) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>

    @if ($anggota->exists)
        @php $kepalaTerkunci = $anggota->hubungan === 'Kepala Keluarga' && $kk->anggota()->count() > 1; @endphp
        <div class="card card-body mt-5 max-w-3xl border-rose-200">
            <h2 class="font-semibold text-rose-700">Hapus anggota keluarga</h2>
            @if ($kepalaTerkunci)
                <p class="mt-1 text-sm text-slate-600">{{ $anggota->nama }} adalah kepala keluarga dan masih ada anggota lain. Jadikan anggota lain sebagai kepala keluarga terlebih dahulu, baru data ini bisa dihapus.</p>
            @else
                <p class="mt-1 text-sm text-slate-600">Gunakan bila anggota ini salah ditambahkan atau sudah tidak termasuk keluarga ini. Data dan fotonya akan dihapus permanen.</p>
                <form method="post" action="{{ route('anggota.destroy', $anggota) }}" class="mt-3"
                      onsubmit="return confirm(@js('Hapus '.$anggota->nama.' dari keluarga ini? Data yang dihapus tidak bisa dikembalikan.'))">
                    @csrf @method('delete')
                    @if (! empty($kembali)) <input type="hidden" name="kembali" value="{{ $kembali }}"> @endif
                    <button class="btn border border-rose-300 bg-white text-rose-700 hover:bg-rose-50"><x-icon name="trash" class="size-4" /> Hapus {{ $anggota->nama }}</button>
                </form>
            @endif
        </div>
    @endif
@endsection
