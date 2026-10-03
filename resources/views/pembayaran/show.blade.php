@extends('layouts.app')
@section('title', 'Pembayaran QRIS')

@push('head')
    <script src="{{ asset('js/qrcode.js') }}"></script>
    <script src="{{ asset('js/pembayaran.js') }}?v={{ @filemtime(public_path('js/pembayaran.js')) }}"></script>
@endpush

@section('content')
    <div class="mx-auto max-w-md" x-data="pembayaran(@js([
            'status' => $p->status,
            'content' => $p->isPending() ? $p->payment_content : null,
            'expired' => $p->expired_at?->toIso8601String(),
            'url' => route('pembayaran.status', $p),
            'va' => $p->isVa(),
        ]))" x-init="mulai()">

        <x-page-header judul="Pembayaran Iuran" :kembali="(int) auth()->user()->kartu_keluarga_id === (int) $p->kartu_keluarga_id ? route('iuran.saya') : route('iuran.transaksi')" />

        <div class="card overflow-hidden text-center">
            <div class="border-b border-slate-100 px-6 py-5">
                <p class="text-sm text-slate-500">Total pembayaran</p>
                <p class="text-3xl font-bold text-slate-900">{{ rupiah($p->total) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $p->tagihans->map(fn ($t) => $t->periode_label)->join(', ') }}</p>
            </div>

            {{-- Menunggu pembayaran --}}
            <template x-if="status === 'pending' && !habis">
                <div class="px-6 py-6">
                    <template x-if="!va">
                        <div>
                            <div class="mx-auto mb-3 flex items-center justify-center gap-2 text-sm font-semibold text-slate-700">
                                <span class="rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-black tracking-wider text-white">QRIS</span> Scan untuk membayar
                            </div>
                            <div x-ref="qr" class="mx-auto aspect-square w-full max-w-[280px] rounded-xl border border-slate-200 bg-white p-3"></div>
                            <button type="button" class="btn btn-secondary btn-sm mt-3" @click="unduh()"><x-icon name="download" class="size-4" /> Simpan gambar QR</button>
                        </div>
                    </template>
                    <template x-if="va">
                        <div>
                            <p class="text-sm text-slate-500">Nomor Virtual Account</p>
                            <p class="mt-1 font-mono text-2xl font-bold tracking-wider text-slate-900" x-text="content"></p>
                            <button type="button" class="btn btn-secondary btn-sm mt-3" @click="navigator.clipboard.writeText(content)">Salin nomor</button>
                        </div>
                    </template>

                    <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-sm text-amber-800">
                        <x-icon name="clock" class="size-4" /> Berlaku <span class="font-mono font-semibold" x-text="sisa"></span>
                    </div>
                    <p class="mt-4 flex items-center justify-center gap-2 text-xs text-slate-500">
                        <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-brand-500"></span></span>
                        Menunggu pembayaran… halaman ini akan diperbarui otomatis.
                    </p>
                </div>
            </template>

            {{-- Berhasil --}}
            <template x-if="status === 'paid'">
                <div class="px-6 py-10">
                    <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-icon name="check" class="size-9" /></div>
                    <p class="text-xl font-bold text-slate-900">Pembayaran berhasil</p>
                    <p class="mt-1 text-sm text-slate-500">Terima kasih, iuran Anda sudah tercatat lunas.</p>
                    <a href="{{ auth()->user()->kartu_keluarga_id ? route('iuran.saya') : route('iuran.transaksi') }}" class="btn btn-primary mt-6">Kembali</a>
                </div>
            </template>

            {{-- Kedaluwarsa / gagal --}}
            <template x-if="status !== 'paid' && (status !== 'pending' || habis)">
                <div class="px-6 py-10">
                    <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-slate-100 text-slate-500"><x-icon name="x" class="size-9" /></div>
                    <p class="text-xl font-bold text-slate-900" x-text="status === 'failed' ? 'Pembayaran gagal' : (status === 'canceled' ? 'Pembayaran dibatalkan' : 'QRIS kedaluwarsa')"></p>
                    <p class="mt-1 text-sm text-slate-500">Jika saldo Anda sudah terpotong, tunggu beberapa menit — status akan diperbarui otomatis. Bila tidak, hubungi pengurus dengan menyebutkan kode transaksi di bawah.</p>
                    @if ((int) auth()->user()->kartu_keluarga_id === (int) $p->kartu_keluarga_id)
                        <a href="{{ route('iuran.saya') }}" class="btn btn-primary mt-6">Buat QRIS baru</a>
                    @endif
                </div>
            </template>

            <div class="border-t border-slate-100 bg-slate-50 px-6 py-3 text-left text-xs text-slate-500">
                <div class="flex justify-between"><span>Kode transaksi</span><span class="font-mono">{{ \Illuminate\Support\Str::limit($p->order_id, 18) }}</span></div>
                @if ($p->reference_no)<div class="flex justify-between"><span>Ref. AINO</span><span class="font-mono">{{ $p->reference_no }}</span></div>@endif
                @if ($p->biaya > 0)<div class="flex justify-between"><span>Iuran + biaya layanan</span><span>{{ rupiah($p->jumlah_iuran) }} + {{ rupiah($p->biaya) }}</span></div>@endif
            </div>
        </div>

        <div class="mt-5 rounded-xl bg-white p-4 text-sm text-slate-600 ring-1 ring-slate-200">
            <p class="mb-2 font-medium text-slate-800">Cara bayar</p>
            <ol class="list-inside list-decimal space-y-1">
                <li>Buka aplikasi m-banking atau e-wallet (GoPay, OVO, DANA, ShopeePay, dll).</li>
                <li>Pilih menu <b>Scan / Bayar QRIS</b>, arahkan ke kode di atas. Jika membuka dari HP yang sama, simpan gambar QR lalu unggah dari galeri.</li>
                <li>Pastikan nominal {{ rupiah($p->total) }}, lalu konfirmasi.</li>
            </ol>
        </div>
    </div>

@endsection
