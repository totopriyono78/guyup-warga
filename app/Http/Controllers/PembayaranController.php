<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Services\AinoException;
use App\Services\PembayaranAktifException;
use App\Services\PembayaranService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PembayaranController extends Controller
{
    public function __construct(private readonly PembayaranService $service) {}

    /** Warga memilih tagihan lalu membuat QRIS. */
    public function store(Request $request)
    {
        $user = $this->user();
        abort_unless($user->kartu_keluarga_id, 403);

        $data = $request->validate([
            'tagihan' => ['required', 'array', 'min:1', 'max:120'],
            'tagihan.*' => ['integer'],
            'jumlah' => ['nullable', 'array'],
            'jumlah.*' => ['nullable', 'string', 'max:15'],
        ], ['tagihan.required' => 'Pilih minimal satu tagihan.']);

        $tagihans = Tagihan::query()
            ->where('kartu_keluarga_id', $user->kartu_keluarga_id)
            ->whereIn('id', $data['tagihan'])
            ->belum()
            ->get();

        try {
            $pembayaran = $this->service->buat($user->kartuKeluarga, $tagihans, $user, $data['jumlah'] ?? []);
        } catch (PembayaranAktifException $e) {
            // Arahkan ke QR yang masih aktif bila milik keluarga ini
            if ((int) $e->pembayaran->kartu_keluarga_id === (int) $user->kartu_keluarga_id) {
                return redirect()->route('pembayaran.show', $e->pembayaran)->with('gagal', $e->getMessage());
            }

            return back()->with('gagal', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('gagal', $e->getMessage());
        } catch (AinoException $e) {
            report($e);

            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('pembayaran.show', $pembayaran);
    }

    public function show(Pembayaran $pembayaran)
    {
        $this->pastikanBolehLihat($pembayaran);

        return view('pembayaran.show', [
            'p' => $pembayaran->load(['tagihans', 'kartuKeluarga.rumah.blok.rt']),
        ]);
    }

    /** Dipanggil berkala oleh halaman QR untuk mengetahui apakah sudah dibayar. */
    public function status(Pembayaran $pembayaran)
    {
        $this->pastikanBolehLihat($pembayaran);

        // Tanya AINO paling sering sekali per 10 detik per transaksi
        // termasuk yang sudah ditandai kedaluwarsa: pembayaran bisa dilaporkan lunas terlambat (maks. 2 hari)
        $bisaDicek = in_array($pembayaran->status, ['pending', 'expired'], true) && $pembayaran->created_at?->gt(now()->subDays(2));
        if ($bisaDicek && Cache::add('cek-aino:'.$pembayaran->id, 1, 10)) {
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
            'paid_at' => $pembayaran->paid_at?->toIso8601String(),
        ]);
    }

    /** Pengurus: cek manual status transaksi ke AINO. */
    public function cek(Pembayaran $pembayaran)
    {
        $this->pastikanKelolaKeluarga($pembayaran->kartuKeluarga);

        try {
            $hasil = $this->service->sinkron($pembayaran);
        } catch (AinoException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', "Status transaksi: {$hasil->statusLabel()}.");
    }

    private function pastikanBolehLihat(Pembayaran $p): void
    {
        $user = $this->user();

        if ((int) $user->kartu_keluarga_id === (int) $p->kartu_keluarga_id) {
            return;
        }

        $this->pastikanKelolaKeluarga($p->kartuKeluarga);
    }
}
