@extends('layouts.app')
@section('title', 'Kelola Iuran')

@section('content')
    @php
        $u = auth()->user();
        $persen = $ringkasan['jumlah'] > 0 ? round($ringkasan['jumlah_lunas'] / $ringkasan['jumlah'] * 100) : 0;
        $sebelum = $periode->copy()->subMonthNoOverflow()->format('Y-m');
        $sesudah = $periode->copy()->addMonthNoOverflow()->format('Y-m');
        $abaikanBulan = $jenisDipilih && $jenisDipilih->frekuensi !== 'bulanan' && ! request()->filled('periode');
        $namaJenis = $daftarJenis->keyBy('id');
    @endphp

    <x-page-header judul="Kelola Iuran" sub="Tagihan per jenis iuran untuk setiap kepala keluarga.">
        <a href="{{ route('iuran.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> Unduh CSV</a>
    </x-page-header>
    @include('iuran._tabs')

    {{-- Filter --}}
    <form method="get" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label class="label">Jenis iuran</label>
            <select name="jenis" class="input w-auto"
                    onchange="const o = this.selectedOptions[0]; if (this.form.periode && o.dataset.frek && o.dataset.frek !== 'bulanan') this.form.periode.disabled = true; this.form.submit()">
                <option value="">Semua jenis</option>
                @foreach ($daftarJenis->groupBy('frekuensi') as $frek => $grup)
                    <optgroup label="{{ \App\Models\TarifIuran::FREKUENSI[$frek] ?? $frek }}">
                        @foreach ($grup as $j)
                            <option value="{{ $j->id }}" data-frek="{{ $j->frekuensi }}" @selected($filter['jenis'] === $j->id)>{{ $j->nama }}{{ $j->aktif ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        @if ($abaikanBulan)
            <a href="{{ request()->fullUrlWithQuery(['periode' => now()->format('Y-m'), 'page' => null]) }}" class="btn btn-ghost btn-sm self-end">Filter per bulan</a>
        @else
            <div>
                <label class="label">Bulan</label>
                <div class="inline-flex items-center rounded-lg border border-slate-300 bg-white">
                    <a href="{{ request()->fullUrlWithQuery(['periode' => $sebelum, 'page' => null]) }}" class="px-3 py-2 text-slate-500 hover:text-slate-900">‹</a>
                    <input type="month" name="periode" value="{{ $periode->format('Y-m') }}" onchange="this.form.submit()" class="border-x border-y-0 border-slate-200 bg-transparent px-2 py-1.5 text-sm font-medium focus:ring-0">
                    <a href="{{ request()->fullUrlWithQuery(['periode' => $sesudah, 'page' => null]) }}" class="px-3 py-2 text-slate-500 hover:text-slate-900">›</a>
                </div>
            </div>
        @endif
        @if ($u->isAdmin())
            <div>
                <label class="label">RT</label>
                <select name="rt" class="input w-auto" onchange="this.form.submit()">
                    <option value="">Semua RT</option>
                    @foreach ($rts as $rt)
                        <option value="{{ $rt->id }}" @selected($filter['rtId'] === $rt->id)>RT {{ $rt->nomor }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="label">Status</label>
            <select name="status" class="input w-auto" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="belum" @selected($filter['status'] === 'belum')>Belum bayar</option>
                <option value="lunas" @selected($filter['status'] === 'lunas')>Lunas</option>
            </select>
        </div>
        <div>
            <label class="label">Cari</label>
            <input type="search" name="q" value="{{ $filter['q'] }}" placeholder="Nama kepala keluarga" class="input w-48">
        </div>
        <button class="btn btn-secondary">Terapkan</button>
    </form>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200">
        <p class="text-slate-600">
            @if ($abaikanBulan)
                Menampilkan semua tagihan <b>{{ $jenisDipilih->nama }}</b> ({{ \App\Models\TarifIuran::FREKUENSI[$jenisDipilih->frekuensi] }}).
            @else
                Menampilkan tagihan periode <b>{{ $periode->translatedFormat('F Y') }}</b>{{ $jenisDipilih ? ' untuk '.$jenisDipilih->nama : '' }}.
                Iuran bulanan dibuat otomatis tiap tanggal 1.
            @endif
        </p>
        @unless ($abaikanBulan)
            <form method="post" action="{{ route('iuran.generate') }}">
                @csrf
                <input type="hidden" name="periode" value="{{ $periode->format('Y-m') }}">
                <input type="hidden" name="rt" value="{{ $filter['rtId'] }}">
                <button class="btn btn-secondary btn-sm" title="Aman diklik berulang kali; tagihan yang sudah ada tidak digandakan">
                    <x-icon name="refresh" class="size-4" /> Buat tagihan bulanan/tahunan {{ $periode->translatedFormat('F') }}
                </button>
            </form>
        @endunless
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card card-body">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Terkumpul</p>
            <p class="mt-1 text-xl font-bold text-emerald-700">{{ rupiah($ringkasan['lunas']) }}</p>
            <p class="text-xs text-slate-500">target wajib {{ rupiah($ringkasan['total']) }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Lunas</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ $persen }}%</p>
            <p class="text-xs text-slate-500">{{ $ringkasan['jumlah_lunas'] }} dari {{ $ringkasan['jumlah'] }} tagihan</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Via QRIS</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ rupiah($ringkasan['qris']) }}</p>
            <p class="text-xs text-slate-500">otomatis dari AINO</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Tunai / transfer</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ rupiah($ringkasan['tunai']) }}</p>
            <p class="text-xs text-slate-500">dicatat pengurus</p>
        </div>
    </div>

    @if ($perJenis->count() > 1)
        <div class="card mb-5 overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="card-title">Per jenis iuran</h2></div>
            {{-- HP: daftar ringkas --}}
            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($perJenis as $pj)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-slate-900">{{ $pj->tarif_iuran_id ? ($namaJenis[$pj->tarif_iuran_id]->nama ?? 'Jenis terhapus') : 'Iuran (data lama)' }}</p>
                            <p class="text-xs text-slate-500">{{ $pj->lunas }}/{{ $pj->jumlah }} lunas</p>
                        </div>
                        <p class="text-right text-sm font-semibold text-slate-900">{{ rupiah($pj->terkumpul) }}</p>
                        @if ($pj->tarif_iuran_id)
                            <a href="{{ request()->fullUrlWithQuery(['jenis' => $pj->tarif_iuran_id, 'page' => null]) }}" class="btn btn-ghost btn-sm -mr-2 text-brand-700">Filter</a>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="table">
                    <thead><tr><th>Jenis</th><th class="text-right">Tagihan</th><th class="text-right">Lunas</th><th class="text-right">Terkumpul</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($perJenis as $pj)
                            <tr>
                                <td class="font-medium">{{ $pj->tarif_iuran_id ? ($namaJenis[$pj->tarif_iuran_id]->nama ?? 'Jenis terhapus') : 'Iuran (data lama)' }}</td>
                                <td class="text-right">{{ $pj->jumlah }}</td>
                                <td class="text-right">{{ $pj->lunas }}</td>
                                <td class="text-right font-medium">{{ rupiah($pj->terkumpul) }}</td>
                                <td class="text-right">
                                    @if ($pj->tarif_iuran_id)
                                        <a href="{{ request()->fullUrlWithQuery(['jenis' => $pj->tarif_iuran_id, 'page' => null]) }}" class="text-xs text-brand-700 underline">filter</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card overflow-hidden" x-data="{ catat: null }" @keydown.escape.window="catat = null">
        @if ($tagihan->isEmpty())
            <x-empty icon="wallet" judul="Belum ada tagihan">
                Buat jenis iuran di tab “Jenis iuran”. Iuran bulanan/tahunan dibuat dengan tombol di atas atau otomatis tiap tanggal 1; iuran insidental dengan tombol “Terbitkan tagihan”.
            </x-empty>
        @else
            @php
                $dataCatat = fn ($t) => [
                    'url' => route('iuran.lunas', $t),
                    'judul' => $t->periode_label,
                    'nama' => $t->kartuKeluarga->nama_kepala,
                    'sukarela' => (bool) $t->sukarela,
                    'min' => max(1, (int) $t->nominal),
                    'nominal' => $t->sukarela ? ($t->nominal > 0 ? (int) $t->nominal : null) : (int) $t->nominal,
                ];
                $teksNominal = function ($t) {
                    if ($t->isLunas() && $t->nominal_dibayar !== null && $t->nominal_dibayar !== $t->nominal) return rupiah($t->nominal_dibayar);
                    if ($t->sukarela && ! $t->isLunas()) return $t->nominal > 0 ? 'min. '.rupiah($t->nominal) : 'bebas';
                    return rupiah($t->nominal);
                };
            @endphp

            {{-- Tampilan HP: kartu --}}
            <ul class="divide-y divide-slate-100 sm:hidden">
                @foreach ($tagihan as $t)
                    @php $kk = $t->kartuKeluarga; $tg = $tunggakan->get($t->kartu_keluarga_id); @endphp
                    <li class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('keluarga.show', $kk) }}" class="block truncate font-medium text-slate-900">{{ $kk->nama_kepala }}</a>
                                <p class="text-xs text-slate-500">{{ $kk->rumah?->kode ?? '—' }} · RT {{ $kk->rumah?->blok?->rt?->nomor }}</p>
                                <p class="mt-0.5 text-xs text-slate-700">{{ $t->periode_label }} @if ($t->sukarela) <span class="badge badge-green">Sukarela</span> @endif</p>
                            </div>
                            <p class="shrink-0 text-right text-sm font-semibold text-slate-900">{{ $teksNominal($t) }}</p>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="text-xs">
                                @if ($t->isLunas())
                                    <span class="badge badge-green">Lunas · {{ \App\Models\Tagihan::METODE[$t->metode] ?? $t->metode }}</span>
                                    <span class="text-slate-500">{{ $t->dibayar_pada?->format('d/m H:i') }}</span>
                                @else
                                    <span class="badge {{ $t->terlambat() ? 'badge-red' : 'badge-amber' }}">{{ $t->terlambat() ? 'Terlambat' : 'Belum bayar' }}</span>
                                    <span class="text-slate-500">tempo {{ $t->jatuhTempo()->format('d/m/Y') }}</span>
                                @endif
                                @if ($tg) <p class="mt-1 text-rose-600">Tunggakan lain: {{ $tg->bulan }} tagihan · {{ rupiah($tg->total) }}</p> @endif
                            </div>
                            @if ($t->isLunas())
                                @if (! in_array($t->metode, ['qris', 'va']))
                                    <form method="post" action="{{ route('iuran.batal', $t) }}" onsubmit="return confirm('Batalkan status lunas?')">
                                        @csrf <button class="btn btn-ghost btn-sm text-slate-500">Batalkan</button>
                                    </form>
                                @endif
                            @else
                                <button type="button" class="btn btn-secondary btn-sm" @click="catat = @js($dataCatat($t))">Catat bayar</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Tampilan layar lebar: tabel --}}
            <div class="hidden overflow-x-auto sm:block">
                <table class="table">
                    <thead>
                        <tr><th>Rumah</th><th>Kepala keluarga</th><th>Tagihan</th><th class="text-right">Nominal</th><th>Status</th><th>Tunggakan lain</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($tagihan as $t)
                            @php $kk = $t->kartuKeluarga; $tg = $tunggakan->get($t->kartu_keluarga_id); @endphp
                            <tr>
                                <td class="whitespace-nowrap font-medium">{{ $kk->rumah?->kode ?? '—' }} <span class="text-xs text-slate-400">RT {{ $kk->rumah?->blok?->rt?->nomor }}</span></td>
                                <td><a href="{{ route('keluarga.show', $kk) }}" class="hover:text-brand-700 hover:underline">{{ $kk->nama_kepala }}</a></td>
                                <td class="text-xs">
                                    {{ $t->periode_label }}
                                    @if ($t->sukarela) <span class="badge badge-green ml-1">Sukarela</span> @endif
                                </td>
                                <td class="whitespace-nowrap text-right {{ $t->sukarela && ! $t->isLunas() ? 'text-slate-500' : '' }}">{{ $teksNominal($t) }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($t->isLunas())
                                        <span class="badge badge-green">Lunas · {{ \App\Models\Tagihan::METODE[$t->metode] ?? $t->metode }}</span>
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $t->dibayar_pada?->format('d/m H:i') }}{{ $t->pencatat ? ' · '.$t->pencatat->name : '' }}</p>
                                    @else
                                        <span class="badge {{ $t->terlambat() ? 'badge-red' : 'badge-amber' }}">{{ $t->terlambat() ? 'Terlambat' : 'Belum bayar' }}</span>
                                        <p class="mt-0.5 text-[11px] text-slate-500">tempo {{ $t->jatuhTempo()->format('d/m/Y') }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($tg) <span class="text-rose-600">{{ $tg->bulan }} tagihan · {{ rupiah($tg->total) }}</span> @else <span class="text-slate-400">—</span> @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($t->isLunas())
                                        @if (! in_array($t->metode, ['qris', 'va']))
                                            <form method="post" action="{{ route('iuran.batal', $t) }}" onsubmit="return confirm('Batalkan status lunas?')" class="inline">
                                                @csrf <button class="btn btn-ghost btn-sm text-slate-500">Batalkan</button>
                                            </form>
                                        @endif
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm" @click="catat = @js($dataCatat($t))">Catat bayar</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $tagihan->links() }}</div>

            {{-- Form catat bayar (bottom sheet di HP, dialog di layar lebar) --}}
            <div x-cloak x-show="catat" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" @click.self="catat = null">
                <template x-if="catat">
                    <form method="post" :action="catat.url" class="card card-body w-full max-w-sm space-y-3">
                        @csrf
                        <div>
                            <h3 class="card-title">Catat pembayaran</h3>
                            <p class="text-sm text-slate-600"><span x-text="catat.nama"></span> · <span x-text="catat.judul"></span></p>
                        </div>
                        <template x-if="catat.sukarela">
                            <div>
                                <label class="label">Nominal diterima</label>
                                <input type="number" name="nominal" :min="catat.min" :value="catat.nominal" inputmode="numeric" class="input" required>
                            </div>
                        </template>
                        <template x-if="!catat.sukarela">
                            <p class="rounded-lg bg-slate-50 p-3 text-center text-lg font-bold text-slate-900" x-text="'Rp ' + Number(catat.nominal).toLocaleString('id-ID')"></p>
                        </template>
                        <div class="grid grid-cols-2 gap-2">
                            <button name="metode" value="tunai" class="btn btn-primary">Tunai</button>
                            <button name="metode" value="transfer" class="btn btn-secondary">Transfer</button>
                        </div>
                        <button type="button" class="btn btn-ghost w-full" @click="catat = null">Batal</button>
                    </form>
                </template>
            </div>
        @endif
    </div>
@endsection
