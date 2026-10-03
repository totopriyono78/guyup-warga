<?php

namespace App\Services;

use App\Models\Donasi;
use App\Models\DonasiDonatur;
use App\Models\KartuKeluarga;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PembayaranService
{
    public function __construct(private readonly AinoClient $aino) {}

    public function biaya(int $jumlah): int
    {
        $persen = (float) config('aino.biaya_persen', 0);

        return $persen > 0 ? (int) ceil($jumlah * $persen / 100) : 0;
    }

    public function callbackUrl(): string
    {
        return config('aino.callback_url') ?: route('aino.notify');
    }

    /**
     * Membuat transaksi QRIS untuk satu atau beberapa tagihan milik satu KK.
     *
     * @param  Collection<int, Tagihan>  $tagihans
     * @param  array<int|string, int|string|null>  $jumlahSukarela  nominal pilihan warga untuk tagihan sukarela [tagihan_id => nominal]
     *
     * @throws AinoException|InvalidArgumentException
     */
    public function buat(KartuKeluarga $kk, Collection $tagihans, ?User $user = null, array $jumlahSukarela = []): Pembayaran
    {
        $tagihans = $tagihans->filter(fn (Tagihan $t) => (int) $t->kartu_keluarga_id === (int) $kk->id && ! $t->isLunas())->values();

        if ($tagihans->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada tagihan yang perlu dibayar.');
        }

        // Nominal per tagihan: wajib = nominal tagihan, sukarela = pilihan warga (>= minimal)
        $rincian = [];
        foreach ($tagihans as $t) {
            $rincian[$t->id] = $this->nominalBayar($t, $jumlahSukarela[$t->id] ?? null);
        }
        ksort($rincian);
        $ids = array_keys($rincian);

        // Satu KK hanya boleh membuat QR satu per satu (hindari klik ganda / QR ganda)
        $lock = Cache::lock('buat-qris:'.$kk->id, 30);
        if (! $lock->get()) {
            throw new InvalidArgumentException('QRIS sedang dibuat, tunggu beberapa detik lalu muat ulang halaman.');
        }

        try {
            $aktif = $this->pembayaranAktif($ids);

            if ($aktif->isNotEmpty()) {
                $sama = $aktif->first(fn (Pembayaran $p) => (int) $p->kartu_keluarga_id === (int) $kk->id
                    && $p->expired_at?->gt(now()->addMinute())
                    && $p->tagihans->mapWithKeys(fn ($t) => [$t->id => (int) $t->pivot->nominal])->sortKeys()->all() === $rincian);

                if ($sama) {
                    return $sama; // pakai lagi QR yang masih berlaku
                }

                throw new PembayaranAktifException($aktif->first());
            }

            return $this->buatBaru($kk, $user, $rincian);
        } finally {
            $lock->release();
        }
    }

    /**
     * Membuat transaksi QRIS untuk donasi / penggalangan dana.
     * Donatur dicatat otomatis setelah pembayaran terverifikasi lunas (lihat terapkan()).
     *
     * @throws AinoException|InvalidArgumentException
     */
    public function buatDonasi(Donasi $donasi, int|string $nominal, ?string $nama, bool $anonim, ?string $pesan, ?User $user, string $kunci): Pembayaran
    {
        if (! $donasi->terima_qris || ! $donasi->berjalan()) {
            throw new InvalidArgumentException('Penggalangan dana ini sudah ditutup atau tidak menerima donasi QRIS.');
        }

        $nominal = (int) preg_replace('/\D/', '', (string) $nominal);
        $minimal = $this->donasiMinimal();
        if ($nominal < $minimal) {
            throw new InvalidArgumentException('Nominal donasi minimal '.rupiah($minimal).'.');
        }
        if ($nominal > 100_000_000) {
            throw new InvalidArgumentException('Nominal terlalu besar. Untuk donasi besar silakan hubungi pengurus.');
        }

        // cegah klik ganda dari pengunjung yang sama
        $lock = Cache::lock('buat-qris-donasi:'.$kunci, 15);
        if (! $lock->get()) {
            throw new InvalidArgumentException('QRIS sedang dibuat, tunggu beberapa detik.');
        }

        try {
            $biaya = $this->biaya($nominal);
            $total = $nominal + $biaya;
            $orderId = (string) Str::uuid();
            $respons = $this->aino->generate($orderId, $total, $this->callbackUrl());
            $kk = $user?->kartuKeluarga;

            return Pembayaran::query()->create([
                'kartu_keluarga_id' => $kk?->id,
                'donasi_id' => $donasi->id,
                'donatur_nama' => filled($nama) ? mb_substr(trim($nama), 0, 255) : ($kk ? 'Kel. '.$kk->nama_kepala : null),
                'donatur_anonim' => $anonim,
                'donatur_pesan' => filled($pesan) ? mb_substr(trim($pesan), 0, 255) : null,
                'user_id' => $user?->id,
                'order_id' => $orderId,
                'reference_no' => AinoClient::referenceNo($respons),
                'payment_type' => $respons['paymentType'] ?? config('aino.payment_type', 'qr'),
                'jumlah_iuran' => $nominal,
                'biaya' => $biaya,
                'total' => $total,
                'status' => 'pending',
                'payment_content' => $respons['paymentContent'],
                'expired_at' => $this->parseWaktu($respons['expiryDate'] ?? null)
                    ?? now()->addMinutes((int) config('aino.default_expiry_minutes', 15)),
                'response_generate' => $respons,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function donasiMinimal(): int
    {
        if (config('aino.donasi_uji')) {
            return 1; // mode uji coba: boleh Rp1
        }

        return max(1, (int) config('aino.donasi_minimal', 10000));
    }

    /**
     * Transaksi QRIS yang masih bisa dibayar (termasuk masa tenggang 5 menit setelah
     * kedaluwarsa, karena pembayaran bisa terlambat diproses) dan mencakup salah satu tagihan ini.
     *
     * @param  array<int>  $tagihanIds
     * @return Collection<int, Pembayaran>
     */
    public function pembayaranAktif(array $tagihanIds): Collection
    {
        return Pembayaran::query()
            ->where('status', 'pending')
            ->where('expired_at', '>', now()->subMinutes(5))
            ->whereHas('tagihans', fn ($q) => $q->whereIn('tagihans.id', $tagihanIds))
            ->with('tagihans:id,periode,judul')
            ->latest()
            ->get();
    }

    /** Nominal yang harus dibayar untuk satu tagihan. */
    public function nominalBayar(Tagihan $t, int|string|null $pilihan = null): int
    {
        if (! $t->sukarela) {
            return (int) $t->nominal;
        }

        $nominal = (int) preg_replace('/\D/', '', (string) $pilihan);
        $minimal = max(1, (int) $t->nominal);

        if ($nominal < $minimal) {
            throw new InvalidArgumentException("Isi nominal untuk “{$t->periode_label}” minimal ".rupiah($minimal).'.');
        }
        if ($nominal > 100_000_000) {
            throw new InvalidArgumentException('Nominal terlalu besar.');
        }

        return $nominal;
    }

    /** @param  array<int, int>  $rincian  [tagihan_id => nominal] */
    private function buatBaru(KartuKeluarga $kk, ?User $user, array $rincian): Pembayaran
    {
        $jumlah = (int) array_sum($rincian);
        $biaya = $this->biaya($jumlah);
        $total = $jumlah + $biaya;
        $orderId = (string) Str::uuid();

        $respons = $this->aino->generate($orderId, $total, $this->callbackUrl());

        return DB::transaction(function () use ($kk, $user, $orderId, $respons, $jumlah, $biaya, $total, $rincian) {
            $pembayaran = Pembayaran::query()->create([
                'kartu_keluarga_id' => $kk->id,
                'user_id' => $user?->id,
                'order_id' => $orderId,
                'reference_no' => AinoClient::referenceNo($respons),
                'payment_type' => $respons['paymentType'] ?? config('aino.payment_type', 'qr'),
                'jumlah_iuran' => $jumlah,
                'biaya' => $biaya,
                'total' => $total,
                'status' => 'pending',
                'payment_content' => $respons['paymentContent'],
                'expired_at' => $this->parseWaktu($respons['expiryDate'] ?? null)
                    ?? now()->addMinutes((int) config('aino.default_expiry_minutes', 15)),
                'response_generate' => $respons,
            ]);

            $pembayaran->tagihans()->attach(collect($rincian)->map(fn ($n) => ['nominal' => $n])->all());

            return $pembayaran;
        });
    }

    /**
     * Cek status ke AINO (Query Payment) lalu terapkan ke database.
     * Ini satu-satunya jalur yang menandai pembayaran "paid", karena
     * callback Finish Notify dari AINO tidak memiliki tanda tangan.
     *
     * @throws AinoException
     */
    public function sinkron(Pembayaran $pembayaran): Pembayaran
    {
        if ($pembayaran->isPaid()) {
            return $pembayaran;
        }

        // reference_no bisa kosong bila respons Generate memakai nama kolom lain: ambil ulang dari respons tersimpan
        if (blank($pembayaran->reference_no) && is_array($pembayaran->response_generate)) {
            $ref = AinoClient::referenceNo($pembayaran->response_generate);
            if ($ref) {
                $pembayaran->forceFill(['reference_no' => $ref])->save();
            }
        }

        $respons = $this->aino->inquiry($pembayaran->order_id, (string) $pembayaran->reference_no);

        $status = AinoClient::statusDariInquiry($respons);

        // Pastikan nominal & order cocok sebelum menerima status lunas
        if ($status === 'paid') {
            $nominal = AinoClient::nominal($respons);
            $partnerRef = AinoClient::partnerRef($respons);
            // bandingkan tanpa beda huruf besar/kecil & tanda hubung (beberapa sistem menghapus "-" pada UUID)
            $norm = fn (?string $s) => strtolower(str_replace('-', '', (string) $s));
            $refCocok = $partnerRef === null || $norm($partnerRef) === $norm($pembayaran->order_id);

            if (($nominal !== null && $nominal !== (int) $pembayaran->total) || ! $refCocok) {
                Log::error('AINO: data pembayaran tidak cocok, status paid ditolak', [
                    'order_id' => $pembayaran->order_id,
                    'nominal_aino' => $nominal,
                    'nominal_lokal' => $pembayaran->total,
                    'partner_ref' => $partnerRef,
                ]);

                $pembayaran->forceFill(['response_terakhir' => $respons])->save();

                return $pembayaran;
            }
        }

        // Status pending yang sudah melewati batas waktu dianggap kedaluwarsa
        // (tetap bisa berubah menjadi paid bila AINO kemudian melaporkan lunas)
        if ($status === 'pending' && $pembayaran->expired_at && $pembayaran->expired_at->lt(now()->subMinutes(5))) {
            $status = 'expired';
        }

        return $this->terapkan($pembayaran, $status, $respons, $this->parseWaktu($respons['paidTime'] ?? null));
    }

    public function terapkan(Pembayaran $pembayaran, string $status, array $respons = [], ?Carbon $paidAt = null): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $status, $respons, $paidAt) {
            /** @var Pembayaran $p */
            $p = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);

            // Idempoten: status lunas bersifat final; status gagal/kedaluwarsa hanya boleh berubah menjadi lunas
            if ($p->isPaid() || $p->status === $status || (! $p->isPending() && $status !== 'paid')) {
                if ($respons) {
                    $p->forceFill(['response_terakhir' => $respons])->save();
                }

                return $p;
            }

            $p->forceFill([
                'status' => $status,
                'response_terakhir' => $respons ?: $p->response_terakhir,
                'paid_at' => $status === 'paid' ? ($paidAt ?? now()) : null,
            ])->save();

            if ($status === 'paid' && $p->isDonasi()) {
                $this->catatDonatur($p);

                return $p;
            }

            if ($status === 'paid') {
                $metode = $p->isVa() ? 'va' : 'qris';

                foreach ($p->tagihans()->lockForUpdate()->get() as $tagihan) {
                    if ($tagihan->isLunas()) {
                        Log::warning('AINO: tagihan sudah lunas sebelumnya (kemungkinan bayar ganda)', [
                            'order_id' => $p->order_id, 'tagihan_id' => $tagihan->id,
                        ]);

                        continue;
                    }

                    $tagihan->forceFill([
                        'status' => 'lunas',
                        'metode' => $metode,
                        'nominal_dibayar' => $tagihan->pivot->nominal ?? $tagihan->nominal,
                        'dibayar_pada' => $p->paid_at,
                        'catatan' => 'Ref AINO '.$p->reference_no,
                    ])->save();
                }
            }

            return $p;
        });
    }

    /** Donasi QRIS yang lunas otomatis masuk ke daftar donatur (sekali saja per transaksi). */
    private function catatDonatur(Pembayaran $p): void
    {
        if (! $p->donasi_id || DonasiDonatur::query()->where('pembayaran_id', $p->id)->exists()) {
            return;
        }

        DonasiDonatur::query()->create([
            'donasi_id' => $p->donasi_id,
            'pembayaran_id' => $p->id,
            'nama' => $p->donatur_nama ?: 'Donatur',
            'anonim' => (bool) $p->donatur_anonim || blank($p->donatur_nama),
            'nominal' => (int) $p->jumlah_iuran,   // tanpa biaya layanan
            'tanggal' => ($p->paid_at ?? now())->toDateString(),
            'kartu_keluarga_id' => $p->kartu_keluarga_id,
            'catatan' => trim('QRIS · Ref '.$p->reference_no.($p->donatur_pesan ? ' · '.$p->donatur_pesan : '')),
        ]);
    }

    private function parseWaktu(?string $nilai): ?Carbon
    {
        if (blank($nilai)) {
            return null;
        }

        try {
            return Carbon::parse($nilai)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
