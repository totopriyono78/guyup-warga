@extends('layouts.app')
@section('title', 'Iuran Saya')

@section('content')
    <x-page-header judul="Iuran Saya" :sub="'Keluarga '.$kk->nama_kepala.($kk->rumah ? ' · '.$kk->rumah->alamat : '')" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($belum->isEmpty())
                <div class="card card-body flex items-center gap-4">
                    <div class="rounded-full bg-emerald-100 p-3 text-emerald-700"><x-icon name="check" class="size-7" /></div>
                    <div>
                        <p class="text-lg font-semibold text-slate-900">Semua iuran sudah lunas</p>
                        <p class="text-sm text-slate-500">Terima kasih telah membayar tepat waktu.</p>
                    </div>
                </div>
            @else
                <form method="post" action="{{ route('pembayaran.store') }}" class="card overflow-hidden"
                      x-data="bayar(@js($belum->map(fn ($t) => ['id' => $t->id, 'nominal' => $t->nominal, 'sukarela' => $t->sukarela])->values()), {{ $biayaPersen }})">
                    @csrf
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="card-title">Iuran yang belum dibayar</h2>
                        <p class="text-sm text-slate-500">Centang yang ingin dibayar — beberapa iuran bisa dibayar sekaligus dengan satu QRIS.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($belum as $t)
                            @php $frek = $t->jenis?->frekuensi; @endphp
                            <div class="flex items-start gap-4 px-5 py-3.5 hover:bg-slate-50">
                                <input type="checkbox" id="tg-{{ $t->id }}" name="tagihan[]" value="{{ $t->id }}" x-model.number="dipilih" class="mt-0.5 size-5 rounded border-slate-300 text-brand-700">
                                <label for="tg-{{ $t->id }}" class="min-w-0 flex-1 cursor-pointer">
                                    <p class="font-medium text-slate-900">{{ $t->periode_label }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                        @if ($frek) <span class="badge {{ ['bulanan' => 'badge-blue', 'tahunan' => 'badge-amber', 'insidental' => 'badge-red'][$frek] ?? 'badge-slate' }}">{{ \App\Models\TarifIuran::FREKUENSI[$frek] ?? $frek }}</span> @endif
                                        @if ($t->sukarela) <span class="badge badge-green">Sukarela</span> @endif
                                        <span>Jatuh tempo {{ $t->jatuhTempo()->translatedFormat('j M Y') }}</span>
                                        @if ($t->terlambat()) <span class="badge badge-red">Lewat jatuh tempo</span> @endif
                                    </p>
                                    @if ($t->jenis?->keterangan) <p class="mt-0.5 text-xs text-slate-500">{{ $t->jenis->keterangan }}</p> @endif
                                </label>
                                @if ($t->sukarela)
                                    <div class="w-36 text-right">
                                        <input type="number" name="jumlah[{{ $t->id }}]" x-model.number="jumlahSukarela[{{ $t->id }}]" min="{{ max(1, $t->nominal) }}"
                                               placeholder="{{ $t->nominal > 0 ? 'min. '.number_format($t->nominal, 0, ',', '.') : 'Nominal' }}"
                                               class="input py-1 text-right" @focus="if (!dipilih.includes({{ $t->id }})) dipilih.push({{ $t->id }})">
                                        <p class="mt-0.5 text-[11px] text-slate-500">isi nominal sendiri</p>
                                    </div>
                                @else
                                    <p class="font-semibold text-slate-900">{{ rupiah($t->nominal) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="space-y-1 border-t border-slate-200 bg-slate-50 px-5 py-4 text-sm">
                        <div class="flex justify-between"><span class="text-slate-600">Jumlah iuran</span><span x-text="rp(jumlah())"></span></div>
                        <template x-if="persen > 0">
                            <div class="flex justify-between"><span class="text-slate-600">Biaya layanan QRIS ({{ rtrim(rtrim(number_format($biayaPersen, 2, ',', ''), '0'), ',') }}%)</span><span x-text="rp(biaya())"></span></div>
                        </template>
                        <div class="flex justify-between pt-1 text-base font-bold text-slate-900"><span>Total bayar</span><span x-text="rp(jumlah() + biaya())"></span></div>
                    </div>
                    <div class="px-5 py-4">
                        @if ($gatewayAktif)
                            <p class="mb-2 text-center text-sm text-rose-600" x-show="kurang()" x-text="kurang()"></p>
                            <button class="btn btn-primary w-full py-3 text-base" :disabled="dipilih.length === 0 || !!kurang()">
                                <x-icon name="qr" /> Bayar dengan QRIS
                            </button>
                            <p class="mt-2 text-center text-xs text-slate-500">Bisa dibayar dengan semua aplikasi bank & e-wallet yang mendukung QRIS.</p>
                        @else
                            <p class="rounded-lg bg-amber-50 p-3 text-center text-sm text-amber-800">Pembayaran QRIS belum diaktifkan pengurus. Silakan bayar tunai ke bendahara RT.</p>
                        @endif
                    </div>
                </form>
            @endif

            @if ($transaksi->isNotEmpty())
                <h2 class="mb-3 mt-8 text-lg font-semibold text-slate-900">Transaksi QRIS terakhir</h2>
                <div class="card divide-y divide-slate-100">
                    @foreach ($transaksi as $p)
                        <a href="{{ route('pembayaran.show', $p) }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-slate-50">
                            <div>
                                <p class="font-medium text-slate-800">{{ $p->tagihans->map(fn ($t) => $t->periode_label)->join(', ') }}</p>
                                <p class="text-xs text-slate-500">{{ $p->created_at->translatedFormat('j M Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ rupiah($p->total) }}</p>
                                <span class="badge {{ ['paid' => 'badge-green', 'pending' => 'badge-amber'][$p->status] ?? 'badge-slate' }}">{{ $p->statusLabel() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card h-fit">
            <div class="border-b border-slate-100 px-5 py-3.5"><h2 class="card-title">Riwayat lunas</h2></div>
            @forelse ($riwayat as $t)
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-2.5 text-sm last:border-0">
                    <div>
                        <p class="font-medium text-slate-800">{{ $t->periode_label }}</p>
                        <p class="text-xs text-slate-500">{{ \App\Models\Tagihan::METODE[$t->metode] ?? $t->metode }} · {{ $t->dibayar_pada?->format('d/m/Y') }}</p>
                    </div>
                    <span class="font-medium text-emerald-700">{{ rupiah($t->nominal_masuk) }}</span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-slate-500">Belum ada riwayat.</p>
            @endforelse
        </div>
    </div>

    @push('scripts')
        <script>
            function bayar(tagihan, persen) {
                return {
                    tagihan, persen, jumlahSukarela: {},
                    dipilih: tagihan.filter(t => !t.sukarela).slice(0, 1).map(t => t.id),
                    nominalOf(t) { return t.sukarela ? (parseInt(this.jumlahSukarela[t.id], 10) || 0) : t.nominal },
                    jumlah() { return this.tagihan.filter(t => this.dipilih.includes(t.id)).reduce((s, t) => s + this.nominalOf(t), 0) },
                    biaya() { return this.persen > 0 ? Math.ceil(this.jumlah() * this.persen / 100) : 0 },
                    kurang() {
                        const t = this.tagihan.find(t => t.sukarela && this.dipilih.includes(t.id) && this.nominalOf(t) < Math.max(1, t.nominal));
                        return t ? 'Isi nominal untuk iuran sukarela yang dicentang' + (t.nominal > 0 ? ' (minimal ' + this.rp(t.nominal) + ')' : '') + '.' : '';
                    },
                    rp(n) { return 'Rp ' + n.toLocaleString('id-ID') },
                }
            }
        </script>
    @endpush
@endsection
