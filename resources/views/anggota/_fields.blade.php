{{-- Field data diri; $a = model AnggotaKeluarga, $p = prefix nama input, $denganHubungan = bool --}}
@php $n = fn ($f) => $p.$f; @endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-6" x-data="{ pratinjau: null }">
    <div class="sm:col-span-6 flex items-center gap-4">
        <div class="size-16 shrink-0 overflow-hidden rounded-full sm:size-20 bg-slate-100 ring-1 ring-slate-200">
            <template x-if="pratinjau"><img :src="pratinjau" class="size-full object-cover" alt=""></template>
            <template x-if="!pratinjau">
                @if ($a->fotoUrl())
                    <img src="{{ $a->fotoUrl() }}" class="size-full object-cover" alt="">
                @else
                    <div class="flex size-full items-center justify-center text-slate-400"><x-icon name="user" class="size-8" /></div>
                @endif
            </template>
        </div>
        <div class="min-w-0 flex-1">
            <label class="label">Foto wajah</label>
            <x-pilih-file :name="$n('foto')" accept="image/*" label="Pilih foto wajah"
                          x-on:change="pratinjau = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
            @if ($a->foto && $p === '')
                <label class="mt-1 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="hapus_foto" value="1" class="rounded border-slate-300"> Hapus foto</label>
            @endif
            @error($n('foto')) <p class="error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="sm:col-span-4">
        <label class="label">Nama lengkap <span class="text-rose-500">*</span></label>
        <input name="{{ $n('nama') }}" value="{{ old($n('nama'), $a->nama) }}" required class="input @error($n('nama')) input-error @enderror">
        @error($n('nama')) <p class="error">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label class="label">Jenis kelamin <span class="text-rose-500">*</span></label>
        <select name="{{ $n('jenis_kelamin') }}" class="input">
            <option value="L" @selected(old($n('jenis_kelamin'), $a->jenis_kelamin) === 'L')>Laki-laki</option>
            <option value="P" @selected(old($n('jenis_kelamin'), $a->jenis_kelamin) === 'P')>Perempuan</option>
        </select>
    </div>
    <div class="sm:col-span-3">
        <label class="label">NIK</label>
        <input name="{{ $n('nik') }}" value="{{ old($n('nik'), $a->nik) }}" inputmode="numeric" maxlength="16" placeholder="16 digit" class="input @error($n('nik')) input-error @enderror">
        @error($n('nik')) <p class="error">{{ $message }}</p> @enderror
    </div>
    @if ($denganHubungan)
        <div class="sm:col-span-3">
            <label class="label">Hubungan dalam keluarga <span class="text-rose-500">*</span></label>
            <select name="hubungan" class="input @error('hubungan') input-error @enderror">
                @foreach (\App\Models\AnggotaKeluarga::HUBUNGAN as $h)
                    <option value="{{ $h }}" @selected(old('hubungan', $a->hubungan) === $h)>{{ $h }}</option>
                @endforeach
            </select>
            @error('hubungan') <p class="error">{{ $message }}</p> @enderror
        </div>
    @endif
    <div class="sm:col-span-3">
        <label class="label">Tempat lahir</label>
        <input name="{{ $n('tempat_lahir') }}" value="{{ old($n('tempat_lahir'), $a->tempat_lahir) }}" class="input">
    </div>
    <div class="sm:col-span-3">
        <label class="label">Tanggal lahir</label>
        <input type="date" name="{{ $n('tanggal_lahir') }}" value="{{ old($n('tanggal_lahir'), $a->tanggal_lahir?->format('Y-m-d')) }}" class="input">
    </div>
    <div class="sm:col-span-2">
        <label class="label">Agama</label>
        <select name="{{ $n('agama') }}" class="input">
            <option value="">—</option>
            @foreach (\App\Models\AnggotaKeluarga::AGAMA as $x)
                <option value="{{ $x }}" @selected(old($n('agama'), $a->agama) === $x)>{{ $x }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Status perkawinan</label>
        <select name="{{ $n('status_perkawinan') }}" class="input">
            <option value="">—</option>
            @foreach (\App\Models\AnggotaKeluarga::STATUS_PERKAWINAN as $x)
                <option value="{{ $x }}" @selected(old($n('status_perkawinan'), $a->status_perkawinan) === $x)>{{ $x }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Pendidikan</label>
        <select name="{{ $n('pendidikan') }}" class="input">
            <option value="">—</option>
            @foreach (\App\Models\AnggotaKeluarga::PENDIDIKAN as $x)
                <option value="{{ $x }}" @selected(old($n('pendidikan'), $a->pendidikan) === $x)>{{ $x }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-3">
        <label class="label">Pekerjaan</label>
        <input name="{{ $n('pekerjaan') }}" value="{{ old($n('pekerjaan'), $a->pekerjaan) }}" class="input">
    </div>
    <div class="sm:col-span-3">
        <label class="label">No. HP</label>
        <input name="{{ $n('no_hp') }}" value="{{ old($n('no_hp'), $a->no_hp) }}" inputmode="tel" class="input">
    </div>
</div>
