<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\TarifIuran;
use App\Services\PembayaranService;
use App\Services\TagihanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IuranController extends Controller
{
    private const MASUK = 'coalesce(nominal_dibayar, nominal)';

    /** Rekap tagihan iuran (pengurus): per bulan, bisa difilter per jenis iuran. */
    public function index(Request $request)
    {
        $user = $this->user();
        [$dasar, $f] = $this->dasarRekap($request);

        $ringkasan = [
            'total' => (int) (clone $dasar)->where('sukarela', false)->sum('nominal'),
            'lunas' => (int) (clone $dasar)->lunas()->sum(DB::raw(self::MASUK)),
            'jumlah' => (clone $dasar)->count(),
            'jumlah_lunas' => (clone $dasar)->lunas()->count(),
            'qris' => (int) (clone $dasar)->lunas()->whereIn('metode', ['qris', 'va'])->sum(DB::raw(self::MASUK)),
            'tunai' => (int) (clone $dasar)->lunas()->whereIn('metode', ['tunai', 'transfer'])->sum(DB::raw(self::MASUK)),
        ];

        $perJenis = (clone $dasar)
            ->selectRaw('tarif_iuran_id, count(*) as jumlah, sum(case when status = \'lunas\' then 1 else 0 end) as lunas, sum(case when status = \'lunas\' then '.self::MASUK.' else 0 end) as terkumpul')
            ->groupBy('tarif_iuran_id')
            ->get();

        $tagihan = (clone $dasar)
            ->when($f['status'], fn ($x) => $x->where('tagihans.status', $f['status']))
            ->when($f['q'] !== '', fn ($x) => $x->whereHas('kartuKeluarga', fn ($k) => $k->whereLike('nama_kepala', "%{$f['q']}%")))
            ->with(['kartuKeluarga.rumah.blok.rt', 'pencatat:id,name', 'jenis:id,nama,frekuensi'])
            ->join('kartu_keluargas', 'kartu_keluargas.id', '=', 'tagihans.kartu_keluarga_id')
            ->leftJoin('rumahs', 'rumahs.id', '=', 'kartu_keluargas.rumah_id')
            ->leftJoin('bloks', 'bloks.id', '=', 'rumahs.blok_id')
            ->select('tagihans.*')
            ->orderBy('bloks.nama')->orderByRaw('LENGTH(rumahs.nomor), rumahs.nomor')->orderBy('tagihans.judul')
            ->paginate(50)
            ->withQueryString();

        // Tunggakan lain (tagihan periode sebelumnya yang belum lunas) per KK di halaman ini
        $tunggakan = Tagihan::query()->belum()->where('sukarela', false)
            ->where('periode', '<', $f['periode']->toDateString())
            ->whereIn('kartu_keluarga_id', $tagihan->pluck('kartu_keluarga_id'))
            ->selectRaw('kartu_keluarga_id, count(*) as bulan, sum(nominal) as total')
            ->groupBy('kartu_keluarga_id')
            ->get()->keyBy('kartu_keluarga_id');

        return view('iuran.index', [
            'periode' => $f['periode'],
            'tagihan' => $tagihan,
            'ringkasan' => $ringkasan,
            'perJenis' => $perJenis,
            'tunggakan' => $tunggakan,
            'rts' => $user->managedRts()->get(),
            'daftarJenis' => $this->daftarJenis(),
            'jenisDipilih' => $f['jenis'],
            'filter' => ['rtId' => $f['rtId'], 'status' => $f['status'], 'q' => $f['q'], 'jenis' => $f['jenis']?->id],
        ]);
    }

    public function generate(Request $request, TagihanService $service)
    {
        $request->validate(['periode' => ['required', 'date_format:Y-m']]);
        $user = $this->user();
        $periode = Carbon::createFromFormat('!Y-m', $request->input('periode'))->startOfMonth();
        $rtId = $user->isPengurusRt() ? $user->rt_id : ($request->integer('rt') ?: null);

        $jumlah = $service->generate($periode, $rtId);

        return back()->with('sukses', $jumlah
            ? "{$jumlah} tagihan iuran bulanan/tahunan dibuat untuk {$periode->translatedFormat('F Y')}."
            : "Semua tagihan bulanan/tahunan {$periode->translatedFormat('F Y')} sudah dibuat sebelumnya.");
    }

    /** Catat pembayaran tunai / transfer manual. */
    public function tandaiLunas(Request $request, Tagihan $tagihan)
    {
        $this->pastikanKelolaKeluarga($tagihan->kartuKeluarga);

        $data = $request->validate([
            'metode' => ['required', Rule::in(['tunai', 'transfer'])],
            'nominal' => [$tagihan->sukarela ? 'required' : 'nullable', 'integer', 'min:'.max(1, $tagihan->sukarela ? (int) $tagihan->nominal : 0), 'max:100000000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'nominal.required' => 'Isi nominal yang dibayarkan untuk iuran sukarela.',
            'nominal.min' => 'Nominal minimal '.rupiah(max(1, (int) $tagihan->nominal)).'.',
        ]);

        if ($tagihan->isLunas()) {
            return back()->with('gagal', 'Tagihan ini sudah lunas.');
        }

        if (app(PembayaranService::class)->pembayaranAktif([$tagihan->id])->isNotEmpty()) {
            return back()->with('gagal', 'Warga sedang memiliki QRIS aktif untuk tagihan ini. Tunggu sampai QRIS kedaluwarsa (maks. beberapa menit) agar tidak terjadi bayar ganda.');
        }

        $tagihan->update([
            'status' => 'lunas',
            'metode' => $data['metode'],
            'nominal_dibayar' => $tagihan->sukarela ? (int) $data['nominal'] : $tagihan->nominal,
            'catatan' => $data['catatan'] ?? null,
            'dibayar_pada' => now(),
            'dicatat_oleh' => $this->user()->id,
        ]);

        return back()->with('sukses', "{$tagihan->periode_label} — {$tagihan->kartuKeluarga->nama_kepala} dicatat lunas (".rupiah($tagihan->nominal_masuk).').');
    }

    public function batalLunas(Tagihan $tagihan)
    {
        $this->pastikanKelolaKeluarga($tagihan->kartuKeluarga);

        if (in_array($tagihan->metode, ['qris', 'va'], true)) {
            return back()->with('gagal', 'Pembayaran QRIS/VA tidak bisa dibatalkan dari aplikasi. Lakukan refund melalui AINO bila perlu.');
        }

        $tagihan->update(['status' => 'belum', 'metode' => null, 'nominal_dibayar' => null, 'dibayar_pada' => null, 'dicatat_oleh' => null, 'catatan' => null]);

        return back()->with('sukses', 'Status lunas dibatalkan.');
    }

    /** Unduh rekap sebagai CSV (bisa dibuka di Excel). */
    public function export(Request $request): StreamedResponse
    {
        [$dasar, $f] = $this->dasarRekap($request);

        $rows = $dasar->with(['kartuKeluarga.rumah.blok.rt', 'jenis:id,nama,frekuensi'])->get()
            ->sortBy(fn ($t) => [$t->kartuKeluarga->rumah?->blok?->rt?->nomor, $t->kartuKeluarga->rumah?->blok?->nama, (int) $t->kartuKeluarga->rumah?->nomor, $t->judul]);

        $nama = 'iuran-'.($f['jenis'] && $f['jenis']->frekuensi !== 'bulanan' ? \Illuminate\Support\Str::slug($f['jenis']->nama) : $f['periode']->format('Y-m'))
            .($f['rtId'] ? '-rt'.$f['rtId'] : '').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['RT', 'Blok', 'No', 'Kepala Keluarga', 'Tagihan', 'Jenis', 'Nominal', 'Dibayar', 'Status', 'Metode', 'Tanggal Bayar', 'Catatan'], ';');
            $aman = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
            foreach ($rows as $t) {
                $r = $t->kartuKeluarga->rumah;
                fputcsv($out, array_map($aman, [
                    $r?->blok?->rt?->nomor, $r?->blok?->nama, $r?->nomor, $t->kartuKeluarga->nama_kepala,
                    $t->periode_label, $t->jenis ? (TarifIuran::FREKUENSI[$t->jenis->frekuensi] ?? '') : '',
                    $t->nominal, $t->isLunas() ? $t->nominal_masuk : null, $t->status, $t->metode,
                    $t->dibayar_pada?->format('Y-m-d H:i'), $t->catatan,
                ]), ';');
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Riwayat transaksi QRIS (pengurus). */
    public function transaksi(Request $request)
    {
        $user = $this->user();
        $rtId = $user->isPengurusRt() ? $user->rt_id : ($request->integer('rt') ?: null);

        $transaksi = Pembayaran::query()
            ->whereNull('donasi_id')
            ->whereHas('kartuKeluarga', fn ($k) => $k->diRt($rtId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->with(['kartuKeluarga.rumah.blok', 'tagihans:id,periode,judul'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('iuran.transaksi', ['transaksi' => $transaksi, 'rts' => $user->managedRts()->get(), 'rtId' => $rtId]);
    }

    /** Tagihan milik keluarga user yang login. */
    public function saya()
    {
        $user = $this->user();
        abort_unless($user->kartu_keluarga_id, 403, 'Akun Anda belum terhubung ke data Kartu Keluarga. Hubungi pengurus RT.');

        $kk = $user->kartuKeluarga()->with('rumah.blok.rt')->first();

        return view('iuran.saya', [
            'kk' => $kk,
            'belum' => $kk->tagihans()->belum()->with('jenis:id,nama,frekuensi,keterangan')->reorder()
                ->orderBy('periode')->orderBy('judul')->get(),
            'riwayat' => $kk->tagihans()->lunas()->reorder()->orderByDesc('dibayar_pada')->limit(36)->get(),
            'transaksi' => $kk->pembayarans()->whereNull('donasi_id')->with('tagihans:id,periode,judul')->limit(10)->get(),
            'biayaPersen' => (float) config('aino.biaya_persen', 0),
            'gatewayAktif' => app(\App\Services\AinoClient::class)->dikonfigurasi(),
        ]);
    }

    /**
     * Query dasar rekap sesuai filter.
     * Filter jenis tahunan/insidental menampilkan semua tagihan jenis itu (tanpa filter bulan).
     *
     * @return array{0: Builder, 1: array}
     */
    private function dasarRekap(Request $request): array
    {
        $user = $this->user();
        $periode = $this->periode($request);
        $rtId = $user->isPengurusRt() ? $user->rt_id : ($request->integer('rt') ?: null);
        $jenis = $request->integer('jenis') ? $this->daftarJenis()->firstWhere('id', $request->integer('jenis')) : null;
        $abaikanBulan = $jenis && $jenis->frekuensi !== 'bulanan' && ! $request->filled('periode');

        $dasar = Tagihan::query()
            ->when(! $abaikanBulan, fn ($q) => $q->where('tagihans.periode', $periode->toDateString()))
            ->when($jenis, fn ($q) => $q->where('tagihans.tarif_iuran_id', $jenis->id))
            ->whereHas('kartuKeluarga', fn ($k) => $k->diRt($rtId));

        return [$dasar, [
            'periode' => $periode,
            'rtId' => $rtId,
            'jenis' => $jenis,
            'abaikanBulan' => $abaikanBulan,
            'status' => in_array($request->input('status'), ['belum', 'lunas'], true) ? $request->input('status') : null,
            'q' => trim((string) $request->input('q')),
        ]];
    }

    /** Jenis iuran yang terlihat oleh user (untuk filter). */
    private function daftarJenis()
    {
        $user = $this->user();

        return TarifIuran::query()
            ->when($user->isPengurusRt(), fn ($q) => $q->where(fn ($w) => $w->whereNull('rt_id')->orWhere('rt_id', $user->rt_id)))
            ->orderByRaw("CASE frekuensi WHEN 'bulanan' THEN 0 WHEN 'tahunan' THEN 1 ELSE 2 END")
            ->orderBy('nama')
            ->get(['id', 'nama', 'frekuensi', 'rt_id', 'aktif']);
    }

    private function periode(Request $request): Carbon
    {
        $p = $request->input('periode');

        return ($p && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p))
            ? Carbon::createFromFormat('!Y-m', $p)->startOfMonth()
            : now()->startOfMonth();
    }
}
