{{--
  Tombol pilih berkas yang rapi di HP (menggantikan tampilan bawaan "Choose File / No file chosen").
  Atribut lain (name, accept, multiple, required, x-on:change, ...) diteruskan ke <input type="file">.
--}}
@props(['label' => 'Pilih foto', 'hint' => null, 'icon' => 'photo'])
<label class="file-pick" x-data="{ n: 0, nama: '' }">
    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-brand-700 shadow-sm ring-1 ring-slate-200">
        <x-icon :name="$icon" class="size-5" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="block truncate font-medium text-slate-800" x-text="n > 1 ? n + ' berkas dipilih' : (n === 1 ? nama : @js($label))">{{ $label }}</span>
        <span class="block truncate text-xs text-slate-500" x-text="n ? 'Ketuk untuk mengganti' : @js($hint ?? 'Dari galeri atau kamera')">{{ $hint ?? 'Dari galeri atau kamera' }}</span>
    </span>
    <input type="file" {{ $attributes->merge(['class' => 'sr-only']) }}
           x-on:change="n = $event.target.files.length; nama = n ? $event.target.files[0].name : ''; $dispatch('berkas-dipilih', n)">
</label>
