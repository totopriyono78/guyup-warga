@if (session('sukses'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
         class="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <x-icon name="check" class="mt-0.5 size-5 shrink-0" />
        <div class="flex-1">{{ session('sukses') }}</div>
        <button type="button" @click="show = false" class="text-emerald-700/70 hover:text-emerald-900"><x-icon name="x" class="size-4" /></button>
    </div>
@endif
@if (session('gagal'))
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <x-icon name="x" class="mt-0.5 size-5 shrink-0" />
        <div class="flex-1">{{ session('gagal') }}</div>
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <p class="font-medium">Periksa kembali isian berikut:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
