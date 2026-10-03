@extends('layouts.app')
@section('title', 'Atur Titik Rumah')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/peta.js') }}?v={{ @filemtime(public_path('js/peta.js')) }}"></script>
@endpush

@section('content')
    <div x-data="editorPeta(@js([
            'rumahs' => $rumahs,
            'bloks' => $bloks,
            'peta' => $peta,
            'admin' => auth()->user()->isAdmin(),
            'url' => [
                'lokasi' => route('rumah.lokasi', ['rumah' => '__ID__']),
                'tambah' => route('rumah.peta.store'),
                'awal' => auth()->user()->isAdmin() ? route('peta.awal') : null,
            ],
        ]))" @keydown.escape.window="batal()">

        <x-page-header judul="Atur Titik Rumah" sub="Tandai posisi setiap rumah di peta satelit." :kembali="route('denah', ['tampilan' => 'peta'])">
            <a href="{{ route('wilayah') }}" class="btn btn-secondary"><x-icon name="building" class="size-4" /> RT, Blok & Rumah</a>
        </x-page-header>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Peta --}}
            <div class="lg:col-span-2">
                <div class="card isolate overflow-hidden">
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2 text-sm">
                        <template x-if="dipilih">
                            <div class="flex flex-1 items-center gap-2 rounded-lg bg-yellow-50 px-3 py-1.5 text-yellow-900 ring-1 ring-yellow-300">
                                <span>Klik atap rumah <b x-text="rumahDipilih()?.kode"></b> di peta<span x-show="rumahDipilih()?.lat !== null"> (atau geser titiknya)</span>.</span>
                                <button type="button" class="ml-auto text-yellow-800 underline" @click="batal()">Batal</button>
                            </div>
                        </template>
                        <template x-if="!dipilih">
                            <p class="flex-1 text-slate-600">Pilih rumah di daftar lalu klik atapnya di peta, <b>atau</b> klik langsung di peta untuk membuat rumah baru.</p>
                        </template>
                    </div>
                    <div class="relative">
                        <div x-ref="peta" class="h-[72vh] min-h-[440px] w-full"></div>
                        <div x-cloak x-show="menyeret" class="pointer-events-none absolute inset-0 z-[500] rounded-b-2xl ring-4 ring-inset transition"
                             :class="diAtasPeta ? 'ring-amber-400 bg-amber-300/10' : 'ring-brand-400/60'">
                            <p class="absolute left-1/2 top-3 -translate-x-1/2 rounded-full bg-slate-900/85 px-3 py-1.5 text-xs font-medium text-white shadow"
                               x-text="diAtasPeta ? 'Lepaskan di atap rumah ' + (seretKode || '') : 'Bawa ke peta lalu lepaskan di atap rumahnya'"></p>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Tip: perbesar peta sampai atap rumah terlihat jelas. Titik rumah bisa digeser (drag) untuk merapikan posisi. Rumah di daftar juga bisa <b>diseret langsung ke peta</b>.</p>
            </div>

            {{-- Panel samping --}}
            <div class="space-y-4">
                <div x-show="pesan" x-cloak class="rounded-xl px-4 py-3 text-sm" :class="pesanGagal ? 'bg-rose-50 text-rose-800 ring-1 ring-rose-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200'" x-text="pesan"></div>

                {{-- Rumah terpilih: data keluarga --}}
                <template x-if="rumahDipilih()">
                    <div class="card card-body space-y-3 ring-2 ring-yellow-300">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-lg font-bold text-slate-900" x-text="rumahDipilih().kode"></p>
                                <p class="text-xs text-slate-500" x-text="'RT ' + rumahDipilih().rt + ' · ' + (rumahDipilih().lat !== null ? 'sudah ada titik' : 'belum ada titik')"></p>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" @click="batal()" title="Tutup"><x-icon name="x" class="size-4" /></button>
                        </div>
                        <template x-if="!rumahDipilih().keluarga || rumahDipilih().keluarga.length === 0">
                            <p class="rounded-lg bg-slate-50 p-3 text-center text-sm text-slate-500">Belum ada keluarga di rumah ini.</p>
                        </template>
                        <template x-for="kk in (rumahDipilih().keluarga || [])" :key="kk.id">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex items-center gap-3">
                                    <template x-if="kk.foto"><img :src="kk.foto" alt="" class="size-10 rounded-lg object-cover"></template>
                                    <template x-if="!kk.foto"><span class="inline-flex size-10 items-center justify-center rounded-lg bg-brand-100 text-sm font-semibold text-brand-800" x-text="kk.nama.split(' ').slice(0,2).map(k => k[0]).join('').toUpperCase()"></span></template>
                                    <a :href="kk.urlShow" class="min-w-0 flex-1 truncate font-medium text-slate-900 hover:underline" x-text="kk.nama"></a>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <a :href="kk.urlEdit" class="btn btn-primary btn-sm"><x-icon name="pencil" class="size-4" /> Ubah data KK</a>
                                    <a :href="kk.urlAnggota" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Anggota</a>
                                </div>
                            </div>
                        </template>
                        <div class="grid grid-cols-2 gap-2">
                            <a :href="rumahDipilih().urlKk" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Tambah KK</a>
                            <a :href="rumahDipilih().urlBlok" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Ubah rumah</a>
                        </div>
                    </div>
                </template>

                <div class="card card-body space-y-3">
                    <h2 class="card-title">Cari lokasi</h2>
                    <form class="flex gap-2" @submit.prevent="cari()">
                        <input x-model="q" type="search" placeholder="Nama perumahan, jalan, kelurahan…" class="input">
                        <button class="btn btn-secondary" :disabled="mencari"><x-icon name="search" class="size-4" /></button>
                    </form>
                    <ul class="max-h-40 space-y-1 overflow-y-auto text-sm" x-show="hasilCari.length">
                        <template x-for="(h, i) in hasilCari" :key="i">
                            <li><button type="button" class="w-full rounded-lg px-2 py-1.5 text-left hover:bg-slate-50" @click="ke(h)" x-text="h.nama"></button></li>
                        </template>
                    </ul>
                    <template x-if="o.admin">
                        <button type="button" class="btn btn-secondary btn-sm w-full" @click="simpanAwal()">Jadikan tampilan ini posisi awal peta</button>
                    </template>
                </div>

                <div class="card card-body space-y-2">
                    <h2 class="card-title">Impor bangunan dari OpenStreetMap</h2>
                    <p class="text-xs text-slate-500">Mengambil bangunan yang sudah tergambar di OpenStreetMap pada area peta yang sedang tampil. Titik abu-abu bisa diklik untuk dijadikan rumah.</p>
                    <button type="button" class="btn btn-secondary btn-sm w-full" @click="imporOsm()" :disabled="mengimpor">
                        <x-icon name="download" class="size-4" /> <span x-text="mengimpor ? 'Mengambil…' : 'Impor bangunan di area ini'"></span>
                    </button>
                    <p class="text-xs text-slate-600" x-show="jumlahKandidat > 0"><span x-text="jumlahKandidat"></span> bangunan kandidat tampil. <button type="button" class="text-rose-600 underline" @click="hapusKandidat()">Hapus</button></p>
                </div>

                <div class="card overflow-hidden">
                    <div class="space-y-2 border-b border-slate-100 p-3">
                        <div class="flex items-center justify-between">
                            <h2 class="card-title">Rumah</h2>
                            <span class="text-xs text-slate-500"><span x-text="jumlahBertitik()"></span>/<span x-text="rumahs.length"></span> sudah ada titik</span>
                        </div>
                        <input x-model="filter" type="search" placeholder="Cari kode (A-12) atau nama…" class="input">
                        <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" x-model="hanyaBelum" class="rounded border-slate-300 text-brand-700"> Hanya yang belum ada titik</label>
                        <p class="flex items-start gap-1.5 rounded-lg bg-brand-50 px-2.5 py-2 text-xs text-brand-900">
                            <x-icon name="grip" class="mt-0.5 size-3.5 shrink-0 stroke-[3]" />
                            <span>Seret rumah dari daftar ini lalu lepaskan di atap rumahnya di peta.<span class="lg:hidden"> Di HP: tekan-tahan sebentar, lalu geser.</span></span>
                        </p>
                    </div>
                    <ul class="max-h-[46vh] divide-y divide-slate-100 overflow-y-auto">
                        <template x-for="r in daftar()" :key="r.id">
                            <li class="sw-seret flex cursor-grab items-center gap-2 px-3 py-2 text-sm" :class="{ 'bg-yellow-50': dipilih === r.id, 'opacity-40': seretId === r.id }"
                                @pointerdown="mulaiSeret($event, r.id)" @contextmenu.prevent>
                                <x-icon name="grip" class="size-4 shrink-0 stroke-[3] text-slate-300" />
                                <span class="size-2.5 shrink-0 rounded-full" :class="r.lat !== null ? 'bg-emerald-500' : 'bg-slate-300'" :title="r.lat !== null ? 'Sudah ada titik' : 'Belum ada titik'"></span>
                                <button type="button" class="min-w-0 flex-1 text-left" @click="if (!barusSeret()) pilih(r.id)">
                                    <span class="font-semibold text-slate-900" x-text="r.kode"></span>
                                    <span class="text-xs text-slate-400" x-text="'RT ' + r.rt"></span>
                                    <span class="block truncate text-xs text-slate-500" x-text="r.kk || 'Belum ada KK'"></span>
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm" x-show="r.lat !== null" @click="fokus(r)" title="Lihat di peta"><x-icon name="map" class="size-4" /></button>
                                <button type="button" class="btn btn-ghost btn-sm text-rose-600" x-show="r.lat !== null" @click="hapusTitik(r)" title="Hapus titik"><x-icon name="x" class="size-4" /></button>
                            </li>
                        </template>
                        <li x-show="daftar().length === 0" class="px-3 py-6 text-center text-sm text-slate-500">Tidak ada rumah.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Form rumah baru dari titik peta --}}
        <div x-cloak x-show="baru" class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" @click.self="baru = null">
            <form class="card card-body w-full max-w-sm space-y-3" @submit.prevent="simpanBaru()">
                <h2 class="card-title">Rumah baru di titik ini</h2>
                <template x-if="o.bloks.length === 0">
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Belum ada blok. Buat RT dan blok dahulu di menu RT, Blok & Rumah.</p>
                </template>
                <div>
                    <label class="label">Blok</label>
                    <select x-model.number="formBaru.blok_id" class="input" @change="saranNomor()" required>
                        <template x-for="b in o.bloks" :key="b.id">
                            <option :value="b.id" x-text="b.label"></option>
                        </template>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Nomor rumah</label>
                        <input x-model="formBaru.nomor" x-ref="nomorBaru" class="input" required maxlength="10">
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select x-model="formBaru.status_hunian" class="input">
                            @foreach ($statusList as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-xs text-rose-600" x-show="galatBaru" x-text="galatBaru"></p>
                <div class="flex gap-2">
                    <button class="btn btn-primary flex-1" :disabled="menyimpan || o.bloks.length === 0">Simpan rumah</button>
                    <button type="button" class="btn btn-ghost" @click="baru = null">Batal</button>
                </div>
                <p class="text-xs text-slate-500">Rumah yang sudah ada di daftar? Tutup form ini, pilih rumahnya di daftar, lalu klik di peta.</p>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function editorPeta(o) {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                // status seret (berisi elemen DOM) disimpan di luar state Alpine
                let S = null;
                let waktuSeret = 0;

                return {
                    o, rumahs: o.rumahs, map: null, marker: {}, kandidat: null, jumlahKandidat: 0,
                    dipilih: null, filter: '', hanyaBelum: false, q: '', hasilCari: [], mencari: false, mengimpor: false,
                    baru: null, formBaru: { blok_id: null, nomor: '', status_hunian: 'dihuni' }, galatBaru: '', menyimpan: false,
                    pesan: '', pesanGagal: false,
                    menyeret: false, diAtasPeta: false, seretId: null, seretKode: '',

                    async init() {
                        // cegah halaman menggulir saat rumah sedang diseret di layar sentuh
                        window.addEventListener('touchmove', e => { if (S?.aktif) e.preventDefault() }, { passive: false });
                        this.map = await SiwargaPeta.buat(this.$refs.peta, o.peta);
                        this.kandidat = L.layerGroup().addTo(this.map);
                        const titik = [];
                        for (const r of this.rumahs) {
                            if (r.lat !== null) { this.pasangMarker(r); titik.push([r.lat, r.lng]); }
                        }
                        if (titik.length) this.map.fitBounds(titik, { padding: [30, 30], maxZoom: 19 });
                        this.map.on('click', e => this.klikPeta(e.latlng));
                        const m = location.hash.match(/^#rumah-(\d+)$/);
                        if (m && this.rumahs.some(r => r.id === +m[1])) this.pilih(+m[1]);
                    },

                    // ---------- daftar ----------
                    daftar() {
                        const q = this.filter.trim().toLowerCase();
                        return this.rumahs.filter(r =>
                            (!this.hanyaBelum || r.lat === null) &&
                            (!q || r.kode.toLowerCase().includes(q) || (r.kk || '').toLowerCase().includes(q)));
                    },
                    jumlahBertitik() { return this.rumahs.filter(r => r.lat !== null).length },
                    rumahDipilih() { return this.rumahs.find(r => r.id === this.dipilih) },
                    pilih(id) {
                        this.dipilih = this.dipilih === id ? null : id;
                        this.segarkanIkon();
                        const r = this.rumahDipilih();
                        if (r && r.lat !== null) this.map.panTo([r.lat, r.lng]);
                    },
                    batal() { this.dipilih = null; this.baru = null; this.segarkanIkon(); },
                    fokus(r) { this.map.setView([r.lat, r.lng], Math.max(this.map.getZoom(), 19)); },

                    // ---------- seret rumah dari daftar ke peta ----------
                    barusSeret() { return Date.now() - waktuSeret < 150 },
                    mulaiSeret(e, id) {
                        if (e.target.closest('button:not(.min-w-0), a, input') || (e.pointerType === 'mouse' && e.button !== 0)) return;
                        S = { id, x: e.clientX, y: e.clientY, x0: e.clientX, y0: e.clientY, aktif: false, sentuh: e.pointerType !== 'mouse', daftar: e.currentTarget.closest('ul') };
                        if (S.sentuh) S.timer = setTimeout(() => S && this.aktifkanSeret(), 300);
                        const gerak = ev => this.geserSeret(ev);
                        const lepas = () => this.lepasSeret();
                        const batal = () => { if (!S?.aktif) this.akhiriSeret() };
                        S.lepasPendengar = () => {
                            window.removeEventListener('pointermove', gerak);
                            window.removeEventListener('pointerup', lepas);
                            window.removeEventListener('pointercancel', batal);
                        };
                        window.addEventListener('pointermove', gerak);
                        window.addEventListener('pointerup', lepas);
                        window.addEventListener('pointercancel', batal);
                    },
                    aktifkanSeret() {
                        const r = this.rumahs.find(x => x.id === S.id);
                        if (!r) return this.akhiriSeret();
                        // penanda yang ujung bawahnya tepat di bawah jari/kursor
                        const g = document.createElement('div');
                        g.setAttribute('x-ignore', '');
                        g.className = 'sw-ghost-pin';
                        g.innerHTML = '<span></span>';
                        g.firstChild.textContent = r.kode;
                        g.firstChild.style.background = r.warna || '#0f766e';
                        document.body.appendChild(g);
                        Object.assign(S, { aktif: true, ghost: g });
                        this.menyeret = true; this.seretId = r.id; this.seretKode = r.kode;
                        document.body.classList.add('sw-menyeret');
                        navigator.vibrate?.(25);
                        this.posisiGhost();
                        const putar = () => {
                            if (!S?.aktif) return;
                            // gulir otomatis di tepi layar (di HP peta ada di atas daftar)
                            const tepi = 70, h = window.innerHeight;
                            if (S.y < tepi) window.scrollBy(0, -Math.ceil((tepi - S.y) / 4));
                            else if (S.y > h - tepi) window.scrollBy(0, Math.ceil((S.y - (h - tepi)) / 4));
                            this.cekDiAtasPeta();
                            S.raf = requestAnimationFrame(putar);
                        };
                        S.raf = requestAnimationFrame(putar);
                    },
                    geserSeret(e) {
                        if (!S) return;
                        S.x = e.clientX; S.y = e.clientY;
                        if (!S.aktif) {
                            const jauh = Math.hypot(S.x - S.x0, S.y - S.y0);
                            if (S.sentuh && jauh > 10) return this.akhiriSeret(); // sedang menggulir daftar
                            if (!S.sentuh && jauh > 5) this.aktifkanSeret();
                            if (!S?.aktif) return;
                        }
                        this.posisiGhost();
                        this.cekDiAtasPeta();
                    },
                    posisiGhost() {
                        S.ghost.style.left = S.x + 'px';
                        S.ghost.style.top = S.y + 'px';
                    },
                    cekDiAtasPeta() {
                        const b = this.$refs.peta.getBoundingClientRect();
                        const di = S.x >= b.left && S.x <= b.right && S.y >= b.top && S.y <= b.bottom;
                        if (di !== this.diAtasPeta) this.diAtasPeta = di;
                    },
                    lepasSeret() {
                        if (!S) return;
                        const { aktif, id, x, y } = S;
                        const diPeta = aktif && this.diAtasPeta;
                        this.akhiriSeret();
                        if (!aktif) return;
                        waktuSeret = Date.now();
                        const r = this.rumahs.find(x => x.id === id);
                        if (!r) return;
                        if (!diPeta) { this.kabar('Lepaskan rumah di dalam peta untuk menandai lokasinya.', true); return; }
                        const p = this.map.mouseEventToLatLng({ clientX: x, clientY: y });
                        if (this.dipilih === id) this.dipilih = null;
                        this.simpanLokasi(r, p.lat, p.lng, true);
                    },
                    akhiriSeret() {
                        if (!S) return;
                        clearTimeout(S.timer);
                        cancelAnimationFrame(S.raf);
                        S.lepasPendengar();
                        S.ghost?.remove();
                        document.body.classList.remove('sw-menyeret');
                        this.menyeret = false; this.diAtasPeta = false; this.seretId = null;
                        S = null;
                    },

                    // ---------- marker ----------
                    pasangMarker(r) {
                        if (this.marker[r.id]) this.map.removeLayer(this.marker[r.id]);
                        const kat = r.kk ? (r.status === 'kontrakan' ? 'kontrakan' : 'terisi') : (r.status === 'kosong' ? 'kosong' : (r.status === 'usaha' ? 'usaha' : 'belum_didata'));
                        const mk = L.marker([r.lat, r.lng], {
                            draggable: true,
                            icon: SiwargaPeta.ikon(kat, r.kode, { warnaRt: r.warna, dipilih: this.dipilih === r.id }),
                            title: r.kode + (r.kk ? ' · ' + r.kk : ''),
                        }).addTo(this.map);
                        mk.on('click', () => this.pilih(r.id));
                        mk.on('dragend', () => { const p = mk.getLatLng(); this.simpanLokasi(r, p.lat, p.lng); });
                        mk.bindTooltip(SiwargaPeta.esc(r.kode + (r.kk ? ' · ' + r.kk : '')), { direction: 'top', offset: [0, -10] });
                        this.marker[r.id] = mk;
                    },
                    segarkanIkon() {
                        for (const r of this.rumahs) if (r.lat !== null) this.pasangMarker(r);
                    },

                    // ---------- aksi peta ----------
                    klikPeta(latlng, nomorSaran = '') {
                        if (this.dipilih) {
                            const r = this.rumahDipilih();
                            this.simpanLokasi(r, latlng.lat, latlng.lng, true);
                            return;
                        }
                        this.baru = latlng;
                        this.galatBaru = '';
                        this.formBaru.blok_id = this.formBaru.blok_id || (o.bloks[0] && o.bloks[0].id);
                        this.formBaru.nomor = nomorSaran;
                        if (!nomorSaran) this.saranNomor();
                        this.$nextTick(() => this.$refs.nomorBaru && this.$refs.nomorBaru.focus());
                    },
                    saranNomor() {
                        const nomor = this.rumahs.filter(r => r.blok_id === this.formBaru.blok_id).map(r => parseInt(r.nomor, 10)).filter(n => !isNaN(n));
                        this.formBaru.nomor = String(nomor.length ? Math.max(...nomor) + 1 : 1);
                    },

                    async kirim(url, method, body) {
                        const r = await fetch(url, {
                            method,
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify(body),
                        });
                        const j = await r.json().catch(() => ({}));
                        if (!r.ok) {
                            const galat = j.errors ? Object.values(j.errors).flat().join(' ') : (j.message || ('Gagal (' + r.status + ')'));
                            throw new Error(galat);
                        }
                        return j;
                    },
                    kabar(teks, gagal = false) {
                        this.pesan = teks; this.pesanGagal = gagal;
                        clearTimeout(this._t); this._t = setTimeout(() => this.pesan = '', 4000);
                    },

                    async simpanLokasi(r, lat, lng, lepasPilihan = false) {
                        try {
                            const j = await this.kirim(o.url.lokasi.replace('__ID__', r.id), 'PUT', { lat, lng });
                            Object.assign(r, j.rumah);
                            if (lepasPilihan) this.dipilih = null;
                            this.pasangMarker(r);
                            if (lepasPilihan) this.segarkanIkon();
                            this.kabar('Titik ' + r.kode + ' disimpan.');
                            this.hapusKandidatDekat(lat, lng);
                        } catch (e) {
                            this.kabar(e.message, true);
                            if (r.lat !== null) this.pasangMarker(r);
                        }
                    },
                    async hapusTitik(r) {
                        try {
                            await this.kirim(o.url.lokasi.replace('__ID__', r.id), 'PUT', { lat: null, lng: null });
                            if (this.marker[r.id]) { this.map.removeLayer(this.marker[r.id]); delete this.marker[r.id]; }
                            r.lat = null; r.lng = null;
                            this.kabar('Titik ' + r.kode + ' dihapus.');
                        } catch (e) { this.kabar(e.message, true); }
                    },
                    async simpanBaru() {
                        this.menyimpan = true; this.galatBaru = '';
                        try {
                            const j = await this.kirim(o.url.tambah, 'POST', { ...this.formBaru, lat: this.baru.lat, lng: this.baru.lng });
                            this.rumahs.push(j.rumah);
                            this.pasangMarker(j.rumah);
                            this.hapusKandidatDekat(this.baru.lat, this.baru.lng);
                            this.kabar('Rumah ' + j.rumah.kode + ' dibuat.');
                            this.baru = null;
                        } catch (e) {
                            this.galatBaru = e.message;
                        } finally { this.menyimpan = false; }
                    },

                    // ---------- cari & posisi awal ----------
                    async cari() {
                        if (this.q.trim().length < 3) return;
                        this.mencari = true;
                        try {
                            this.hasilCari = await SiwargaPeta.cariLokasi(this.q.trim());
                            if (!this.hasilCari.length) this.kabar('Lokasi tidak ditemukan.', true);
                        } catch (e) { this.kabar(e.message, true); }
                        finally { this.mencari = false; }
                    },
                    ke(h) {
                        if (h.bbox) this.map.fitBounds([[+h.bbox[0], +h.bbox[2]], [+h.bbox[1], +h.bbox[3]]], { maxZoom: 18 });
                        else this.map.setView([h.lat, h.lng], 18);
                        this.hasilCari = [];
                    },
                    async simpanAwal() {
                        const c = this.map.getCenter();
                        try {
                            await this.kirim(o.url.awal, 'PUT', { lat: c.lat, lng: c.lng, zoom: this.map.getZoom() });
                            this.kabar('Posisi awal peta disimpan.');
                        } catch (e) { this.kabar(e.message, true); }
                    },

                    // ---------- impor OpenStreetMap ----------
                    async imporOsm() {
                        if (this.map.getZoom() < 16) { this.kabar('Perbesar peta dulu (sampai terlihat per rumah) sebelum mengimpor.', true); return; }
                        this.mengimpor = true;
                        try {
                            const bangunan = await SiwargaPeta.bangunanOsm(this.map.getBounds());
                            this.kandidat.clearLayers();
                            let n = 0;
                            for (const b of bangunan) {
                                if (this.dekatRumah(b.lat, b.lng)) continue;
                                const c = L.circleMarker([b.lat, b.lng], { bubblingMouseEvents: false, radius: 6, color: '#334155', weight: 1.5, fillColor: '#e2e8f0', fillOpacity: .9 })
                                    .bindTooltip(b.nomor ? 'Bangunan No. ' + SiwargaPeta.esc(b.nomor) : 'Bangunan (OSM)', { direction: 'top' })
                                    .on('click', ev => { L.DomEvent.stopPropagation(ev); this.klikPeta(ev.latlng, b.nomor); });
                                c.addTo(this.kandidat); n++;
                            }
                            this.jumlahKandidat = n;
                            this.kabar(n ? n + ' bangunan ditemukan. Klik titik abu-abu untuk menjadikannya rumah.' : 'Tidak ada bangunan baru di OpenStreetMap untuk area ini. Tandai manual dengan klik di peta.', !n);
                        } catch (e) { this.kabar(e.message, true); }
                        finally { this.mengimpor = false; }
                    },
                    dekatRumah(lat, lng) {
                        return this.rumahs.some(r => r.lat !== null && this.map.distance([lat, lng], [r.lat, r.lng]) < 4);
                    },
                    hapusKandidatDekat(lat, lng) {
                        this.kandidat.eachLayer(l => { if (this.map.distance(l.getLatLng(), [lat, lng]) < 6) this.kandidat.removeLayer(l); });
                        this.jumlahKandidat = this.kandidat.getLayers().length;
                    },
                    hapusKandidat() { this.kandidat.clearLayers(); this.jumlahKandidat = 0; },
                }
            }
        </script>
    @endpush
@endsection
