{{-- Field jenis iuran. $t = TarifIuran|null. Butuh x-data { frek, sukarela } pada elemen induk. --}}
@php
    $u = auth()->user();
    $bulanList = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('F')]);
@endphp
<div class="sm:col-span-6">
    <label class="label">Nama iuran</label>
    <input name="nama" value="{{ $t ? $t->nama : old('nama') }}" placeholder="mis. Iuran kebersihan, Sumbangan HUT RI" class="input" required maxlength="255">
</div>
<div class="sm:col-span-3">
    <label class="label">Frekuensi</label>
    <select name="frekuensi" x-model="frek" class="input">
        @foreach (\App\Models\TarifIuran::FREKUENSI as $k => $l)
            <option value="{{ $k }}">{{ $l }}</option>
        @endforeach
    </select>
</div>
<div class="sm:col-span-3" x-show="frek === 'tahunan'" x-cloak>
    <label class="label">Ditagih setiap bulan</label>
    <select name="bulan_tagih" class="input">
        @foreach ($bulanList as $m => $nama)
            <option value="{{ $m }}" @selected((int) ($t ? $t->bulan_tagih : old('bulan_tagih', 1)) === $m)>{{ $nama }}</option>
        @endforeach
    </select>
</div>
<div class="sm:col-span-3" x-show="frek === 'insidental'" x-cloak>
    <label class="label">Tenggat bayar</label>
    <input type="date" name="tenggat" value="{{ $t ? $t->tenggat?->format('Y-m-d') : old('tenggat') }}" class="input">
</div>
<div class="sm:col-span-3">
    <label class="label" x-text="sukarela ? 'Nominal minimal (boleh 0)' : 'Nominal per KK'">Nominal per KK</label>
    <input type="number" name="nominal" value="{{ $t ? $t->nominal : old('nominal') }}" min="0" step="500" class="input" required>
</div>
<div class="sm:col-span-3">
    <label class="label">Berlaku untuk</label>
    @if ($u->isAdmin())
        <select name="rt_id" class="input">
            <option value="">Semua RT (iuran RW)</option>
            @foreach ($rts as $rt)
                <option value="{{ $rt->id }}" @selected($t && (int) $t->rt_id === $rt->id)>RT {{ $rt->nomor }} saja</option>
            @endforeach
        </select>
    @else
        <input class="input" value="RT {{ $u->rt?->nomor }} saja" disabled>
    @endif
</div>
<label class="flex items-center gap-2 text-sm sm:col-span-6">
    <input type="checkbox" name="sukarela" value="1" x-model="sukarela" class="rounded border-slate-300 text-brand-700">
    Sukarela — warga mengisi sendiri nominalnya
</label>
<div class="sm:col-span-6">
    <label class="label">Keterangan (tampil ke warga)</label>
    <input name="keterangan" value="{{ $t ? $t->keterangan : old('keterangan') }}" maxlength="500" class="input" placeholder="mis. untuk lomba & tasyakuran 17 Agustus">
</div>
