{{-- Form donasi via QRIS (AINO). $d = Donasi --}}
@php
    $u = auth()->user();
    $minimal = app(\App\Services\PembayaranService::class)->donasiMinimal();
    $uji = (bool) config('aino.donasi_uji');
    $pilihan = collect([10000, 25000, 50000, 100000, 250000, 500000])->filter(fn ($n) => $n >= $minimal)->values();
    if ($uji) {
        $pilihan = $pilihan->prepend(1)->take(6); // Rp1 untuk uji coba pembayaran
    }
@endphp
<form id="donasi" method="post" action="{{ route('publik.donasi.qris', $d) }}" class="card card-body scroll-mt-28 space-y-4 border-brand-200 md:scroll-mt-20"
      x-data="{ nominal: @js(old('nominal', '')), anonim: @js((bool) old('anonim')), kirim: false }" @submit="kirim = true">
    @csrf
    <div>
        <h2 class="flex items-center gap-2 font-semibold text-slate-900">
            <span class="rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-black tracking-wider text-white">QRIS</span> Donasi sekarang
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">Bayar dengan m-banking atau e-wallet apa pun. Donasi otomatis tercatat.</p>
        @if ($uji)
            <p class="mt-2 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 ring-1 ring-amber-200">Mode uji coba aktif: nominal Rp1 bisa dipakai untuk mencoba pembayaran.</p>
        @endif
    </div>

    <div>
        <label class="label">Nominal donasi</label>
        <div class="mb-2 grid grid-cols-3 gap-2">
            @foreach ($pilihan as $n)
                <button type="button" @click="nominal = '{{ $n }}'"
                        class="rounded-lg border px-2 py-2 text-sm font-semibold transition"
                        :class="nominal == '{{ $n }}' ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-slate-200 text-slate-700 hover:border-brand-300'">
                    {{ $n < 1000 ? 'Rp '.$n.' · uji' : ($n >= 1000000 ? ($n / 1000000).' jt' : ($n / 1000).' rb') }}
                </button>
            @endforeach
        </div>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-500">Rp</span>
            <input name="nominal" x-model="nominal" inputmode="numeric" pattern="[0-9.]*" required placeholder="Nominal lain"
                   class="input pl-10 font-semibold @error('nominal') input-error @enderror">
        </div>
        <p class="hint">Minimal {{ rupiah($minimal) }}.@if ((float) config('aino.biaya_persen') > 0) Ditambah biaya layanan {{ config('aino.biaya_persen') }}%.@endif</p>
        @error('nominal') <p class="error">{{ $message }}</p> @enderror
    </div>

    <div x-show="!anonim">
        <label class="label">Nama yang ditampilkan</label>
        <input name="nama" value="{{ old('nama', $u?->kartuKeluarga ? 'Kel. '.$u->kartuKeluarga->nama_kepala : '') }}" maxlength="100" placeholder="mis. Kel. Bpk. Sutrisno" class="input">
    </div>
    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="anonim" value="1" x-model="anonim" class="mt-0.5 rounded border-slate-300 text-brand-700">
        <span>Sembunyikan nama saya <span class="text-slate-500">(tampil sebagai “Donatur anonim”)</span></span>
    </label>
    <div>
        <label class="label">Pesan / doa <span class="font-normal text-slate-400">(opsional, hanya untuk pengurus)</span></label>
        <input name="pesan" value="{{ old('pesan') }}" maxlength="200" class="input">
    </div>

    <button class="btn btn-primary w-full py-2.5" :disabled="kirim">
        <x-icon name="qr" class="size-5" />
        <span x-text="kirim ? 'Membuat QRIS…' : 'Lanjut bayar dengan QRIS'">Lanjut bayar dengan QRIS</span>
    </button>
    <p class="text-center text-[11px] text-slate-400">Pembayaran diproses oleh AINO. Nominal per donatur tidak ditampilkan untuk umum.</p>
</form>
