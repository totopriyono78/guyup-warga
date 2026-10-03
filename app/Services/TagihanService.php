<?php

namespace App\Services;

use App\Models\KartuKeluarga;
use App\Models\Tagihan;
use App\Models\TarifIuran;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan tagihan per jenis iuran.
 *  - bulanan    : otomatis tiap tanggal 1 (iuran:generate)
 *  - tahunan    : otomatis pada bulan_tagih setiap tahun
 *  - insidental : diterbitkan manual oleh pengurus (tombol "Terbitkan tagihan")
 */
class TagihanService
{
    /**
     * Total iuran bulanan wajib per KK untuk RT tertentu (untuk informasi di halaman jenis iuran).
     *
     * @return array{0:int, 1:array<int, array{nama:string, nominal:int}>}
     */
    public function hitung(?int $rtId): array
    {
        $tarif = TarifIuran::query()->berlakuUntuk($rtId)->frekuensi('bulanan')->where('sukarela', false)
            ->orderByRaw('rt_id IS NOT NULL')->orderBy('nama')->get();

        return [(int) $tarif->sum('nominal'), $tarif->map(fn ($t) => ['nama' => $t->nama, 'nominal' => (int) $t->nominal])->values()->all()];
    }

    /**
     * Membuat tagihan jenis bulanan (dan tahunan yang jatuh pada bulan ini) untuk semua KK aktif.
     * Aman dijalankan berulang kali.
     *
     * @return int jumlah tagihan baru
     */
    public function generate(CarbonInterface $periode, ?int $rtId = null): int
    {
        $periode = Carbon::parse($periode)->startOfMonth();

        $jenis = TarifIuran::query()
            ->where('aktif', true)
            ->where(fn ($q) => $q->where('frekuensi', 'bulanan')
                ->orWhere(fn ($t) => $t->where('frekuensi', 'tahunan')->where('bulan_tagih', $periode->month)))
            ->when($rtId, fn ($q) => $q->where(fn ($w) => $w->whereNull('rt_id')->orWhere('rt_id', $rtId)))
            ->get();

        $dibuat = 0;
        foreach ($jenis as $j) {
            $dibuat += $this->terbitkan($j, $periode, $rtId);
        }

        return $dibuat;
    }

    /**
     * Terbitkan tagihan satu jenis iuran untuk semua KK aktif dalam cakupannya.
     *
     * @param  int|null  $rtId  batasi ke RT tertentu (mis. pengurus RT menerbitkan iuran RW untuk RT-nya)
     * @return int jumlah tagihan baru
     */
    public function terbitkan(TarifIuran $jenis, ?CarbonInterface $periode = null, ?int $rtId = null): int
    {
        $periode = $this->periodeUntuk($jenis, $periode);
        $cakupanRt = $jenis->rt_id ?? $rtId;
        if ($jenis->rt_id && $rtId && (int) $jenis->rt_id !== (int) $rtId) {
            return 0;
        }

        $tanggal = $periode->toDateString();
        $judul = $jenis->judulTagihan($periode);
        $jatuhTempo = $jenis->jatuhTempo($periode)->toDateString();
        $dibuat = 0;

        KartuKeluarga::query()
            ->aktif()
            ->whereNotNull('rumah_id')
            ->diRt($cakupanRt)
            ->whereDoesntHave('tagihans', fn ($q) => $q->where('tarif_iuran_id', $jenis->id)->where('periode', $tanggal))
            // Tagihan gabungan versi lama (tanpa jenis) untuk bulan yang sama sudah mencakup iuran bulanan
            ->when($jenis->frekuensi === 'bulanan', fn ($q) => $q->whereDoesntHave('tagihans', fn ($t) => $t->whereNull('tarif_iuran_id')->where('periode', $tanggal)))
            ->select('id')
            ->chunkById(300, function (Collection $kks) use ($jenis, $tanggal, $judul, $jatuhTempo, &$dibuat) {
                $sekarang = now();
                $baris = $kks->map(fn ($kk) => [
                    'kartu_keluarga_id' => $kk->id,
                    'tarif_iuran_id' => $jenis->id,
                    'periode' => $tanggal,
                    'judul' => $judul,
                    'jatuh_tempo' => $jatuhTempo,
                    'nominal' => (int) $jenis->nominal,
                    'sukarela' => (bool) $jenis->sukarela,
                    'rincian' => json_encode([['nama' => $jenis->nama, 'nominal' => (int) $jenis->nominal]]),
                    'status' => 'belum',
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ])->all();

                // insertOrIgnore: aman bila dua proses berjalan bersamaan (unique kk + jenis + periode)
                $dibuat += DB::table('tagihans')->insertOrIgnore($baris);
            });

        if ($jenis->frekuensi === 'insidental' && ! $jenis->diterbitkan_pada) {
            $jenis->forceFill(['diterbitkan_pada' => now()])->save();
        }

        return $dibuat;
    }

    /** Periode (tanggal 1) tagihan untuk jenis ini. */
    public function periodeUntuk(TarifIuran $jenis, ?CarbonInterface $periode = null): Carbon
    {
        $periode = $periode ? Carbon::parse($periode)->startOfMonth() : now()->startOfMonth();

        if ($jenis->frekuensi === 'tahunan') {
            return $periode->copy()->month($jenis->bulan_tagih ?: 1)->startOfMonth();
        }

        if ($jenis->frekuensi === 'insidental') {
            // tagihan acara hanya terbit sekali: pakai bulan penerbitan pertama
            return $jenis->diterbitkan_pada ? $jenis->diterbitkan_pada->copy()->startOfMonth() : $periode;
        }

        return $periode;
    }
}
