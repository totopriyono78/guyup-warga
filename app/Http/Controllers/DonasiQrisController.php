<?php

namespace App\Http\Controllers;

use App\Models\Donasi;
use App\Models\Pembayaran;
use App\Services\AinoException;
use App\Services\PembayaranService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Donasi lewat QRIS dari halaman umum (boleh tanpa login).
 * Halaman QR diakses lewat kode transaksi (UUID acak) sehingga tidak bisa ditebak.
 */
class DonasiQrisController extends Controller
{
    public function __construct(private readonly PembayaranService $service) {}

    public function store(Request $request, Donasi $donasi)
    {
        abort_unless($donasi->bolehDilihat($request->user()), 404);

        $data = $request->validate([
            'nominal' => ['required', 'string', 'max:15'],
            'nama' => ['nullable', 'string', 'max:100'],
            'anonim' => ['nullable', 'boolean'],
            'pesan' => ['nullable', 'string', 'max:200'],
        ], ['nominal.required' => 'Isi nominal donasi.']);

        try {
            $p = $this->service->buatDonasi(
                $donasi, $data['nominal'], $data['nama'] ?? null, $request->boolean('anonim'),
                $data['pesan'] ?? null, $request->user(), $request->session()->getId(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['nominal' => $e->getMessage()]);
        } catch (AinoException $e) {
            report($e);

            return back()->withInput()->withErrors(['nominal' => 'QRIS belum bisa dibuat saat ini. Silakan coba lagi atau gunakan cara donasi lain.']);
        }

        // simpan di sesi supaya pengunjung bisa kembali ke QR-nya
        $request->session()->push('donasi_qris', $p->order_id);

        return redirect()->route('publik.donasi.bayar', $p);
    }

    public function show(Pembayaran $pembayaran)
    {
        abort_unless($pembayaran->isDonasi() && $pembayaran->donasi, 404);

        return view('publik.bayar-donasi', ['p' => $pembayaran, 'd' => $pembayaran->donasi]);
    }

    public function status(Pembayaran $pembayaran)
    {
        abort_unless($pembayaran->isDonasi(), 404);

        // tanya AINO paling sering sekali per 10 detik per transaksi
        if ($pembayaran->isPending() && Cache::add('cek-aino:'.$pembayaran->id, 1, 10)) {
            try {
                $pembayaran = $this->service->sinkron($pembayaran);
            } catch (AinoException $e) {
                report($e);
            }
        }

        return response()->json([
            'status' => $pembayaran->status,
            'label' => $pembayaran->statusLabel(),
            'kedaluwarsa' => $pembayaran->isKedaluwarsa(),
        ]);
    }
}
