@extends('layouts.app')
@section('title', $d->judul)

@section('content')
    <x-page-header :judul="$d->judul" :sub="$d->lingkup.($d->mulai ? ' · mulai '.$d->mulai->translatedFormat('d M Y') : '').($d->selesai ? ' s.d. '.$d->selesai->translatedFormat('d M Y') : '')" :kembali="route('donasi.index')">
        @if ($d->publik)
            <a href="{{ route('publik.donasi', $d) }}" target="_blank" class="btn btn-secondary"><x-icon name="globe" class="size-4" /> Halaman umum</a>
        @endif
        <a href="{{ route('galang.show', $d) }}" class="btn btn-secondary"><x-icon name="heart" class="size-4" /> Halaman donasi warga</a>
        @if ($bolehKelola)
            <a href="{{ route('donasi.edit', $d) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Ubah</a>
            <a href="#catat" class="btn btn-primary lg:hidden"><x-icon name="plus" class="size-4" /> Catat donasi</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px]">
        <div class="space-y-5">
            {{-- Ringkasan --}}
            <div class="card card-body">
                <div class="mb-3 flex flex-wrap items-center gap-1.5">
                    @if ($d->berjalan())<span class="badge badge-green">Berjalan</span>@else<span class="badge badge-slate">{{ $d->aktif ? 'Selesai' : 'Ditutup' }}</span>@endif
                    @if ($d->publik)<span class="badge badge-blue">Tampil di halaman umum</span>@else<span class="badge badge-amber">Internal</span>@endif
                    @if ($d->publik && ! $d->tampilkan_total)<span class="badge badge-slate">Total disembunyikan dari umum</span>@endif
                </div>
                @php $persen = $d->persen(); @endphp
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Terkumpul</p>
                        <p class="text-base font-bold text-slate-900 sm:text-lg">{{ rupiah($d->terkumpul) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Target</p>
                        <p class="text-base font-bold text-slate-900 sm:text-lg">{{ $d->target ? rupiah($d->target) : '—' }}</p>
                    </div>
                    <div class="col-span-2 rounded-xl bg-slate-50 p-3 sm:col-span-1">
                        <p class="text-xs text-slate-500">Donatur</p>
                        <p class="text-base font-bold text-slate-900 sm:text-lg">{{ $d->jumlah_donatur }}</p>
                    </div>
                </div>
                @if (! is_null($persen))
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ max(2, $persen) }}%"></div></div>
                    <p class="mt-1 text-right text-xs text-slate-500">{{ $persen }}% dari target</p>
                @endif
            </div>

            {{-- Daftar donatur (dengan nominal — hanya pengurus) --}}
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="card-title">Donatur</h2>
                    <span class="text-xs text-slate-500">Nominal hanya terlihat oleh pengurus</span>
                </div>
                @forelse ($donaturs as $x)
                    <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900">
                                {{ $x->nama }}
                                @if ($x->anonim)<span class="badge badge-slate ml-1">Anonim di publik</span>@endif
                                @if ($x->pembayaran_id)<span class="badge badge-blue ml-1">QRIS</span>@endif
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $x->tanggal->translatedFormat('d M Y') }}
                                @if ($x->kartuKeluarga?->rumah) · {{ $x->kartuKeluarga->rumah->kode }} @endif
                                @if ($x->pencatat) · dicatat {{ $x->pencatat->name }} @endif
                            </p>
                            @if ($x->catatan)<p class="mt-0.5 text-xs text-slate-600">{{ $x->catatan }}</p>@endif
                        </div>
                        <p class="shrink-0 font-semibold text-slate-900">{{ rupiah($x->nominal) }}</p>
                        @if ($bolehKelola)
                            <form method="post" action="{{ route('donasi.donatur.destroy', $x) }}" onsubmit="return confirm(@js('Hapus catatan donasi dari '.$x->nama.'?'))">
                                @csrf @method('delete')
                                <button class="btn btn-ghost -mr-2 p-1.5 text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                            </form>
                        @endif
                    </div>
                @empty
                    <x-empty icon="heart" judul="Belum ada donatur">Catat donasi yang masuk melalui formulir di samping.</x-empty>
                @endforelse
            </div>

            {{-- Transaksi QRIS dari halaman umum --}}
            @if ($qris->isNotEmpty())
                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 class="card-title">Transaksi QRIS</h2>
                        <span class="text-xs text-slate-500">Yang lunas otomatis masuk daftar donatur</span>
                    </div>
                    @foreach ($qris as $q)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 last:border-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $q->donatur_nama ?: 'Tanpa nama' }}@if ($q->donatur_anonim) <span class="badge badge-slate ml-1">Anonim</span>@endif</p>
                                <p class="text-xs text-slate-500">{{ $q->created_at->translatedFormat('d M Y H:i') }} · {{ \Illuminate\Support\Str::limit($q->order_id, 8, '') }}@if ($q->donatur_pesan) · “{{ $q->donatur_pesan }}”@endif</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold text-slate-900">{{ rupiah($q->jumlah_iuran) }}</p>
                                <span class="badge {{ ['paid' => 'badge-green', 'pending' => 'badge-amber'][$q->status] ?? 'badge-slate' }}">{{ $q->statusLabel() }}</span>
                            </div>
                            @if ($bolehKelola && ! $q->isPaid())
                                <div class="flex shrink-0 flex-col gap-1">
                                    <form method="post" action="{{ route('donasi.qris.cek', $q) }}">
                                        @csrf
                                        <button class="btn btn-ghost btn-sm px-2" title="Cek status ke AINO"><x-icon name="refresh" class="size-4" /></button>
                                    </form>
                                    <form method="post" action="{{ route('donasi.qris.berhasil', $q) }}"
                                          onsubmit="return confirm('Tandai transaksi ini BERHASIL? Lakukan hanya bila dana sudah dipastikan masuk di dashboard AINO / rekening.')">
                                        @csrf
                                        <button class="btn btn-ghost btn-sm px-2 text-emerald-700" title="Tandai berhasil (manual)"><x-icon name="check" class="size-4" /></button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            @if ($bolehKelola)
                <form id="catat" method="post" action="{{ route('donasi.donatur.store', $d) }}" class="card card-body scroll-mt-20 space-y-3" x-data="{ kk: @js((string) old('kartu_keluarga_id', '')) }">
                    @csrf
                    <h2 class="card-title">Catat donasi masuk</h2>
                    <div>
                        <label class="label">Keluarga warga (opsional)</label>
                        <select name="kartu_keluarga_id" x-model="kk" class="input">
                            <option value="">— Bukan warga / isi nama manual —</option>
                            @foreach ($kkOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Nama donatur</label>
                        <input name="nama" value="{{ old('nama') }}" maxlength="255" class="input @error('nama') input-error @enderror"
                               :placeholder="kk ? 'Kosongkan untuk memakai nama kepala keluarga' : 'mis. Toko Berkah / Bpk. Ahmad'">
                        @error('nama') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Nominal (Rp)</label>
                            <input type="number" name="nominal" min="1" step="1" inputmode="numeric" required value="{{ old('nominal') }}" class="input @error('nominal') input-error @enderror">
                            @error('nominal') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Tanggal</label>
                            <input type="date" name="tanggal" required max="{{ now()->toDateString() }}" value="{{ old('tanggal', now()->toDateString()) }}" class="input @error('tanggal') input-error @enderror">
                            @error('tanggal') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="label">Catatan (internal)</label>
                        <input name="catatan" value="{{ old('catatan') }}" maxlength="255" class="input" placeholder="mis. transfer BRI, tunai via bendahara">
                    </div>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="anonim" value="1" @checked(old('anonim')) class="mt-0.5 rounded border-slate-300 text-brand-700"> <span>Sembunyikan nama di halaman umum <span class="text-slate-500">(tampil “Donatur anonim”)</span></span></label>
                    <button class="btn btn-primary w-full"><x-icon name="plus" class="size-4" /> Simpan donasi</button>
                </form>

                <form method="post" action="{{ route('donasi.destroy', $d) }}" onsubmit="return confirm('Hapus penggalangan dana ini? Bila sudah ada donatur, program hanya ditutup & disembunyikan.')">
                    @csrf @method('delete')
                    <button class="btn btn-ghost w-full text-rose-600"><x-icon name="trash" class="size-4" /> Hapus / tutup program</button>
                </form>
            @endif

            @if ($d->cara_donasi)
                <div class="card card-body">
                    <h2 class="card-title mb-1">Cara berdonasi</h2>
                    <div class="prose-isi text-sm">{{ $d->cara_donasi }}</div>
                </div>
            @endif
        </aside>
    </div>
@endsection
