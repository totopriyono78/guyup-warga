@extends('layouts.app')
@section('title', 'Blok '.$blok->nama)

@section('content')
    <x-page-header :judul="'Blok '.$blok->nama.' · RT '.$blok->rt->nomor" sub="Atur rumah dan posisinya di denah." :kembali="route('wilayah')">
        <a href="{{ route('peta.edit') }}" class="btn btn-secondary"><x-icon name="map" class="size-4" /> Atur titik di peta</a>
        <a href="{{ route('denah', ['rt' => $blok->rt_id, 'tampilan' => 'blok']) }}" class="btn btn-secondary">Lihat di denah</a>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="susunBlok(@js($susun), @js(route('blok.susun', $blok)))">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Susunan denah (drag & drop) --}}
            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="card-title">Susunan denah</h2>
                    <p class="text-xs text-slate-500">
                        <b>Geser</b> petak rumah ke posisi lain (atau ketuk petak lalu ketuk tujuan). Geser ke petak kosong = pindah posisi.
                        Geser ke rumah lain = pilih <b>tukar posisi</b> atau <b>tukar nomor</b>. Ketuk dua kali petak untuk mengganti nomor.
                    </p>
                </div>

                {{-- Bilah status --}}
                <div class="flex min-h-12 flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50 px-5 py-2 text-sm">
                    <template x-if="!dipilih && !pesan">
                        <span class="text-slate-500">Ketuk atau geser sebuah petak rumah untuk mulai.</span>
                    </template>
                    <template x-if="pesan">
                        <span :class="pesanGagal ? 'text-rose-700' : 'text-emerald-700'" x-text="pesan"></span>
                    </template>
                    <template x-if="dipilih && !pesan">
                        <div class="flex flex-1 flex-wrap items-center gap-2">
                            <span>Rumah <b x-text="rumah(dipilih).nomor"></b> dipilih — ketuk petak tujuan.</span>
                            <form class="flex items-center gap-1" @submit.prevent="gantiNomor()">
                                <input x-model="nomorBaru" x-ref="nomorBaru" maxlength="10" class="input w-20 py-1" aria-label="Nomor baru">
                                <button class="btn btn-secondary btn-sm" :disabled="sibuk">Ganti nomor</button>
                            </form>
                            <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="batal()">Batal</button>
                        </div>
                    </template>
                </div>

                <div class="overflow-x-auto p-5">
                    <div class="inline-grid select-none gap-1" :style="`grid-template-columns: 2rem repeat(${kolomTampil()}, 3rem)`">
                        <template x-for="sel in daftarSel()" :key="sel.key">
                            <div :style="sel.tipe === 'jalan' ? `grid-column: 2 / span ${kolomTampil()}` : ''"
                                 :class="{
                                    'text-center text-[10px] text-slate-400 flex items-center justify-center': sel.tipe === 'label',
                                    'flex h-5 items-center justify-center rounded bg-slate-100 text-[9px] uppercase tracking-[0.3em] text-slate-400': sel.tipe === 'jalan',
                                 }">
                                <template x-if="sel.tipe === 'label' || sel.tipe === 'jalan'"><span x-text="sel.teks"></span></template>
                                <template x-if="sel.tipe === 'sel'">
                                    <div :data-sel="sel.b + '-' + sel.k"
                                         class="relative flex h-12 items-center justify-center rounded-md text-xs font-bold transition"
                                         :class="kelasSel(sel)"
                                         @click="!sel.r && ketukKosong(sel)">
                                        <template x-if="sel.r">
                                            <div class="flex size-full cursor-grab touch-none items-center justify-center rounded-md border-2 active:cursor-grabbing"
                                                 :class="kelasRumah(sel.r)"
                                                 :title="sel.r.kk || 'Belum ada KK'"
                                                 @pointerdown="mulaiGeser($event, sel.r)"
                                                 @dblclick="pilih(sel.r); $nextTick(() => $refs.nomorBaru && $refs.nomorBaru.select())">
                                                <span x-text="sel.r.nomor"></span>
                                                <span x-show="sel.r.adaTitik" class="absolute bottom-0.5 right-0.5 size-1.5 rounded-full bg-sky-600" title="Sudah ada titik di peta"></span>
                                            </div>
                                        </template>
                                        <template x-if="!sel.r"><span class="text-slate-300">+</span></template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Pilihan saat dijatuhkan ke rumah lain --}}
                <div x-cloak x-show="tukar" class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" @click.self="tukar = null">
                    <div class="card card-body w-full max-w-sm space-y-3" x-show="tukar">
                        <template x-if="tukar">
                            <div class="space-y-3">
                                <h3 class="card-title">Rumah <span x-text="rumah(tukar.a).nomor"></span> ↔ rumah <span x-text="rumah(tukar.b).nomor"></span></h3>
                                <button type="button" class="btn btn-primary w-full justify-start" @click="kirimTukar('tukar_posisi')" :disabled="sibuk">
                                    Tukar posisi
                                    <span class="ml-auto text-xs font-normal opacity-80">nomor tetap, letak petak bertukar</span>
                                </button>
                                <button type="button" class="btn btn-secondary w-full justify-start" @click="kirimTukar('tukar_nomor')" :disabled="sibuk">
                                    Tukar nomor rumah
                                    <span class="ml-auto text-xs font-normal text-slate-500">letak tetap, nomor bertukar</span>
                                </button>
                                <p class="text-xs text-slate-500">Tukar nomor: data KK dan titik peta tetap di rumah (lokasi) masing-masing, hanya nomornya yang ditukar.</p>
                                <button type="button" class="btn btn-ghost w-full" @click="tukar = null">Batal</button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Bayangan petak saat digeser --}}
                <div x-cloak x-show="geser.aktif" class="pointer-events-none fixed z-50 flex h-12 w-12 items-center justify-center rounded-md border-2 border-brand-700 bg-brand-600 text-xs font-bold text-white shadow-xl"
                     :style="{ left: (geser.x - 24) + 'px', top: (geser.y - 24) + 'px' }" x-text="geser.aktif ? rumah(geser.id).nomor : ''"></div>
            </div>

            {{-- Daftar rumah --}}
            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5"><h2 class="card-title">Daftar rumah ({{ $blok->rumahs->count() }})</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($blok->rumahs->sortBy(fn ($r) => [strlen($r->nomor), $r->nomor]) as $r)
                        <div id="rumah-{{ $r->id }}" x-data="{ edit: false }" class="scroll-mt-20 target:bg-yellow-50">
                            <div class="flex items-center gap-2 px-4 py-3 sm:gap-3 sm:px-5">
                                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-bold text-slate-700" x-text="rumah({{ $r->id }})?.nomor ?? @js($r->nomor)">{{ $r->nomor }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-2 text-sm font-medium text-slate-800">
                                        {{ $r->keluargaAktif->pluck('nama_kepala')->join(', ') ?: 'Belum ada KK terdaftar' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500 sm:whitespace-normal"><span x-text="posisiTeks({{ $r->id }})">Baris {{ $r->baris }}, kolom {{ $r->kolom }}</span> · {{ $statusList[$r->status_hunian] ?? $r->status_hunian }} @if ($r->pemilik) · Pemilik: {{ $r->pemilik }} @endif</p>
                                </div>
                                <div class="flex shrink-0 items-center">
                                <a href="{{ route('keluarga.create', ['rumah' => $r->id]) }}" class="btn btn-secondary btn-sm px-2 sm:px-2.5" title="Tambah KK"><x-icon name="plus" class="size-4" /> KK</a>
                                <button type="button" class="btn btn-ghost btn-sm px-2" @click="edit = !edit" aria-label="Ubah rumah"><x-icon name="pencil" class="size-4" /></button>
                                <form method="post" action="{{ route('rumah.destroy', $r) }}" onsubmit="return confirm(@js('Hapus rumah No. '.$r->nomor.'?'))">
                                    @csrf @method('delete')
                                    <button class="btn btn-ghost btn-sm px-2 text-rose-600" aria-label="Hapus rumah"><x-icon name="trash" class="size-4" /></button>
                                </form>
                                </div>
                            </div>
                            <form x-cloak x-show="edit" method="post" action="{{ route('rumah.update', $r) }}" class="grid grid-cols-2 gap-3 bg-slate-50 px-5 py-4 sm:grid-cols-6">
                                @csrf @method('put')
                                <div><label class="label">Nomor</label><input name="nomor" value="{{ $r->nomor }}" :value="rumah({{ $r->id }})?.nomor" class="input" required></div>
                                <div><label class="label">Baris</label><input type="number" name="baris" value="{{ $r->baris }}" :value="rumah({{ $r->id }})?.baris" min="1" class="input" required></div>
                                <div><label class="label">Kolom</label><input type="number" name="kolom" value="{{ $r->kolom }}" :value="rumah({{ $r->id }})?.kolom" min="1" class="input" required></div>
                                <div class="col-span-2 sm:col-span-3">
                                    <label class="label">Status</label>
                                    <select name="status_hunian" class="input">
                                        @foreach ($statusList as $k => $l)
                                            <option value="{{ $k }}" @selected($r->status_hunian === $k)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2 sm:col-span-3"><label class="label">Nama pemilik (opsional)</label><input name="pemilik" value="{{ $r->pemilik }}" class="input"></div>
                                <div class="col-span-2 sm:col-span-3"><label class="label">Keterangan</label><input name="keterangan" value="{{ $r->keterangan }}" class="input"></div>
                                <div class="col-span-2 sm:col-span-6"><button class="btn btn-primary btn-sm">Simpan</button></div>
                            </form>
                        </div>
                    @empty
                        <x-empty icon="home" judul="Belum ada rumah">Gunakan "Tambah banyak rumah" untuk membuat nomor rumah sekaligus.</x-empty>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <form method="post" action="{{ route('rumah.massal', $blok) }}" class="card card-body space-y-3">
                @csrf
                <h2 class="card-title">Tambah banyak rumah</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Dari No.</label><input type="number" name="dari" value="1" min="1" class="input" required></div>
                    <div><label class="label">Sampai No.</label><input type="number" name="sampai" value="16" min="1" class="input" required></div>
                    <div><label class="label">Jumlah baris</label><input type="number" name="jumlah_baris" value="2" min="1" max="6" class="input" required></div>
                    <div><label class="label">Awalan</label><input name="awalan" placeholder="opsional" maxlength="4" class="input"></div>
                </div>
                <p class="hint">Contoh: 1–16 dalam 2 baris → baris 1 berisi No. 1–8, baris 2 berisi No. 9–16 (saling berhadapan).</p>
                <button class="btn btn-primary w-full">Buat rumah</button>
            </form>

            <form method="post" action="{{ route('rumah.store', $blok) }}" class="card card-body space-y-3">
                @csrf
                <h2 class="card-title">Tambah satu rumah</h2>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="label">Nomor</label><input id="f-nomor" name="nomor" value="{{ old('nomor') }}" class="input" required></div>
                    <div><label class="label">Baris</label><input id="f-baris" type="number" name="baris" value="{{ old('baris', 1) }}" min="1" class="input" required></div>
                    <div><label class="label">Kolom</label><input id="f-kolom" type="number" name="kolom" value="{{ old('kolom', 1) }}" min="1" class="input" required></div>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select name="status_hunian" class="input">
                        @foreach ($statusList as $k => $l)
                            <option value="{{ $k }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary w-full">Tambah rumah</button>
            </form>

            <form method="post" action="{{ route('blok.update', $blok) }}" class="card card-body space-y-3">
                @csrf @method('put')
                <h2 class="card-title">Pengaturan blok</h2>
                <div><label class="label">Nama blok</label><input name="nama" value="{{ $blok->nama }}" class="input" required></div>
                <div><label class="label">Urutan tampil</label><input type="number" name="urutan" value="{{ $blok->urutan }}" min="0" class="input"></div>
                <div><label class="label">Keterangan</label><textarea name="keterangan" rows="2" class="input">{{ $blok->keterangan }}</textarea></div>
                <button class="btn btn-secondary w-full">Simpan blok</button>
            </form>
            <form method="post" action="{{ route('blok.destroy', $blok) }}" onsubmit="return confirm(@js('Hapus Blok '.$blok->nama.' beserta semua rumahnya?'))">
                @csrf @method('delete')
                <button class="btn btn-ghost w-full text-rose-600"><x-icon name="trash" class="size-4" /> Hapus blok ini</button>
            </form>
        </div>
    </div>
    @push('scripts')
        <script>
            function susunBlok(rumahs, url) {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                return {
                    rumahs, dipilih: null, nomorBaru: '', tukar: null, sibuk: false, pesan: '', pesanGagal: false,
                    geser: { aktif: false, id: null, x: 0, y: 0, sx: 0, sy: 0, target: null },

                    rumah(id) { return this.rumahs.find(r => r.id === id) },
                    posisiTeks(id) { const r = this.rumah(id); return r ? `Baris ${r.baris}, kolom ${r.kolom}` : '' },
                    barisTampil() { return Math.max(2, ...this.rumahs.map(r => r.baris)) + 1 },
                    kolomTampil() { return Math.max(8, ...this.rumahs.map(r => r.kolom)) + 1 },

                    daftarSel() {
                        const peta = {}; this.rumahs.forEach(r => peta[r.baris + '-' + r.kolom] = r);
                        const out = [{ tipe: 'label', key: 'pojok', teks: '' }];
                        const K = this.kolomTampil(), B = this.barisTampil();
                        for (let k = 1; k <= K; k++) out.push({ tipe: 'label', key: 'k' + k, teks: k });
                        for (let b = 1; b <= B; b++) {
                            out.push({ tipe: 'label', key: 'b' + b, teks: b });
                            for (let k = 1; k <= K; k++) out.push({ tipe: 'sel', key: b + '-' + k, b, k, r: peta[b + '-' + k] || null });
                            if (b % 2 === 0 && b < B) { out.push({ tipe: 'label', key: 'j' + b, teks: '' }); out.push({ tipe: 'jalan', key: 'jalan' + b, teks: 'jalan' }); }
                        }
                        return out;
                    },
                    kelasSel(sel) {
                        const target = this.geser.target === sel.b + '-' + sel.k;
                        if (sel.r) return target ? 'ring-4 ring-yellow-400' : '';
                        return 'border border-dashed ' + (target ? 'border-brand-600 bg-brand-50' : (this.dipilih ? 'border-brand-300 bg-brand-50/40 cursor-pointer hover:bg-brand-100' : 'border-slate-200 cursor-pointer hover:border-brand-400'));
                    },
                    kelasRumah(r) {
                        const dipilih = this.dipilih === r.id ? ' ring-4 ring-yellow-400 ring-offset-1' : '';
                        const redup = this.geser.aktif && this.geser.id === r.id ? ' opacity-30' : '';
                        const warna = r.status === 'kosong' ? 'border-slate-300 bg-slate-200 text-slate-500'
                            : (r.kk ? 'border-emerald-600 bg-emerald-500 text-white' : 'border-dashed border-slate-400 bg-white text-slate-700');
                        return warna + dipilih + redup;
                    },

                    // ---------- pilih / ketuk ----------
                    pilih(r) { this.dipilih = r.id; this.nomorBaru = r.nomor; this.pesan = ''; },
                    batal() { this.dipilih = null; this.tukar = null; },
                    ketukKosong(sel) {
                        if (this.dipilih) { this.pindah(this.dipilih, sel.b, sel.k); return; }
                        // tanpa pilihan: isi form "Tambah satu rumah"
                        const b = document.getElementById('f-baris'), k = document.getElementById('f-kolom'), n = document.getElementById('f-nomor');
                        if (b && k && n) { b.value = sel.b; k.value = sel.k; n.focus(); }
                    },
                    ketukRumah(r) {
                        if (this.dipilih && this.dipilih !== r.id) { this.tukar = { a: this.dipilih, b: r.id }; return; }
                        this.dipilih === r.id ? this.batal() : this.pilih(r);
                    },

                    // ---------- geser (mouse & sentuh) ----------
                    mulaiGeser(e, r) {
                        if (e.button !== undefined && e.button !== 0) return;
                        const g = this.geser;
                        Object.assign(g, { aktif: false, id: r.id, x: e.clientX, y: e.clientY, sx: e.clientX, sy: e.clientY, target: null });
                        const gerak = ev => {
                            g.x = ev.clientX; g.y = ev.clientY;
                            if (!g.aktif && Math.hypot(g.x - g.sx, g.y - g.sy) > 6) g.aktif = true;
                            if (g.aktif) {
                                const el = document.elementFromPoint(g.x, g.y);
                                const s = el && el.closest('[data-sel]');
                                g.target = s ? s.dataset.sel : null;
                            }
                        };
                        const lepas = () => {
                            window.removeEventListener('pointermove', gerak);
                            window.removeEventListener('pointerup', lepas);
                            window.removeEventListener('pointercancel', lepas);
                            const tadi = g.aktif, tujuan = g.target;
                            g.aktif = false; g.target = null;
                            if (!tadi) { this.ketukRumah(r); return; }
                            if (!tujuan) return;
                            const [b, k] = tujuan.split('-').map(Number);
                            const lain = this.rumahs.find(x => x.baris === b && x.kolom === k);
                            if (!lain) this.pindah(r.id, b, k);
                            else if (lain.id !== r.id) this.tukar = { a: r.id, b: lain.id };
                        };
                        window.addEventListener('pointermove', gerak);
                        window.addEventListener('pointerup', lepas);
                        window.addEventListener('pointercancel', lepas);
                    },

                    // ---------- simpan ke server ----------
                    async kirim(body, sukses) {
                        this.sibuk = true;
                        try {
                            const res = await fetch(url, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                                body: JSON.stringify(body),
                            });
                            const j = await res.json().catch(() => ({}));
                            if (!res.ok) throw new Error(j.errors ? Object.values(j.errors).flat().join(' ') : (j.message || 'Gagal menyimpan'));
                            this.rumahs = j.rumahs;
                            this.kabar(sukses);
                            this.dipilih = null; this.tukar = null;
                        } catch (e) {
                            this.kabar(e.message, true);
                        } finally { this.sibuk = false; }
                    },
                    kabar(t, gagal = false) {
                        this.pesan = t; this.pesanGagal = gagal;
                        clearTimeout(this._t); this._t = setTimeout(() => this.pesan = '', 3500);
                    },
                    pindah(id, b, k) {
                        const r = this.rumah(id);
                        this.kirim({ aksi: 'pindah', rumah_id: id, baris: b, kolom: k }, `Rumah ${r.nomor} dipindah ke baris ${b}, kolom ${k}.`);
                    },
                    kirimTukar(aksi) {
                        const a = this.rumah(this.tukar.a), b = this.rumah(this.tukar.b);
                        const t = aksi === 'tukar_posisi' ? `Posisi rumah ${a.nomor} dan ${b.nomor} ditukar.` : `Nomor rumah ${a.nomor} dan ${b.nomor} ditukar.`;
                        this.kirim({ aksi, rumah_id: a.id, target_id: b.id }, t);
                    },
                    gantiNomor() {
                        const r = this.rumah(this.dipilih);
                        if (!r || !this.nomorBaru.trim() || this.nomorBaru.trim() === r.nomor) return;
                        this.kirim({ aksi: 'nomor', rumah_id: r.id, nomor: this.nomorBaru.trim() }, `Nomor ${r.nomor} diganti menjadi ${this.nomorBaru.trim()}.`);
                    },
                }
            }
        </script>
    @endpush
@endsection
