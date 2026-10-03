{{--
  Grid foto + penampil layar penuh (lightbox).
  $fotos  : koleksi GaleriFoto
  $kelola : tampilkan tombol kelola (sampul / keterangan / hapus)
  $sampulId : id foto sampul (opsional)
--}}
@php
    $kelola = $kelola ?? false;
    $daftar = $fotos->values()->map(fn ($f) => ['src' => $f->url(), 'ket' => $f->keterangan])->all();
@endphp
<div x-data="lightbox({{ Js::from($daftar) }})" @keydown.window="tombol($event)">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($fotos->values() as $i => $f)
            <figure class="group relative overflow-hidden rounded-xl bg-slate-100" x-data="{ ubah: false }">
                <button type="button" class="block w-full" @click="buka({{ $i }})" aria-label="Perbesar foto {{ $i + 1 }}">
                    <img src="{{ $f->url() }}" alt="{{ $f->keterangan ?? 'Foto kegiatan '.($i + 1) }}" loading="lazy"
                         class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                </button>
                @if (! empty($sampulId) && (int) $sampulId === $f->id)
                    <span class="badge badge-amber absolute left-2 top-2 shadow">Sampul</span>
                @endif
                @if ($f->keterangan)
                    <figcaption class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2.5 pb-2 pt-6 text-xs text-white">{{ $f->keterangan }}</figcaption>
                @endif

                @if ($kelola)
                    <div class="absolute right-1.5 top-1.5 flex gap-1">
                        <button type="button" @click="ubah = !ubah" class="rounded-lg bg-white/90 p-1.5 text-slate-700 shadow hover:bg-white" title="Keterangan / sampul"><x-icon name="pencil" class="size-4" /></button>
                        <form method="post" action="{{ route('galeri.foto.destroy', $f) }}" onsubmit="return confirm('Hapus foto ini?')">
                            @csrf @method('delete')
                            <button class="rounded-lg bg-white/90 p-1.5 text-rose-600 shadow hover:bg-white" title="Hapus foto"><x-icon name="trash" class="size-4" /></button>
                        </form>
                    </div>
                    <div x-cloak x-show="ubah" class="absolute inset-0 flex flex-col justify-end bg-slate-900/60 p-2">
                        <form method="post" action="{{ route('galeri.foto.update', $f) }}" class="space-y-1.5 rounded-lg bg-white p-2 shadow">
                            @csrf @method('put')
                            <input name="keterangan" value="{{ $f->keterangan }}" maxlength="255" placeholder="Keterangan foto" class="input py-1.5 text-xs">
                            <div class="flex gap-1.5">
                                <button class="btn btn-primary btn-sm flex-1">Simpan</button>
                                <button type="button" @click="ubah = false" class="btn btn-ghost btn-sm">Batal</button>
                            </div>
                        </form>
                        <form method="post" action="{{ route('galeri.foto.update', $f) }}" class="mt-1.5">
                            @csrf @method('put')
                            <input type="hidden" name="sampul" value="1">
                            <button class="btn btn-secondary btn-sm w-full"><x-icon name="star" class="size-3.5" /> Jadikan sampul</button>
                        </form>
                    </div>
                @endif
            </figure>
        @endforeach
    </div>

    {{-- Lightbox --}}
    <div x-cloak x-show="aktif !== null" x-transition.opacity class="fixed inset-0 z-[1300] flex flex-col bg-slate-950"
         @touchstart.passive="sentuh = $event.touches[0].clientX" @touchend="geser($event.changedTouches[0].clientX)">
        <div class="flex items-center justify-between px-4 py-3 text-sm text-white/80">
            <span x-text="aktif !== null ? (aktif + 1) + ' / ' + foto.length : ''"></span>
            <button type="button" @click="tutup()" class="rounded-full p-2 hover:bg-white/10" aria-label="Tutup"><x-icon name="x" class="size-6" /></button>
        </div>
        <div class="relative flex min-h-0 flex-1 items-center justify-center px-2" @click.self="tutup()">
            <img :src="aktif !== null ? foto[aktif].src : ''" alt="" class="max-h-full max-w-full select-none rounded-lg object-contain">
            <button type="button" x-show="foto.length > 1" @click="maju(-1)" class="absolute left-2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 sm:left-4" aria-label="Sebelumnya"><x-icon name="arrow-left" class="size-5" /></button>
            <button type="button" x-show="foto.length > 1" @click="maju(1)" class="absolute right-2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 sm:right-4" aria-label="Berikutnya"><x-icon name="arrow-left" class="size-5 rotate-180" /></button>
        </div>
        <p class="min-h-12 px-4 py-3 text-center text-sm text-white/90" x-text="aktif !== null ? (foto[aktif].ket || '') : ''"></p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function lightbox(foto) {
                return {
                    foto, aktif: null, sentuh: null,
                    buka(i) { this.aktif = i; document.body.style.overflow = 'hidden'; },
                    tutup() { this.aktif = null; document.body.style.overflow = ''; },
                    maju(n) { if (this.aktif === null) return; this.aktif = (this.aktif + n + this.foto.length) % this.foto.length; },
                    tombol(e) {
                        if (this.aktif === null) return;
                        if (e.key === 'Escape') this.tutup();
                        if (e.key === 'ArrowRight') this.maju(1);
                        if (e.key === 'ArrowLeft') this.maju(-1);
                    },
                    geser(x) {
                        if (this.sentuh === null) return;
                        const d = x - this.sentuh;
                        if (Math.abs(d) > 50) this.maju(d < 0 ? 1 : -1);
                        this.sentuh = null;
                    },
                };
            }
        </script>
    @endpush
@endonce
