@props(['icon' => 'search', 'judul' => 'Belum ada data'])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="mb-3 rounded-full bg-slate-100 p-3 text-slate-400"><x-icon :name="$icon" class="size-6" /></div>
    <p class="font-medium text-slate-700">{{ $judul }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-1 max-w-sm text-sm text-slate-500">{{ $slot }}</div>
    @endif
</div>
