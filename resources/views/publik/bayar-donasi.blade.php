@extends('layouts.publik')
@section('title', 'Donasi QRIS · '.$d->judul)

@push('head')
    <meta name="robots" content="noindex">
    <script src="{{ asset('js/qrcode.js') }}"></script>
    <script src="{{ asset('js/pembayaran.js') }}?v={{ @filemtime(public_path('js/pembayaran.js')) }}"></script>
@endpush

@section('content')
    @php $kembali = auth()->check() ? route('galang.show', $d) : route('publik.donasi', $d); @endphp
    <div class="mx-auto max-w-md px-4 py-6 sm:py-10" x-data="pembayaran(@js([
            'status' => $p->status,
            'content' => $p->isPending() ? $p->payment_content : null,
            'expired' => $p->expired_at?->toIso8601String(),
            'url' => route('publik.donasi.status', $p),
            'va' => $p->isVa(),
            'namaFile' => 'qris-donasi.png',
        ]))" x-init="mulai()">
        <a href="{{ $kembali }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="arrow-left" class="size-4" /> {{ $d->judul }}</a>

        <div class="card overflow-hidden text-center">
            <div class="border-b border-slate-100 px-6 py-5">
                <p class="text-sm text-slate-500">Donasi untuk <b class="text-slate-700">{{ $d->judul }}</b></p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ rupiah($p->total) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    atas nama {{ $p->donatur_anonim || blank($p->donatur_nama) ? 'Donatur anonim' : $p->donatur_nama }}
                    @if ($p->biaya > 0) · termasuk biaya layanan {{ rupiah($p->biaya) }} @endif
                </p>
            </div>

            <template x-if="status === 'pending' && !habis">
                <div class="px-6 py-6">
                    <div class="mx-auto mb-3 flex items-center justify-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-black tracking-wider text-white">QRIS</span> Scan untuk berdonasi
                    </div>
                    <div x-ref="qr" class="mx-auto aspect-square w-full max-w-[280px] rounded-xl border border-slate-200 bg-white p-3"></div>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" @click="unduh()"><x-icon name="download" class="size-4" /> Simpan gambar QR</button>
                    <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-sm text-amber-800">
                        <x-icon name="clock" class="size-4" /> Berlaku <span class="font-mono font-semibold" x-text="sisa"></span>
                    </div>
                    <p class="mt-4 flex items-center justify-center gap-2 text-xs text-slate-500">
                        <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-brand-500"></span></span>
                        Menunggu pembayaran… halaman ini diperbarui otomatis.
                    </p>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" @click="cek()"><x-icon name="refresh" class="size-4" /> Saya sudah bayar, cek sekarang</button>
                </div>
            </template>

            <template x-if="status === 'paid'">
                <div class="px-6 py-10">
                    <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-rose-50 text-rose-500"><x-icon name="heart" class="size-9" /></div>
                    <p class="text-xl font-bold text-slate-900">Terima kasih atas donasinya!</p>
                    <p class="mt-1 text-sm text-slate-500">Donasi Anda sudah diterima dan tercatat di daftar donatur{{ $p->donatur_anonim ? ' sebagai donatur anonim' : '' }}. Nominal tidak ditampilkan untuk umum.</p>
                    <a href="{{ $kembali }}" class="btn btn-primary mt-6">Lihat penggalangan dana</a>
                    <x-bagikan class="mt-4 justify-center" :judul="$d->judul" :url="route('publik.donasi', $d)" :teks="'🤝 Saya baru saja berdonasi untuk '.$d->judul.'. Yuk ikut bantu!'" />
                </div>
            </template>

            <template x-if="status !== 'paid' && (status !== 'pending' || habis)">
                <div class="px-6 py-10">
                    <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-slate-100 text-slate-500"><x-icon name="x" class="size-9" /></div>
                    <p class="text-xl font-bold text-slate-900" x-text="status === 'failed' ? 'Pembayaran gagal' : (status === 'canceled' ? 'Pembayaran dibatalkan' : 'QRIS kedaluwarsa')"></p>
                    <p class="mt-1 text-sm text-slate-500">Jika saldo sudah terpotong, tunggu beberapa menit — status akan diperbarui otomatis. Bila tidak, hubungi pengurus dengan menyebutkan kode transaksi di bawah.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        <button type="button" class="btn btn-secondary" @click="cek()"><x-icon name="refresh" class="size-4" /> Cek ulang status</button>
                        <a href="{{ $kembali }}#donasi" class="btn btn-primary">Buat QRIS baru</a>
                    </div>
                </div>
            </template>

            <div class="border-t border-slate-100 bg-slate-50 px-6 py-3 text-left text-xs text-slate-500">
                <div class="flex justify-between gap-3"><span>Kode transaksi</span><span class="font-mono">{{ \Illuminate\Support\Str::limit($p->order_id, 18) }}</span></div>
                @if ($p->reference_no)<div class="flex justify-between gap-3"><span>Ref. AINO</span><span class="font-mono">{{ $p->reference_no }}</span></div>@endif
            </div>
        </div>

        <div class="mt-5 rounded-xl bg-white p-4 text-sm text-slate-600 ring-1 ring-slate-200">
            <p class="mb-2 font-medium text-slate-800">Cara bayar</p>
            <ol class="list-inside list-decimal space-y-1">
                <li>Buka aplikasi m-banking atau e-wallet (GoPay, OVO, DANA, ShopeePay, dll).</li>
                <li>Pilih <b>Scan / Bayar QRIS</b> lalu arahkan ke kode di atas. Bila membuka dari HP yang sama, simpan gambar QR lalu unggah dari galeri.</li>
                <li>Pastikan nominal {{ rupiah($p->total) }}, lalu konfirmasi.</li>
            </ol>
        </div>
    </div>
@endsection
