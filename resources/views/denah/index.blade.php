@extends('layouts.app')
@section('title', 'Denah Wilayah')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/peta.js') }}?v={{ @filemtime(public_path('js/peta.js')) }}"></script>
@endpush

@section('content')
    @php
        $u = auth()->user();
        $warnaHunian = [
            'terisi' => 'bg-emerald-500 text-white border-emerald-600 hover:bg-emerald-600',
            'kontrakan' => 'bg-sky-500 text-white border-sky-600 hover:bg-sky-600',
            'belum_didata' => 'bg-white text-slate-600 border-dashed border-slate-400 hover:bg-slate-50',
            'kosong' => 'bg-slate-200 text-slate-500 border-slate-300 hover:bg-slate-300',
            'usaha' => 'bg-amber-400 text-amber-950 border-amber-500 hover:bg-amber-500',
        ];
        $warnaIuran = [
            'lunas' => 'bg-emerald-500 text-white border-emerald-600 hover:bg-emerald-600',
            'sebagian' => 'bg-amber-400 text-amber-950 border-amber-500 hover:bg-amber-500',
            'belum' => 'bg-rose-500 text-white border-rose-600 hover:bg-rose-600',
            'tidak_ada' => 'bg-slate-200 text-slate-500 border-slate-300 hover:bg-slate-300',
        ];
    @endphp

    <div x-data="denah(@js($detail), @js($peta), @js($tampilan), @js(config('siwarga.peta.warga')))" @keydown.escape.window="tutup()">
        <x-page-header judul="Denah Wilayah" sub="Ketuk titik atau petak rumah untuk melihat penghuninya.">
            <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 text-sm">
                <a href="{{ request()->fullUrlWithQuery(['tampilan' => 'peta']) }}" class="rounded-md px-3 py-1.5 {{ $tampilan === 'peta' ? 'bg-brand-700 text-white' : 'text-slate-600' }}"><x-icon name="map" class="-mt-0.5 inline size-4" /> Peta</a>
                <a href="{{ request()->fullUrlWithQuery(['tampilan' => 'blok']) }}" class="rounded-md px-3 py-1.5 {{ $tampilan === 'blok' ? 'bg-brand-700 text-white' : 'text-slate-600' }}">Denah blok</a>
            </div>
            @if ($u->isPengurus())
                <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 text-sm">
                    <a href="{{ request()->fullUrlWithQuery(['mode' => 'hunian']) }}" class="rounded-md px-3 py-1.5 {{ $mode === 'hunian' ? 'bg-brand-700 text-white' : 'text-slate-600' }}">Hunian</a>
                    <a href="{{ request()->fullUrlWithQuery(['mode' => 'iuran']) }}" class="rounded-md px-3 py-1.5 {{ $mode === 'iuran' ? 'bg-brand-700 text-white' : 'text-slate-600' }}">Iuran {{ now()->translatedFormat('M') }}</a>
                </div>
                <a href="{{ route('peta.edit') }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Atur titik rumah</a>
            @endif
        </x-page-header>

        {{-- Filter RT --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['rt' => null]) }}" class="badge px-3 py-1.5 text-sm {{ ! $rtId ? 'bg-brand-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300' }}">Semua RT</a>
            @foreach ($rts as $rt)
                <a href="{{ request()->fullUrlWithQuery(['rt' => $rt->id]) }}"
                   class="badge px-3 py-1.5 text-sm {{ $rtId === $rt->id ? 'text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300' }}"
                   @if ($rtId === $rt->id) style="background: {{ $rt->warna }}" @endif>
                    <span class="size-2 rounded-full" style="background: {{ $rt->warna }}"></span> RT {{ $rt->nomor }}
                </a>
            @endforeach
            <div class="ml-auto w-full sm:w-64">
                <input type="search" x-model="sorot" placeholder="Sorot nama di denah…" class="input">
            </div>
        </div>

        {{-- Legenda --}}
        <div class="mb-5 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600">
            @if ($mode === 'iuran')
                @foreach (['lunas' => 'Lunas', 'sebagian' => 'Sebagian lunas', 'belum' => 'Belum bayar', 'tidak_ada' => 'Tidak ada tagihan'] as $k => $l)
                    <span class="inline-flex items-center gap-1.5"><span class="size-3.5 rounded border {{ $warnaIuran[$k] }}"></span>{{ $l }}</span>
                @endforeach
            @else
                @foreach (['terisi' => 'Dihuni', 'kontrakan' => 'Dikontrakkan', 'belum_didata' => 'Belum didata', 'kosong' => 'Kosong', 'usaha' => 'Usaha / fasum'] as $k => $l)
                    <span class="inline-flex items-center gap-1.5"><span class="size-3.5 rounded border {{ $warnaHunian[$k] }}"></span>{{ $l }}</span>
                @endforeach
            @endif
        </div>

        @if ($petaWilayah)
            <details class="card mb-5 overflow-hidden">
                <summary class="cursor-pointer px-5 py-3 text-sm font-medium text-slate-700">Peta wilayah {{ pengaturan('nama_rw') }}</summary>
                <a href="{{ Storage::disk('public')->url($petaWilayah) }}" target="_blank">
                    <img src="{{ Storage::disk('public')->url($petaWilayah) }}" alt="Peta wilayah" class="w-full border-t border-slate-100">
                </a>
            </details>
        @endif

        @if ($bloks->isEmpty())
            <div class="card">
                <x-empty icon="map" judul="Denah belum dibuat">
                    @if ($u->isPengurus())
                        Tambahkan RT, blok, dan rumah di menu <a href="{{ route('wilayah') }}" class="font-medium text-brand-700 underline">RT, Blok &amp; Rumah</a>.
                    @else
                        Pengurus belum menyusun denah wilayah.
                    @endif
                </x-empty>
            </div>
        @endif

        @if ($tampilan === 'peta')
            @if ($u->isPengurus() && $tanpaLokasi > 0)
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span>{{ $tanpaLokasi }} rumah belum punya titik di peta.</span>
                    <a href="{{ route('peta.edit') }}" class="btn btn-secondary btn-sm">Atur titik rumah</a>
                </div>
            @endif
            @if (! $adaLokasi)
                <div class="card mb-5">
                    <x-empty icon="map" judul="Belum ada rumah yang ditandai di peta">
                        @if ($u->isPengurus())
                            Buka <a href="{{ route('peta.edit') }}" class="font-medium text-brand-700 underline">Atur titik rumah</a>, lalu klik atap setiap rumah di peta satelit.
                        @else
                            Pengurus belum menandai rumah di peta. Gunakan tampilan “Denah blok”.
                        @endif
                    </x-empty>
                </div>
            @endif
            <div class="card isolate overflow-hidden">
                <div x-ref="peta" class="h-[70vh] min-h-[420px] w-full"></div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2" @if ($tampilan !== 'blok') style="display:none" @endif>
            @foreach ($bloks as $blok)
                @php
                    $maxKolom = max(1, (int) $blok->rumahs->max('kolom'));
                    $maxBaris = max(1, (int) $blok->rumahs->max('baris'));
                    // setiap 2 baris rumah diselingi 1 baris jalan
                    $barisGrid = fn ($b) => $b + intdiv($b - 1, 2);
                    $totalGrid = $barisGrid($maxBaris);
                @endphp
                <section class="card overflow-hidden">
                    <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3" style="border-top: 4px solid {{ $blok->rt->warna }}">
                        <div>
                            <h2 class="font-semibold text-slate-900">Blok {{ $blok->nama }}</h2>
                            <p class="text-xs text-slate-500">RT {{ $blok->rt->nomor }} · {{ $blok->rumahs->count() }} rumah · {{ $blok->rumahs->sum(fn ($r) => $r->keluargaAktif->count()) }} KK</p>
                        </div>
                        @if ($u->canManageRt($blok->rt_id))
                            <a href="{{ route('blok.show', $blok) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Ubah</a>
                        @endif
                    </header>
                    <div class="overflow-x-auto p-3 sm:p-4">
                        @if ($blok->rumahs->isEmpty())
                            <p class="py-6 text-center text-sm text-slate-500">Belum ada rumah di blok ini.</p>
                        @else
                            <div class="grid gap-1 sm:gap-1.5" style="grid-template-columns: repeat({{ $maxKolom }}, minmax(2.5rem, 1fr)); grid-template-rows: repeat({{ $totalGrid }}, auto); min-width: {{ $maxKolom * 2.75 }}rem">
                                @for ($b = 2; $b < $maxBaris; $b += 2)
                                    <div class="flex h-6 items-center justify-center rounded bg-slate-100 text-[10px] uppercase tracking-[0.3em] text-slate-400"
                                         style="grid-row: {{ $barisGrid($b) + 1 }}; grid-column: 1 / -1">jalan</div>
                                @endfor
                                @foreach ($blok->rumahs as $rumah)
                                    @php
                                        $d = $detail[$rumah->id];
                                        $kat = $d['kat'];
                                        $kelas = ($mode === 'iuran' ? $warnaIuran : $warnaHunian)[$kat];
                                    @endphp
                                    <button type="button" id="rumah-{{ $rumah->id }}" @click="buka({{ $rumah->id }})"
                                            class="relative flex aspect-[4/5] min-h-12 flex-col items-center justify-center rounded-lg border-2 px-1 text-center transition {{ $kelas }}"
                                            :class="{ 'ring-4 ring-yellow-400 ring-offset-1 scale-105 z-10': cocok({{ $rumah->id }}), 'opacity-30': sorot.length > 1 && !cocok({{ $rumah->id }}) }"
                                            style="grid-row: {{ $barisGrid($rumah->baris) }}; grid-column: {{ $rumah->kolom }}"
                                            title="{{ $d['kode'] }}{{ $d['namaSingkat'] ? ' · '.$d['namaSingkat'] : '' }}">
                                        <span class="text-sm font-bold leading-none">{{ $rumah->nomor }}</span>
                                        @if ($d['namaSingkat'])
                                            <span class="mt-1 hidden w-full truncate text-[10px] leading-tight opacity-90 sm:block">{{ $d['namaSingkat'] }}</span>
                                        @endif
                                        @if ($d['jumlahKk'] > 1)
                                            <span class="absolute -right-1 -top-1 rounded-full bg-slate-900 px-1 text-[9px] font-bold text-white">{{ $d['jumlahKk'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Panel detail rumah --}}
        <div x-cloak x-show="aktif" class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-slate-900/40" x-transition.opacity @click="tutup()"></div>
            <aside x-show="aktif" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
                   class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:inset-y-0 sm:left-auto sm:right-0 sm:max-h-none sm:w-[26rem] sm:rounded-none">
                <template x-if="aktif">
                    <div>
                        <div class="sticky top-0 flex items-start justify-between gap-3 border-b border-slate-100 bg-white px-5 py-4">
                            <div>
                                <p class="text-2xl font-bold text-slate-900" x-text="aktif.kode"></p>
                                <p class="text-sm text-slate-500" x-text="aktif.alamat"></p>
                                <span class="badge badge-slate mt-1" x-text="aktif.status"></span>
                            </div>
                            <button type="button" class="btn btn-ghost p-2" @click="tutup()" aria-label="Tutup"><x-icon name="x" /></button>
                        </div>

                        <div class="space-y-4 p-5">
                            <template x-if="aktif.privat">
                                <p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500"><span x-text="aktif.jumlahKk"></span> keluarga terdata. Detail penghuni hanya dapat dilihat pengurus.</p>
                            </template>
                            <template x-if="!aktif.privat && aktif.keluarga.length === 0">
                                <p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">Belum ada keluarga yang terdata di rumah ini.</p>
                            </template>

                            <template x-for="(kk, i) in aktif.keluarga" :key="i">
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="flex items-center gap-3">
                                        <template x-if="kk.foto"><img :src="kk.foto" :alt="'Foto keluarga ' + kk.nama" class="size-16 rounded-xl object-cover ring-1 ring-slate-200"></template>
                                        <template x-if="!kk.foto"><span class="inline-flex size-16 items-center justify-center rounded-xl bg-brand-100 text-lg font-semibold text-brand-800" x-text="inisial(kk.nama)"></span></template>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900" x-text="kk.nama"></p>
                                            <p class="text-xs text-slate-500" x-text="kk.status"></p>
                                            <template x-if="kk.hp"><a :href="'tel:' + kk.hp" class="text-xs text-brand-700" x-text="kk.hp"></a></template>
                                            <template x-if="kk.iuran">
                                                <span class="badge mt-1" :class="kk.iuran === 'Lunas' ? 'badge-green' : (kk.iuran === 'Belum bayar' ? 'badge-red' : 'badge-slate')" x-text="'Iuran: ' + kk.iuran"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <ul class="mt-3 grid grid-cols-1 gap-2 min-[400px]:grid-cols-2">
                                        <template x-for="(a, j) in kk.anggota" :key="j">
                                            <li class="flex items-center gap-2 rounded-lg bg-slate-50 p-2">
                                                <template x-if="a.foto"><img :src="a.foto" :alt="a.nama" class="size-9 rounded-full object-cover" @click="zoom = a.foto"></template>
                                                <template x-if="!a.foto"><span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800" x-text="a.inisial"></span></template>
                                                <div class="min-w-0 flex-1 leading-tight">
                                                    <p class="truncate text-xs font-medium text-slate-800" x-text="a.nama"></p>
                                                    <p class="text-[11px] text-slate-500" x-text="a.hubungan"></p>
                                                </div>
                                                <template x-if="a.urlEdit"><a :href="a.urlEdit" class="ml-auto shrink-0 rounded p-1 text-slate-400 hover:bg-white hover:text-brand-700" title="Ubah data anggota"><x-icon name="pencil" class="size-3.5" /></a></template>
                                            </li>
                                        </template>
                                    </ul>
                                    <template x-if="kk.urlEdit">
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <a :href="kk.urlEdit" class="btn btn-primary btn-sm"><x-icon name="pencil" class="size-4" /> Ubah data KK</a>
                                            <a :href="kk.urlAnggota" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Anggota</a>
                                        </div>
                                    </template>
                                    <template x-if="kk.url"><a :href="kk.url" class="btn btn-ghost btn-sm mt-2 w-full">Lihat detail lengkap →</a></template>
                                </div>
                            </template>

                            <div class="flex gap-2" x-show="aktif.edit || aktif.tambahKk">
                                <template x-if="aktif.tambahKk"><a :href="aktif.tambahKk" class="btn btn-primary btn-sm flex-1"><x-icon name="plus" class="size-4" /> Tambah KK</a></template>
                                <template x-if="aktif.edit"><a :href="aktif.edit" class="btn btn-secondary btn-sm flex-1"><x-icon name="pencil" class="size-4" /> Ubah rumah</a></template>
                            </div>
                        </div>
                    </div>
                </template>
            </aside>
        </div>

        {{-- Zoom foto --}}
        <div x-cloak x-show="zoom" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="zoom = null">
            <img :src="zoom" alt="" class="max-h-full max-w-full rounded-xl">
        </div>
    </div>

    @push('scripts')
        <script>
            function denah(data, peta, tampilan, privasi) {
                return {
                    data, aktif: null, zoom: null, sorot: '', map: null, marker: {},
                    init() {
                        if (tampilan === 'peta' && this.$refs.peta && window.L) this.siapkanPeta();
                        this.$watch('sorot', () => this.sorotPeta());
                        const m = location.hash.match(/^#rumah-(\d+)$/);
                        if (m && this.data[m[1]]) {
                            document.getElementById('rumah-' + m[1])?.scrollIntoView({ block: 'center' });
                            this.buka(+m[1]);
                        }
                    },
                    async siapkanPeta() {
                        this.map = await SiwargaPeta.buat(this.$refs.peta, peta);
                        const titik = [];
                        for (const [id, r] of Object.entries(this.data)) {
                            if (r.lat === null || r.lng === null) continue;
                            const mk = L.marker([r.lat, r.lng], { icon: SiwargaPeta.ikon(r.kat, r.kode, { warnaRt: r.warnaRt }), title: r.kode + (r.namaSingkat ? ' · ' + r.namaSingkat : '') })
                                .addTo(this.map)
                                .on('click', () => this.buka(+id));
                            this.marker[id] = mk;
                            titik.push([r.lat, r.lng]);
                        }
                        if (titik.length) this.map.fitBounds(titik, { padding: [30, 30], maxZoom: 19 });
                        const m = location.hash.match(/^#rumah-(\d+)$/);
                        if (m && this.marker[m[1]]) this.map.setView(this.marker[m[1]].getLatLng(), 20);
                    },
                    sorotPeta() {
                        const q = this.sorot.trim().toLowerCase();
                        for (const [id, mk] of Object.entries(this.marker)) {
                            const el = mk.getElement();
                            if (!el) continue;
                            const kena = this.cocok(+id);
                            el.classList.toggle('sw-pin-sorot', kena);
                            el.classList.toggle('sw-pin-redup', q.length > 1 && !kena);
                            mk.setZIndexOffset(kena ? 1000 : 0);
                        }
                        const pertama = Object.keys(this.marker).find(id => this.cocok(+id));
                        if (pertama && this.map) this.map.panTo(this.marker[pertama].getLatLng());
                    },
                    buka(id) { this.aktif = this.data[id] || null },
                    tutup() { this.aktif = null; this.zoom = null },
                    inisial(n) { return (n || '?').split(/\s+/).slice(0, 2).map(k => k[0]).join('').toUpperCase() },
                    cocok(id) {
                        const q = this.sorot.trim().toLowerCase();
                        if (q.length < 2) return false;
                        const r = this.data[id];
                        if (!r) return false;
                        if (r.kode.toLowerCase() === q) return true;
                        return r.keluarga.some(k => k.nama.toLowerCase().includes(q) || k.anggota.some(a => a.nama.toLowerCase().includes(q)));
                    },
                }
            }
        </script>
    @endpush
@endsection
