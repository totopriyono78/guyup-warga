@extends('layouts.app')
@section('title', 'Jenis Iuran')

@section('content')
    @php
        $u = auth()->user();
        $bulan = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('F')]);
        $badge = ['bulanan' => 'badge-blue', 'tahunan' => 'badge-amber', 'insidental' => 'badge-red'];
    @endphp

    <x-page-header judul="Kelola Iuran" sub="Buat jenis iuran: bulanan, tahunan, atau insidental (acara). Tiap jenis menjadi tagihan terpisah untuk setiap KK." />
    @include('iuran._tabs')

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($tarif->groupBy('frekuensi') as $frek => $daftar)
                <div class="card overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Iuran {{ \App\Models\TarifIuran::FREKUENSI[$frek] ?? $frek }}
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($daftar as $t)
                            @php
                                $boleh = $t->rt_id ? $u->canManageRt($t->rt_id) : $u->isAdmin();
                                $bolehTerbit = $t->aktif && ($t->rt_id ? $u->canManageRt($t->rt_id) : $u->isPengurus());
                                $r = $ringkas->get($t->id);
                            @endphp
                            <div x-data="{ edit: false, frek: @js($t->frekuensi), sukarela: @js($t->sukarela) }">
                                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">
                                            {{ $t->nama }}
                                            <span class="badge {{ $badge[$t->frekuensi] ?? 'badge-slate' }} ml-1">{{ $t->frekuensiLabel() }}</span>
                                            @if ($t->sukarela) <span class="badge badge-green">Sukarela</span> @endif
                                            @unless ($t->aktif) <span class="badge badge-slate">Nonaktif</span> @endunless
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ $t->rt ? 'Khusus RT '.$t->rt->nomor : 'Semua RT' }}
                                            @if ($t->frekuensi === 'insidental')
                                                · {{ $t->tenggat ? 'Tenggat '.$t->tenggat->translatedFormat('j M Y') : 'Tanpa tenggat' }}
                                                · {{ $t->diterbitkan_pada ? 'Terbit '.$t->diterbitkan_pada->translatedFormat('j M Y') : 'Belum diterbitkan' }}
                                            @endif
                                            {{ $t->keterangan ? '· '.$t->keterangan : '' }}
                                        </p>
                                        @if ($r)
                                            <p class="mt-1 text-xs text-slate-600">
                                                {{ $r->lunas }}/{{ $r->jumlah }} tagihan lunas · terkumpul <b>{{ rupiah($r->terkumpul) }}</b>
                                                <a href="{{ route('iuran.index', ['jenis' => $t->id]) }}" class="ml-1 text-brand-700 underline">lihat rekap</a>
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-slate-900">{{ $t->sukarela ? ($t->nominal > 0 ? 'min. '.rupiah($t->nominal) : 'Bebas') : rupiah($t->nominal) }}</p>
                                        <p class="text-[11px] text-slate-500">per KK</p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        @if ($bolehTerbit)
                                            <form method="post" action="{{ route('tarif.terbitkan', $t) }}"
                                                  onsubmit="return confirm(@js('Terbitkan tagihan “'.$t->nama.'” ke semua KK'.($t->rt ? ' di RT '.$t->rt->nomor : ($u->isPengurusRt() ? ' di RT Anda' : '')).'?'))">
                                                @csrf
                                                <button class="btn btn-sm {{ $t->frekuensi === 'insidental' && ! $t->diterbitkan_pada ? 'btn-primary' : 'btn-secondary' }}"
                                                        title="{{ $t->frekuensi === 'insidental' ? 'Buat tagihan acara ini untuk semua KK' : 'Buat tagihan periode berjalan sekarang (tanpa menunggu jadwal otomatis)' }}">
                                                    {{ $t->frekuensi === 'insidental' ? ($t->diterbitkan_pada ? 'Terbitkan ke KK baru' : 'Terbitkan tagihan') : 'Terbitkan sekarang' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($boleh)
                                            <button type="button" class="btn btn-ghost btn-sm" @click="edit = !edit" title="Ubah"><x-icon name="pencil" class="size-4" /></button>
                                            <form method="post" action="{{ route('tarif.destroy', $t) }}" onsubmit="return confirm('Hapus jenis iuran ini? Bila sudah ada tagihan, jenis iuran hanya dinonaktifkan.')">
                                                @csrf @method('delete')
                                                <button class="btn btn-ghost btn-sm text-rose-600" title="Hapus"><x-icon name="trash" class="size-4" /></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                @if ($boleh)
                                    <form x-cloak x-show="edit" method="post" action="{{ route('tarif.update', $t) }}" class="grid gap-3 bg-slate-50 px-5 py-4 sm:grid-cols-6">
                                        @csrf @method('put')
                                        @include('iuran._form_jenis', ['t' => $t])
                                        <label class="flex items-center gap-2 text-sm sm:col-span-6"><input type="checkbox" name="aktif" value="1" @checked($t->aktif) class="rounded border-slate-300 text-brand-700"> Aktif</label>
                                        <div class="sm:col-span-6"><button class="btn btn-primary btn-sm">Simpan</button></div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card"><x-empty icon="wallet" judul="Belum ada jenis iuran">Contoh: Kebersihan Rp20.000 (bulanan), Iuran tahunan lingkungan (tahunan), Sumbangan HUT RI (insidental, sukarela).</x-empty></div>
            @endforelse

            @if ($rts->isNotEmpty() && $tarif->where('frekuensi', 'bulanan')->isNotEmpty())
                <div class="rounded-xl bg-slate-50 px-5 py-3 text-sm text-slate-600 ring-1 ring-slate-200">
                    Total iuran bulanan wajib per KK:
                    @foreach ($rts as $rt)
                        @php [$total] = app(\App\Services\TagihanService::class)->hitung($rt->id); @endphp
                        <span class="mr-3 whitespace-nowrap">RT {{ $rt->nomor }} <b>{{ rupiah($total) }}</b></span>
                    @endforeach
                </div>
            @endif
        </div>

        <form method="post" action="{{ route('tarif.store') }}" class="card card-body h-fit space-y-3 sm:grid sm:grid-cols-6 sm:gap-3 sm:space-y-0"
              x-data="{ frek: @js(old('frekuensi', 'bulanan')), sukarela: @js((bool) old('sukarela')) }">
            @csrf
            <h2 class="card-title sm:col-span-6">Tambah jenis iuran</h2>
            @include('iuran._form_jenis', ['t' => null])
            <div class="sm:col-span-6"><button class="btn btn-primary w-full">Tambah</button></div>
            <div class="hint space-y-1 sm:col-span-6">
                <p><b>Bulanan</b>: tagihan dibuat otomatis tiap tanggal 1.</p>
                <p><b>Tahunan</b>: tagihan dibuat otomatis pada bulan yang dipilih.</p>
                <p><b>Insidental</b>: untuk acara/kebutuhan khusus; tagihan dibuat saat Anda klik “Terbitkan tagihan”.</p>
                <p><b>Sukarela</b>: warga mengisi nominal sendiri (minimal sesuai nominal yang diisi, boleh 0).</p>
            </div>
        </form>
    </div>
@endsection
