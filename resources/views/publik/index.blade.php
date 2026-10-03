@extends('layouts.publik')
@section('title', 'Peta & Info Warga')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/peta.js') }}?v={{ @filemtime(public_path('js/peta.js')) }}"></script>
@endpush

@section('content')
    @php
        $namaRw = pengaturan('nama_rw', config('siwarga.aplikasi'));
        $warnaRt = $rts->mapWithKeys(fn ($rt) => [$rt->id => $rt->warna ?: '#64748b']);
    @endphp

    {{-- Hero --}}
    <section class="bg-gradient-to-br from-brand-800 via-brand-700 to-teal-600 text-white">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <h1 class="text-2xl font-bold tracking-tight sm:text-4xl">{{ $namaRw }}</h1>
            @if (wilayah())
                <p class="mt-1 text-sm font-medium text-white/90 sm:text-base">{{ wilayah() }}</p>
            @endif
            <dl class="mt-8 grid max-w-lg grid-cols-3 gap-3 sm:mt-10">
                @foreach ([['RT', $statistik['rt']], ['Rumah', $statistik['rumah']], ['Keluarga', $statistik['kk']]] as [$l, $n])
                    <div class="rounded-xl bg-white/10 px-3 py-2.5 ring-1 ring-white/20">
                        <dd class="text-xl font-bold sm:text-2xl">{{ number_format($n, 0, ',', '.') }}</dd>
                        <dt class="text-xs text-brand-100">{{ $l }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- Peta wilayah --}}
    <section id="peta" class="mx-auto max-w-6xl scroll-mt-28 md:scroll-mt-16 px-4 py-8 sm:px-6"
             x-data="petaUmum({{ Js::from($titik) }}, {{ Js::from($peta) }}, {{ Js::from(['login' => route('login'), 'dasbor' => auth()->check() ? route('denah') : null]) }})">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Peta wilayah</h2>
                <p class="text-sm text-slate-500">Setiap rumah warga ditandai dengan warna RT-nya. Klik rumah yang diinginkan untuk mendapatkan daftar yang tinggal di rumah tersebut.</p>
            </div>
        </div>

        {{-- Legenda & filter RT --}}
        <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
            @foreach ($rts as $rt)
                <button type="button" @click="toggle({{ $rt->id }})"
                        class="flex shrink-0 items-center gap-2 rounded-full border bg-white py-1.5 pl-1.5 pr-3 text-sm shadow-sm transition"
                        :class="mati.includes({{ $rt->id }}) ? 'border-slate-200 opacity-50' : 'border-slate-300'">
                    <span class="size-5 rounded-full ring-2 ring-white" style="background: {{ $warnaRt[$rt->id] }}"></span>
                    <span class="font-semibold text-slate-800">RT {{ $rt->nomor }}</span>
                    <span class="text-xs text-slate-500">{{ $rt->rumahs_count }} rumah</span>
                </button>
            @endforeach
        </div>

        @if ($titik->isNotEmpty())
            <div class="card overflow-hidden">
                <div x-ref="peta" class="h-[60vh] min-h-[360px] w-full bg-slate-100 sm:h-[520px]"></div>
            </div>
            <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><x-icon name="lock" class="size-3.5" /> Nama dan data penghuni tidak ditampilkan di halaman umum.</p>
        @elseif ($bloks->isNotEmpty())
            {{-- Cadangan: denah blok sederhana (belum ada titik rumah di peta) --}}
            <div class="space-y-4" x-data="{ pilih: null }">
                @foreach ($bloks->groupBy('rt_id') as $rtId => $grup)
                    <div class="card card-body" x-show="!mati.includes({{ (int) $rtId }})">
                        <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <span class="size-3 rounded-full" style="background: {{ $warnaRt[$rtId] ?? '#64748b' }}"></span> RT {{ $grup->first()->rt->nomor }}
                        </p>
                        <div class="space-y-3">
                            @foreach ($grup as $blok)
                                <div>
                                    <p class="mb-1 text-xs font-medium text-slate-500">Blok {{ $blok->nama }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($blok->rumahs->sortBy(['baris', 'kolom']) as $rumah)
                                            <button type="button" @click="pilih = {{ Js::from(['k' => $blok->nama.'-'.$rumah->nomor, 'rt' => $grup->first()->rt->nomor]) }}"
                                                    class="flex h-8 min-w-9 items-center justify-center rounded-md px-1.5 text-xs font-semibold text-white shadow-sm ring-1 ring-black/10"
                                                    style="background: {{ $warnaRt[$rtId] ?? '#64748b' }}">{{ $rumah->nomor }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div x-cloak x-show="pilih" x-transition.opacity class="fixed inset-0 z-[1200] flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" @click.self="pilih = null" @keydown.escape.window="pilih = null">
                    <div class="w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-xl">
                        <p class="text-lg font-bold text-slate-900">Rumah <span x-text="pilih && pilih.k"></span></p>
                        <p class="text-sm text-slate-500">RT <span x-text="pilih && pilih.rt"></span></p>
                        <div class="mx-auto my-4 w-fit rounded-full bg-slate-100 p-3 text-slate-500"><x-icon name="lock" class="size-6" /></div>
                        <p class="text-sm text-slate-600">Data penghuni hanya dapat dilihat oleh warga terdaftar. Silakan masuk terlebih dahulu.</p>
                        <div class="mt-4 flex gap-2">
                            <button type="button" class="btn btn-secondary flex-1" @click="pilih = null">Tutup</button>
                            <a href="{{ auth()->check() ? route('denah') : route('login') }}" class="btn btn-primary flex-1">{{ auth()->check() ? 'Buka denah' : 'Masuk' }}</a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card"><x-empty icon="map" judul="Peta wilayah belum tersedia">Pengurus sedang menyiapkan denah wilayah.</x-empty></div>
        @endif

        {{-- Daftar RT --}}
        @if ($rts->isNotEmpty())
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rts as $rt)
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white" style="background: {{ $warnaRt[$rt->id] }}">{{ $rt->nomor }}</span>
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold text-slate-900">RT {{ $rt->nomor }}</p>
                            <p class="truncate text-slate-500">{{ $rt->nama_ketua ? 'Ketua: '.$rt->nama_ketua : 'Ketua RT belum diisi' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Galang dana --}}
    <section id="galang-dana" class="scroll-mt-28 md:scroll-mt-16 border-y border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Penggalangan dana</h2>
            <p class="mb-4 text-sm text-slate-500">Mari bergotong royong. Daftar donatur ditampilkan tanpa nominal.</p>

            @forelse ($donasi as $d)
                @if ($loop->first)<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">@endif
                @include('publik.partials.kartu-donasi', ['d' => $d])
                @if ($loop->last)</div>@endif
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300"><x-empty icon="heart" judul="Belum ada penggalangan dana">Program penggalangan dana akan tampil di sini.</x-empty></div>
            @endforelse
        </div>
    </section>

    {{-- Galeri kegiatan --}}
    @if ($galeri->isNotEmpty())
        <section id="galeri" class="mx-auto max-w-6xl scroll-mt-28 md:scroll-mt-16 px-4 pt-8 sm:px-6">
            <div class="mb-4 flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Galeri kegiatan</h2>
                    <p class="text-sm text-slate-500">Dokumentasi kegiatan warga.</p>
                </div>
                <a href="{{ route('publik.galeri') }}" class="shrink-0 text-sm font-medium text-brand-700 hover:underline">Lihat semua &rarr;</a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($galeri as $g)
                    @include('galeri.partials.kartu-album', ['g' => $g, 'url' => route('publik.galeri.show', $g)])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Informasi umum --}}
    <section id="info" class="mx-auto max-w-6xl scroll-mt-28 md:scroll-mt-16 px-4 py-8 sm:px-6">
        <h2 class="text-lg font-bold text-slate-900 sm:text-xl">Informasi umum</h2>
        <p class="mb-4 text-sm text-slate-500">Pengumuman dari pengurus RW &amp; RT.</p>

        @forelse ($pengumuman as $p)
            @if ($loop->first)<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">@endif
            <a href="{{ route('publik.pengumuman', $p) }}" class="card card-body block transition hover:border-brand-300 hover:shadow">
                <div class="mb-1 flex flex-wrap items-center gap-1.5">
                    @if ($p->penting)<span class="badge badge-red">Penting</span>@endif
                    <span class="badge badge-slate">{{ $p->rt ? 'RT '.$p->rt->nomor : 'RW' }}</span>
                    <span class="text-xs text-slate-500">{{ $p->terbit_pada->translatedFormat('d M Y') }}</span>
                </div>
                <p class="font-semibold text-slate-900">{{ $p->judul }}</p>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($p->isi), 160) }}</p>
            </a>
            @if ($loop->last)</div>@endif
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white"><x-empty icon="megaphone" judul="Belum ada informasi umum" /></div>
        @endforelse

        <div class="mt-6 flex flex-col items-start gap-3 rounded-2xl bg-brand-50 p-5 ring-1 ring-brand-100 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold text-brand-900">Warga {{ $namaRw }}?</p>
                <p class="text-sm text-brand-800">Masuk untuk melihat data keluarga, pengumuman khusus warga, dan membayar iuran via QRIS.</p>
            </div>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary shrink-0">{{ auth()->check() ? 'Buka dasbor' : 'Masuk sekarang' }}</a>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        function petaUmum(titik, peta, url) {
            return {
                mati: [],
                lapisan: {},
                async init() {
                    if (!this.$refs.peta || !titik.length) return;
                    const map = await SiwargaPeta.buat(this.$refs.peta, peta, { dasar: 'jalan' });
                    const esc = SiwargaPeta.esc;

                    const bulatan = [];
                    // kanvas dengan toleransi sentuh lebih lebar: titik kecil tetap mudah diketuk di HP
                    const kanvas = L.canvas({ padding: 0.5, tolerance: 10 });
                    titik.forEach(t => {
                        const grup = this.lapisan[t.rtId] || (this.lapisan[t.rtId] = L.layerGroup().addTo(map));
                        const tombol = url.dasbor
                            ? `<a href="${esc(url.dasbor)}" class="sw-pub-btn">Buka di denah</a>`
                            : `<a href="${esc(url.login)}" class="sw-pub-btn">Masuk untuk melihat</a>`;
                        const c = L.circleMarker([t.lat, t.lng], {
                            renderer: kanvas, radius: 8, color: '#ffffff', weight: 2, fillColor: t.w || '#64748b', fillOpacity: 0.95,
                        }).bindTooltip(esc(t.k), { direction: 'top', offset: [0, -6] })
                          .bindPopup(
                            `<div class="sw-pub-pop">
                                <div class="sw-pub-head"><span style="background:${esc(t.w)}"></span><b>Rumah ${esc(t.k)}</b> · RT ${esc(t.rt)}</div>
                                <p>&#128274; Data penghuni hanya dapat dilihat oleh warga terdaftar. Silakan masuk terlebih dahulu.</p>
                                ${tombol}
                            </div>`, { maxWidth: 240 })
                          .addTo(grup);
                        bulatan.push(c);
                    });

                    // Ukuran titik mengikuti zoom supaya rumah berdekatan tidak menumpuk
                    const ukur = () => {
                        const z = map.getZoom();
                        const r = z >= 19 ? 9 : z >= 18 ? 6 : z >= 17 ? 4 : 3;
                        bulatan.forEach(c => c.setRadius(r).setStyle({ weight: r > 4 ? 2 : 1 }));
                    };
                    map.on('zoomend', ukur);

                    const batas = L.latLngBounds(titik.map(t => [t.lat, t.lng]));
                    if (batas.isValid()) map.fitBounds(batas.pad(0.1), { maxZoom: 19 });
                    ukur();
                    this.map = map;
                },
                toggle(id) {
                    const i = this.mati.indexOf(id);
                    i >= 0 ? this.mati.splice(i, 1) : this.mati.push(id);
                    const g = this.lapisan[id];
                    if (g && this.map) i >= 0 ? g.addTo(this.map) : this.map.removeLayer(g);
                },
            };
        }
    </script>
    <style>
        .leaflet-interactive:focus { outline: none; }
        .sw-pub-pop { font-size: 13px; line-height: 1.45; color: #334155; }
        .sw-pub-head { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; color: #0f172a; }
        .sw-pub-head span { width: 12px; height: 12px; border-radius: 9999px; display: inline-block; }
        .sw-pub-pop p { margin: 0 0 8px !important; }
        .leaflet-container a.sw-pub-btn { display: block; text-align: center; background: #0f766e; color: #fff; border-radius: 8px; padding: 7px 10px; font-weight: 600; text-decoration: none; }
    </style>
@endpush
