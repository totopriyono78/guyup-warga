@extends('layouts.app')
@section('title', $kk->exists ? 'Ubah Data Keluarga' : 'Tambah Keluarga')

@section('content')
    <x-page-header :judul="$kk->exists ? 'Ubah Data Keluarga' : 'Tambah Keluarga Baru'"
                   :sub="$kk->exists ? $kk->nama_kepala : 'Isi data kartu keluarga dan kepala keluarga. Anggota lain ditambahkan setelahnya.'"
                   :kembali="$kembali ?? ($kk->exists ? route('keluarga.show', $kk) : route('keluarga.index'))" />

    <form method="post" enctype="multipart/form-data"
          action="{{ $kk->exists ? route('keluarga.update', $kk) : route('keluarga.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @csrf
        @if ($kk->exists) @method('put') @endif
        @if (! empty($kembali)) <input type="hidden" name="kembali" value="{{ $kembali }}"> @endif

        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body space-y-4">
                <h2 class="card-title">Kartu Keluarga</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Rumah / alamat</label>
                        <select name="rumah_id" class="input @error('rumah_id') input-error @enderror">
                            <option value="">— Belum ditentukan —</option>
                            @foreach ($rumahOptions as $grup => $opsi)
                                <optgroup label="{{ $grup }}">
                                    @foreach ($opsi as $id => $label)
                                        <option value="{{ $id }}" @selected((int) old('rumah_id', $kk->rumah_id) === (int) $id)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('rumah_id') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Nomor KK</label>
                        <input name="no_kk" value="{{ old('no_kk', $kk->no_kk) }}" inputmode="numeric" maxlength="16" placeholder="16 digit" class="input @error('no_kk') input-error @enderror">
                        @error('no_kk') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Status tinggal</label>
                        <select name="status_tinggal" class="input">
                            @foreach (\App\Models\KartuKeluarga::STATUS_TINGGAL as $k => $l)
                                <option value="{{ $k }}" @selected(old('status_tinggal', $kk->status_tinggal) === $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">No. HP keluarga</label>
                        <input name="no_hp" value="{{ old('no_hp', $kk->no_hp) }}" inputmode="tel" class="input">
                    </div>
                    <div>
                        <label class="label">Mulai tinggal sejak</label>
                        <input type="date" name="tanggal_masuk" value="{{ old('tanggal_masuk', $kk->tanggal_masuk?->format('Y-m-d')) }}" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Catatan (hanya terlihat pengurus)</label>
                        <textarea name="catatan" rows="2" class="input">{{ old('catatan', $kk->catatan) }}</textarea>
                    </div>
                    @if ($kk->exists)
                        <label class="flex items-center gap-2 text-sm sm:col-span-2">
                            <input type="hidden" name="aktif" value="0">
                            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $kk->aktif)) class="rounded border-slate-300 text-brand-700">
                            Masih tinggal di wilayah ini <span class="text-slate-500">(hapus centang bila sudah pindah — tagihan baru tidak dibuat)</span>
                        </label>
                    @endif
                </div>
            </div>

            @if ($kepala)
                <div class="card card-body space-y-4">
                    <h2 class="card-title">Kepala Keluarga</h2>
                    @include('anggota._fields', ['a' => $kepala, 'p' => 'kepala_', 'denganHubungan' => false])
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card card-body" x-data="{ pratinjau: null }">
                <h2 class="card-title mb-3">Foto keluarga / rumah</h2>
                <div class="mb-3 aspect-[4/3] overflow-hidden rounded-xl bg-slate-100">
                    <template x-if="pratinjau"><img :src="pratinjau" class="size-full object-cover" alt=""></template>
                    <template x-if="!pratinjau">
                        @if ($kk->fotoUrl())
                            <img src="{{ $kk->fotoUrl() }}" class="size-full object-cover" alt="">
                        @else
                            <div class="flex size-full items-center justify-center text-slate-400"><x-icon name="users" class="size-10" /></div>
                        @endif
                    </template>
                </div>
                <x-pilih-file name="foto" accept="image/*" label="Pilih foto keluarga"
                              x-on:change="pratinjau = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                <p class="hint">Foto bersama keluarga atau tampak depan rumah memudahkan identifikasi. Maks {{ round(config('siwarga.foto_max_kb') / 1024) }} MB.</p>
                @if ($kk->foto)
                    <label class="mt-2 flex items-center gap-2 text-sm text-rose-600"><input type="checkbox" name="hapus_foto" value="1" class="rounded border-slate-300"> Hapus foto</label>
                @endif
                @error('foto') <p class="error">{{ $message }}</p> @enderror
            </div>

            <button class="btn btn-primary w-full py-2.5">{{ $kk->exists ? 'Simpan perubahan' : 'Simpan keluarga' }}</button>
        </div>
    </form>
@endsection
