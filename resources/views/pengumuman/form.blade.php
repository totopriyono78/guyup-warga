@extends('layouts.app')
@section('title', $p->exists ? 'Ubah Pengumuman' : 'Buat Pengumuman')

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="mx-auto max-w-3xl">
        <x-page-header :judul="$p->exists ? 'Ubah pengumuman' : 'Buat pengumuman'" :kembali="$p->exists ? route('pengumuman.show', $p) : route('pengumuman.index')" />

        <form method="post" enctype="multipart/form-data" class="card card-body space-y-4"
              action="{{ $p->exists ? route('pengumuman.update', $p) : route('pengumuman.store') }}">
            @csrf
            @if ($p->exists) @method('put') @endif

            <div>
                <label class="label">Judul</label>
                <input name="judul" value="{{ old('judul', $p->judul) }}" required maxlength="255" class="input text-base font-medium @error('judul') input-error @enderror">
                @error('judul') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Isi pengumuman</label>
                <textarea name="isi" rows="10" required class="input leading-relaxed @error('isi') input-error @enderror">{{ old('isi', $p->isi) }}</textarea>
                @error('isi') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Ditujukan untuk</label>
                    @if ($u->isAdmin())
                        <select name="rt_id" class="input">
                            <option value="">Seluruh warga RW</option>
                            @foreach ($rts as $rt)
                                <option value="{{ $rt->id }}" @selected((int) old('rt_id', $p->rt_id) === $rt->id)>Warga RT {{ $rt->nomor }} saja</option>
                            @endforeach
                        </select>
                    @else
                        <input class="input" value="Warga RT {{ $u->rt?->nomor }}" disabled>
                    @endif
                </div>
                <div>
                    <label class="label">Waktu terbit</label>
                    <input type="datetime-local" name="terbit_pada" value="{{ old('terbit_pada', ($p->terbit_pada ?? now())->format('Y-m-d\TH:i')) }}" class="input">
                    <p class="hint">Isi waktu mendatang untuk menjadwalkan.</p>
                </div>
            </div>
            <div>
                <label class="label">Lampiran (gambar/PDF/dokumen, maks 10 MB)</label>
                <x-pilih-file name="lampiran" icon="paperclip" label="Pilih lampiran" hint="Gambar, PDF, Word, atau Excel" />
                @if ($p->lampiran)
                    <p class="hint">Lampiran saat ini: <a href="{{ $p->lampiranUrl() }}" target="_blank" class="text-brand-700 underline">{{ $p->lampiran_nama }}</a></p>
                    <label class="mt-1 flex items-center gap-2 text-sm text-rose-600"><input type="checkbox" name="hapus_lampiran" value="1" class="rounded border-slate-300"> Hapus lampiran</label>
                @endif
                @error('lampiran') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="penting" value="1" @checked(old('penting', $p->penting)) class="rounded border-slate-300 text-rose-600"> Tandai penting (tampil paling atas)</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="draf" value="1" @checked(old('draf', $p->exists && ! $p->terbit_pada)) class="rounded border-slate-300"> Simpan sebagai draf</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="publik" value="1" @checked(old('publik', $p->publik)) class="rounded border-slate-300 text-brand-700"> Tampilkan juga di halaman umum (tanpa login)</label>
            </div>
            <div class="flex gap-2 border-t border-slate-100 pt-4">
                <button class="btn btn-primary">{{ $p->exists ? 'Simpan perubahan' : 'Terbitkan' }}</button>
                <a href="{{ route('pengumuman.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
