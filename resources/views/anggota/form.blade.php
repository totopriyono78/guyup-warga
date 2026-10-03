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
@endsection
