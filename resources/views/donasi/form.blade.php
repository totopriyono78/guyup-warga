@extends('layouts.app')
@section('title', $d->exists ? 'Ubah Penggalangan Dana' : 'Buat Penggalangan Dana')

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="mx-auto max-w-3xl">
        <x-page-header :judul="$d->exists ? 'Ubah penggalangan dana' : 'Buat penggalangan dana'" :kembali="$d->exists ? route('donasi.show', $d) : route('donasi.index')" />

        <form method="post" enctype="multipart/form-data" class="card card-body space-y-4"
              action="{{ $d->exists ? route('donasi.update', $d) : route('donasi.store') }}">
            @csrf
            @if ($d->exists) @method('put') @endif

            <div>
                <label class="label">Judul</label>
                <input name="judul" value="{{ old('judul', $d->judul) }}" required maxlength="255" placeholder="mis. Renovasi Pos Kamling" class="input text-base font-medium @error('judul') input-error @enderror">
                @error('judul') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Ringkasan singkat</label>
                <input name="ringkasan" value="{{ old('ringkasan', $d->ringkasan) }}" maxlength="255" class="input" placeholder="Satu kalimat yang tampil di kartu">
            </div>
            <div>
                <label class="label">Deskripsi / latar belakang</label>
                <textarea name="deskripsi" rows="7" class="input leading-relaxed">{{ old('deskripsi', $d->deskripsi) }}</textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="label">Target dana (Rp, opsional)</label>
                    <input type="number" name="target" min="0" step="1000" inputmode="numeric" value="{{ old('target', $d->target) }}" class="input @error('target') input-error @enderror">
                    @error('target') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Mulai</label>
                    <input type="date" name="mulai" value="{{ old('mulai', $d->mulai?->toDateString()) }}" class="input">
                </div>
                <div>
                    <label class="label">Selesai (opsional)</label>
                    <input type="date" name="selesai" value="{{ old('selesai', $d->selesai?->toDateString()) }}" class="input @error('selesai') input-error @enderror">
                    @error('selesai') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="label">Lingkup</label>
                @if ($u->isAdmin())
                    <select name="rt_id" class="input">
                        <option value="">Seluruh RW</option>
                        @foreach ($rts as $rt)
                            <option value="{{ $rt->id }}" @selected((int) old('rt_id', $d->rt_id) === $rt->id)>RT {{ $rt->nomor }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="input" value="RT {{ $u->rt?->nomor }}" disabled>
                @endif
            </div>
            <div>
                <label class="label">Cara berdonasi</label>
                <textarea name="cara_donasi" rows="4" class="input" placeholder="mis. Tunai ke bendahara RW, atau transfer ke rekening kas …">{{ old('cara_donasi', $d->cara_donasi) }}</textarea>
                <p class="hint">Ditampilkan di halaman umum selama program berjalan.</p>
            </div>
            <div>
                <label class="label">Gambar (opsional)</label>
                <x-pilih-file name="gambar" accept="image/*" label="Pilih gambar" />
                @error('gambar') <p class="error">{{ $message }}</p> @enderror
                @if ($d->gambarUrl())
                    <img src="{{ $d->gambarUrl() }}" alt="" class="mt-2 h-28 rounded-lg object-cover">
                    <label class="mt-1 flex items-center gap-2 text-sm text-rose-600"><input type="checkbox" name="hapus_gambar" value="1" class="rounded border-slate-300"> Hapus gambar</label>
                @endif
            </div>
            <div class="space-y-2 rounded-xl bg-slate-50 p-3 text-sm">
                <label class="flex items-start gap-2"><input type="checkbox" name="publik" value="1" @checked(old('publik', $d->publik)) class="mt-0.5 rounded border-slate-300 text-brand-700"> <span>Tampilkan di halaman umum <span class="text-slate-500">(nama donatur tampil, nominal tidak)</span></span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="tampilkan_total" value="1" @checked(old('tampilkan_total', $d->tampilkan_total)) class="mt-0.5 rounded border-slate-300 text-brand-700"> <span>Tampilkan total terkumpul &amp; progres di halaman umum</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="aktif" value="1" @checked(old('aktif', $d->aktif)) class="mt-0.5 rounded border-slate-300 text-brand-700"> <span>Masih menerima donasi</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" name="terima_qris" value="1" @checked(old('terima_qris', $d->terima_qris ?? true)) class="mt-0.5 rounded border-slate-300 text-brand-700"> <span>Terima donasi lewat QRIS <span class="text-slate-500">(dibayar otomatis via AINO, donatur langsung tercatat)</span></span></label>
            </div>
            <div class="flex gap-2 border-t border-slate-100 pt-4">
                <button class="btn btn-primary">{{ $d->exists ? 'Simpan perubahan' : 'Buat penggalangan dana' }}</button>
                <a href="{{ $d->exists ? route('donasi.show', $d) : route('donasi.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
