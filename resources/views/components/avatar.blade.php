@props(['src' => null, 'nama' => '', 'size' => 'size-10', 'rounded' => 'rounded-full'])
@if ($src)
    <img src="{{ $src }}" alt="Foto {{ $nama }}" loading="lazy"
         {{ $attributes->merge(['class' => "$size $rounded shrink-0 object-cover bg-slate-100 ring-1 ring-slate-200"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size $rounded shrink-0 inline-flex items-center justify-center bg-brand-100 font-semibold text-brand-800 ring-1 ring-brand-200"]) }}
          title="{{ $nama }}">
        <span class="text-[0.8em]">{{ inisial($nama) }}</span>
    </span>
@endif
