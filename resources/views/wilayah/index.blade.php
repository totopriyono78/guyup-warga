@extends('layouts.app')
@section('title', 'RT, Blok & Rumah')

@section('content')
    @php $u = auth()->user(); @endphp
    <x-page-header judul="RT, Blok & Rumah" sub="Susun struktur wilayah. Denah dibentuk dari posisi rumah di setiap blok.">
        <a href="{{ route('peta.edit') }}" class="btn btn-primary"><x-icon name="map" class="size-4" /> Atur titik rumah di peta</a>
        <a href="{{ route('denah') }}" class="btn btn-secondary">Lihat denah</a>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @forelse ($rts as $rt)
                <div class="card" x-data="{ edit: false }">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4" style="border-left: 5px solid {{ $rt->warna }}">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">RT {{ $rt->nomor }}</h2>
                            <p class="text-sm text-slate-500">
                                Ketua: {{ $rt->nama_ketua ?: '—' }} @if ($rt->no_hp_ketua) · {{ $rt->no_hp_ketua }} @endif
                                · {{ $rt->bloks_count }} blok · {{ $rt->rumahs_count }} rumah
                            </p>
                        </div>
                        @if ($u->isAdmin())
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" @click="edit = !edit"><x-icon name="pencil" class="size-4" /> Ubah RT</button>
                                <form method="post" action="{{ route('rt.destroy', $rt) }}" onsubmit="return confirm(@js('Hapus RT '.$rt->nomor.' beserta semua blok & rumahnya?'))">
                                    @csrf @method('delete')
                                    <button class="btn btn-ghost btn-sm text-rose-600"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @if ($u->isAdmin())
                        <form x-cloak x-show="edit" method="post" action="{{ route('rt.update', $rt) }}" class="grid gap-3 border-b border-slate-100 bg-slate-50 p-5 sm:grid-cols-4">
                            @csrf @method('put')
                            <div><label class="label">Nomor RT</label><input name="nomor" value="{{ $rt->nomor }}" class="input" required></div>
                            <div><label class="label">Nama ketua</label><input name="nama_ketua" value="{{ $rt->nama_ketua }}" class="input"></div>
                            <div><label class="label">No. HP ketua</label><input name="no_hp_ketua" value="{{ $rt->no_hp_ketua }}" class="input"></div>
                            <div><label class="label">Warna di denah</label><input type="color" name="warna" value="{{ $rt->warna }}" class="input h-10 p-1"></div>
                            <div class="sm:col-span-4"><button class="btn btn-primary">Simpan RT</button></div>
                        </form>
                    @endif

                    <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($rt->bloks as $blok)
                            <a href="{{ route('blok.show', $blok) }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-brand-400 hover:bg-brand-50/40">
                                <p class="font-semibold text-slate-900">Blok {{ $blok->nama }}</p>
                                <p class="text-sm text-slate-500">{{ $blok->rumahs_count }} rumah</p>
                                <p class="mt-2 text-xs font-medium text-brand-700">Atur rumah & denah →</p>
                            </a>
                        @endforeach

                        <form method="post" action="{{ route('blok.store') }}" class="rounded-xl border border-dashed border-slate-300 p-4">
                            @csrf
                            <input type="hidden" name="rt_id" value="{{ $rt->id }}">
                            <label class="label">Tambah blok baru</label>
                            <div class="flex gap-2">
                                <input name="nama" placeholder="mis. A, B1, Melati" class="input" required maxlength="30">
                                <button class="btn btn-primary"><x-icon name="plus" class="size-4" /></button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <div class="card">
                    <x-empty icon="building" judul="Belum ada RT">
                        @if ($u->isAdmin()) Mulai dengan menambahkan RT pertama di formulir samping. @else Hubungi pengurus RW untuk membuat data RT Anda. @endif
                    </x-empty>
                </div>
            @endforelse
        </div>

        <div class="space-y-5">
            @if ($u->isAdmin())
                <form method="post" action="{{ route('rt.store') }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="card-title">Tambah RT</h2>
                    <div><label class="label">Nomor RT</label><input name="nomor" value="{{ old('nomor') }}" placeholder="01" class="input" required></div>
                    <div><label class="label">Nama ketua RT</label><input name="nama_ketua" value="{{ old('nama_ketua') }}" class="input"></div>
                    <div><label class="label">No. HP ketua</label><input name="no_hp_ketua" value="{{ old('no_hp_ketua') }}" class="input"></div>
                    <div><label class="label">Warna penanda</label><input type="color" name="warna" value="{{ old('warna', \App\Models\Rt::warnaBerikutnya()) }}" class="input h-10 p-1"></div>
                    <button class="btn btn-primary w-full">Simpan RT</button>
                </form>
            @endif

            <div class="card card-body text-sm text-slate-600">
                <h2 class="card-title mb-2">Cara menyusun denah</h2>
                <ol class="list-inside list-decimal space-y-1.5">
                    <li>Buat RT, lalu buat blok di dalam RT.</li>
                    <li>Buka blok, tambahkan rumah sekaligus (mis. No. 1–20 dalam 2 baris).</li>
                    <li>Atur <b>baris</b> dan <b>kolom</b> tiap rumah agar sesuai posisi aslinya. Setiap 2 baris otomatis dipisah jalan.</li>
                    <li>Buka <b>Atur titik rumah di peta</b>, lalu klik atap tiap rumah di peta satelit (atau impor bangunan dari OpenStreetMap).</li>
                    <li>Daftarkan keluarga ke setiap rumah dari menu Data Warga.</li>
                </ol>
            </div>
        </div>
    </div>
@endsection
