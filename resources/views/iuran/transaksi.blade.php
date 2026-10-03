@extends('layouts.app')
@section('title', 'Transaksi QRIS')

@section('content')
    <x-page-header judul="Kelola Iuran" sub="Riwayat transaksi QRIS melalui AINO Payment Gateway." />
    @include('iuran._tabs')

    <form method="get" class="mb-4 flex flex-wrap gap-2">
        @if (auth()->user()->isAdmin())
            <select name="rt" class="input w-auto" onchange="this.form.submit()">
                <option value="">Semua RT</option>
                @foreach ($rts as $rt)
                    <option value="{{ $rt->id }}" @selected($rtId === $rt->id)>RT {{ $rt->nomor }}</option>
                @endforeach
            </select>
        @endif
        <select name="status" class="input w-auto" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (\App\Models\Pembayaran::STATUS as $k => $l)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    <div class="card overflow-hidden">
        @if ($transaksi->isEmpty())
            <x-empty icon="qr" judul="Belum ada transaksi QRIS" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Waktu</th><th>Keluarga</th><th>Periode</th><th class="text-right">Total</th><th>Status</th><th>Ref. AINO</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($transaksi as $p)
                            <tr>
                                <td class="whitespace-nowrap">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $p->kartuKeluarga->nama_kepala }} <span class="text-xs text-slate-400">{{ $p->kartuKeluarga->rumah?->kode }}</span></td>
                                <td class="text-xs">{{ $p->tagihans->map(fn ($t) => $t->periode_label)->join(', ') }}</td>
                                <td class="whitespace-nowrap text-right font-medium">{{ rupiah($p->total) }}</td>
                                <td>
                                    <span class="badge {{ ['paid' => 'badge-green', 'pending' => 'badge-amber'][$p->status] ?? 'badge-slate' }}">{{ $p->statusLabel() }}</span>
                                    @if ($p->paid_at) <p class="text-[11px] text-slate-500">{{ $p->paid_at->format('d/m H:i') }}</p> @endif
                                </td>
                                <td class="font-mono text-xs">{{ $p->reference_no ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($p->isPending())
                                        <form method="post" action="{{ route('pembayaran.cek', $p) }}">
                                            @csrf <button class="btn btn-ghost btn-sm"><x-icon name="refresh" class="size-4" /> Cek status</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $transaksi->links() }}</div>
        @endif
    </div>
@endsection
