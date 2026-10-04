@extends('layouts.app')
@section('title', 'Keluarga '.$kk->nama_kepala)

@if ($kk->rumah?->punyaLokasi())
    @push('head')
        <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script src="{{ asset('js/peta.js') }}?v={{ @filemtime(public_path('js/peta.js')) }}"></script>
    @endpush
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const el = document.getElementById('peta-rumah');
                if (!el || !window.SiwargaPeta) return;
                const map = await SiwargaPeta.buat(el, @js(\App\Models\Pengaturan::petaJs()));
                // pratinjau saja: klik membuka peta wilayah
                ['dragging', 'scrollWheelZoom', 'doubleClickZoom', 'touchZoom', 'boxZoom', 'keyboard'].forEach(h => map[h]?.disable());
                map.zoomControl?.remove();
                document.querySelectorAll('#peta-rumah .leaflet-control-layers').forEach(c => c.remove());
                const d = el.dataset;
                L.marker([+d.lat, +d.lng], { icon: SiwargaPeta.ikon('terisi', d.kode, { warnaRt: d.warna, dipilih: true }), interactive: false }).addTo(map);
                map.setView([+d.lat, +d.lng], 19);
            });
        </script>
    @endpush
@endif

@section('content')
    @php $u = auth()->user(); $lihatSensitif = $bolehKelola || (int) $u->kartu_keluarga_id === (int) $kk->id; @endphp

    <x-page-header :judul="'Keluarga '.$kk->nama_kepala" :sub="$kk->rumah?->alamat ?? 'Belum terhubung ke rumah'"
                   :kembali="$u->isPengurus() ? route('keluarga.index') : null">
        @if ($bolehKelola)
            <a href="{{ route('anggota.create', $kk) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Anggota</a>
            <a href="{{ route('keluarga.edit', $kk) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Ubah KK</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card overflow-hidden" x-data="{ zoom: false }">
                <div class="grid sm:grid-cols-5">
                    <div class="bg-slate-100 sm:col-span-2">
                        @if ($kk->fotoUrl())
                            <img src="{{ $kk->fotoUrl() }}" alt="Foto keluarga {{ $kk->nama_kepala }}" class="aspect-[4/3] size-full cursor-zoom-in object-cover" @click="zoom = true">
                        @else
                            <div class="flex h-28 w-full flex-col items-center justify-center text-slate-400 sm:aspect-[4/3] sm:h-auto sm:size-full">
                                <x-icon name="users" class="size-10 sm:size-12" /><span class="mt-1 text-xs">Belum ada foto keluarga</span>
                            </div>
                        @endif
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 p-5 text-sm sm:col-span-3">
                        <div><dt class="text-xs text-slate-500">Kepala keluarga</dt><dd class="font-medium text-slate-900">{{ $kk->nama_kepala }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Status tinggal</dt><dd class="font-medium">{{ \App\Models\KartuKeluarga::STATUS_TINGGAL[$kk->status_tinggal] ?? $kk->status_tinggal }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Nomor KK</dt><dd class="font-mono">{{ $lihatSensitif ? ($kk->no_kk ?: '—') : '••••' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">No. HP</dt><dd>@if ($kk->no_hp) <a href="tel:{{ $kk->no_hp }}" class="text-brand-700">{{ $kk->no_hp }}</a> @else — @endif</dd></div>
                        <div><dt class="text-xs text-slate-500">Alamat</dt><dd>{{ $kk->rumah?->alamat ?? '—' }}
                            @if ($kk->rumah?->punyaLokasi())
                                <a href="{{ route('denah', ['tampilan' => 'peta']) }}#rumah-{{ $kk->rumah->id }}" class="mt-0.5 flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline"><x-icon name="pin" class="size-3.5" /> Lihat di peta</a>
                            @endif
                        </dd></div>
                        <div><dt class="text-xs text-slate-500">Tinggal sejak</dt><dd>{{ $kk->tanggal_masuk?->translatedFormat('F Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Jumlah anggota</dt><dd>{{ $kk->anggota->count() }} orang</dd></div>
                        <div><dt class="text-xs text-slate-500">Status</dt><dd>@if ($kk->aktif) <span class="badge badge-green">Aktif</span> @else <span class="badge badge-slate">Sudah pindah</span> @endif</dd></div>
                        @if ($bolehKelola && $kk->catatan)
                            <div class="col-span-2"><dt class="text-xs text-slate-500">Catatan pengurus</dt><dd class="prose-isi">{{ $kk->catatan }}</dd></div>
                        @endif
                    </dl>
                </div>
                @if ($kk->fotoUrl())
                    <div x-cloak x-show="zoom" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="zoom = false">
                        <img src="{{ $kk->fotoUrl() }}" alt="" class="max-h-full max-w-full rounded-xl">
                    </div>
                @endif
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Anggota keluarga</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{ zoom: null }">
                    @forelse ($kk->anggota as $a)
                        <div class="card flex gap-4 p-4">
                            @if ($a->fotoUrl())
                                <img src="{{ $a->fotoUrl() }}" alt="Foto {{ $a->nama }}" class="size-20 shrink-0 cursor-zoom-in rounded-xl object-cover ring-1 ring-slate-200" @click="zoom = @js($a->fotoUrl())">
                            @else
                                <x-avatar :nama="$a->nama" size="size-20" rounded="rounded-xl" class="text-2xl" />
                            @endif
                            <div class="min-w-0 flex-1 text-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-slate-900">{{ $a->nama }}</p>
                                        <p class="text-xs text-slate-500">{{ $a->hubungan }} · {{ $a->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}@if ($a->umur !== null) · {{ $a->umur }} th @endif</p>
                                    </div>
                                    @if ($bolehKelola)
                                        <div class="-mr-2 -mt-1 flex shrink-0">
                                            <a href="{{ route('anggota.edit', $a) }}" class="btn btn-ghost btn-sm" title="Ubah"><x-icon name="pencil" class="size-4" /></a>
                                            @if ($a->hubungan === 'Kepala Keluarga' && $kk->anggota->count() > 1)
                                                <span class="btn btn-ghost btn-sm cursor-not-allowed text-slate-300" title="Kepala keluarga tidak bisa dihapus selama masih ada anggota lain. Ubah dulu kepala keluarganya."><x-icon name="trash" class="size-4" /></span>
                                            @else
                                                <form method="post" action="{{ route('anggota.destroy', $a) }}" onsubmit="return confirm(@js('Hapus '.$a->nama.' dari keluarga ini? Data yang dihapus tidak bisa dikembalikan.'))">
                                                    @csrf @method('delete')
                                                    <button class="btn btn-ghost btn-sm text-rose-600 hover:bg-rose-50" title="Hapus anggota"><x-icon name="trash" class="size-4" /></button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <dl class="mt-2 space-y-0.5 text-xs text-slate-600">
                                    @if ($a->nik) <div><span class="text-slate-400">NIK</span> <span class="font-mono">{{ $lihatSensitif ? $a->nik : $a->nik_samar }}</span></div> @endif
                                    @if ($lihatSensitif && ($a->tempat_lahir || $a->tanggal_lahir))
                                        <div><span class="text-slate-400">TTL</span> {{ $a->tempat_lahir }}{{ $a->tanggal_lahir ? ', '.$a->tanggal_lahir->translatedFormat('j F Y') : '' }}</div>
                                    @endif
                                    @if ($a->pekerjaan) <div><span class="text-slate-400">Pekerjaan</span> {{ $a->pekerjaan }}</div> @endif
                                    @if ($lihatSensitif && $a->agama) <div><span class="text-slate-400">Agama</span> {{ $a->agama }} · {{ $a->status_perkawinan }}</div> @endif
                                    @if ($a->no_hp) <div><span class="text-slate-400">HP</span> <a href="tel:{{ $a->no_hp }}" class="text-brand-700">{{ $a->no_hp }}</a></div> @endif
                                </dl>
                            </div>
                        </div>
                    @empty
                        <div class="card sm:col-span-2"><x-empty icon="users" judul="Belum ada anggota" /></div>
                    @endforelse

                    <div x-cloak x-show="zoom" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="zoom = null">
                        <img :src="zoom" alt="" class="max-h-full max-w-full rounded-xl">
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Lokasi rumah di peta --}}
            @if ($kk->rumah)
                @php
                    $rumah = $kk->rumah;
                    $urlPeta = route('denah', ['tampilan' => 'peta']).'#rumah-'.$rumah->id;
                    $urlAtur = $u->canManageRt($rumah->blok->rt_id) ? route('peta.edit').'#rumah-'.$rumah->id : null;
                @endphp
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between gap-2 px-5 py-3.5">
                        <h2 class="card-title flex items-center gap-1.5"><x-icon name="pin" class="size-4 text-brand-700" /> Lokasi rumah</h2>
                        <span class="badge {{ $rumah->punyaLokasi() ? 'badge-green' : 'badge-slate' }}">{{ $rumah->punyaLokasi() ? 'Sudah ditandai' : 'Belum ditandai' }}</span>
                    </div>
                    @if ($rumah->punyaLokasi())
                        <a href="{{ $urlPeta }}" class="block border-y border-slate-100" title="Buka di peta wilayah">
                            <div id="peta-rumah" class="h-48 w-full bg-slate-100" data-lat="{{ $rumah->lat }}" data-lng="{{ $rumah->lng }}"
                                 data-kode="{{ $rumah->blok->nama }}-{{ $rumah->nomor }}" data-warna="{{ $rumah->blok->rt->warna }}"></div>
                        </a>
                    @else
                        <p class="border-t border-slate-100 bg-amber-50 px-5 py-3 text-xs text-amber-900">Rumah {{ $rumah->alamat }} belum ditandai di peta.</p>
                    @endif
                    <div class="grid gap-2 p-3 {{ $rumah->punyaLokasi() && $urlAtur ? 'grid-cols-2' : 'grid-cols-1' }}">
                        @if ($rumah->punyaLokasi())
                            <a href="{{ $urlPeta }}" class="btn btn-secondary btn-sm"><x-icon name="map" class="size-4" /> Lihat di peta</a>
                        @endif
                        @if ($urlAtur)
                            <a href="{{ $urlAtur }}" class="btn btn-sm {{ $rumah->punyaLokasi() ? 'btn-ghost' : 'btn-primary' }}"><x-icon name="pencil" class="size-4" /> {{ $rumah->punyaLokasi() ? 'Perbaiki titik' : 'Tandai di peta' }}</a>
                        @endif
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <h2 class="card-title">Iuran</h2>
                    @if ((int) $u->kartu_keluarga_id === (int) $kk->id)
                        <a href="{{ route('iuran.saya') }}" class="text-sm font-medium text-brand-700">Bayar →</a>
                    @endif
                </div>
                @forelse ($tagihan as $t)
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0">
                        <div>
                            <p class="font-medium text-slate-800">{{ $t->periode_label }}</p>
                            <p class="text-xs text-slate-500">{{ $t->isLunas() ? rupiah($t->nominal_masuk) : ($t->sukarela ? 'Sukarela' : rupiah($t->nominal)) }}@if ($t->isLunas()) · {{ \App\Models\Tagihan::METODE[$t->metode] ?? $t->metode }} {{ $t->dibayar_pada?->format('d/m/Y') }} @endif</p>
                        </div>
                        <div class="flex items-center gap-1">
                            @if ($t->isLunas())
                                <span class="badge badge-green">Lunas</span>
                            @else
                                <span class="badge badge-red">Belum</span>
                                @if ($bolehKelola && ! $t->sukarela)
                                    <form method="post" action="{{ route('iuran.lunas', $t) }}" onsubmit="return confirm(@js('Catat pembayaran tunai untuk '.$t->periode_label.'?'))">
                                        @csrf <input type="hidden" name="metode" value="tunai">
                                        <button class="btn btn-ghost btn-sm" title="Catat bayar tunai">Tunai</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-slate-500">Belum ada tagihan.</p>
                @endforelse
            </div>

            @if ($bolehKelola)
                <div class="card card-body text-sm">
                    <h2 class="card-title mb-2">Akun aplikasi</h2>
                    @if ($kk->akun)
                        <p class="text-slate-600">Terhubung dengan akun <b>{{ $kk->akun->email }}</b>.</p>
                        <a href="{{ route('pengguna.edit', $kk->akun) }}" class="btn btn-secondary btn-sm mt-3 w-full">Kelola akun</a>
                    @else
                        <p class="text-slate-600">Keluarga ini belum punya akun untuk login, melihat pengumuman, dan membayar iuran.</p>
                        <a href="{{ route('pengguna.create', ['kk' => $kk->id]) }}" class="btn btn-primary btn-sm mt-3 w-full"><x-icon name="key" class="size-4" /> Buatkan akun</a>
                    @endif
                </div>

                <form method="post" action="{{ route('keluarga.destroy', $kk) }}" onsubmit="return confirm('Hapus seluruh data keluarga ini? Tindakan ini tidak bisa dibatalkan.')">
                    @csrf @method('delete')
                    <button class="btn btn-ghost w-full text-rose-600"><x-icon name="trash" class="size-4" /> Hapus data keluarga</button>
                </form>
            @endif
        </div>
    </div>
@endsection
