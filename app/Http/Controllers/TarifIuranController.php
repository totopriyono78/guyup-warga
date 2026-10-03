<?php

namespace App\Http\Controllers;

use App\Models\TarifIuran;
use App\Services\TagihanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Kelola jenis iuran (bulanan, tahunan, insidental; wajib atau sukarela). */
class TarifIuranController extends Controller
{
    public function index()
    {
        $user = $this->user();

        $tarif = TarifIuran::query()
            ->with('rt')
            ->when($user->isPengurusRt(), fn ($q) => $q->where(fn ($w) => $w->whereNull('rt_id')->orWhere('rt_id', $user->rt_id)))
            ->orderByRaw("CASE frekuensi WHEN 'bulanan' THEN 0 WHEN 'tahunan' THEN 1 ELSE 2 END")
            ->orderByRaw('rt_id IS NOT NULL')->orderBy('nama')
            ->get();

        // Ringkasan tagihan per jenis (dalam cakupan user)
        $rtId = $user->isPengurusRt() ? $user->rt_id : null;
        $ringkas = DB::table('tagihans')
            ->join('kartu_keluargas', 'kartu_keluargas.id', '=', 'tagihans.kartu_keluarga_id')
            ->leftJoin('rumahs', 'rumahs.id', '=', 'kartu_keluargas.rumah_id')
            ->leftJoin('bloks', 'bloks.id', '=', 'rumahs.blok_id')
            ->when($rtId, fn ($q) => $q->where('bloks.rt_id', $rtId))
            ->whereIn('tagihans.tarif_iuran_id', $tarif->pluck('id'))
            ->groupBy('tagihans.tarif_iuran_id')
            ->selectRaw("tagihans.tarif_iuran_id,
                count(*) as jumlah,
                sum(case when tagihans.status = 'lunas' then 1 else 0 end) as lunas,
                sum(case when tagihans.status = 'lunas' then coalesce(tagihans.nominal_dibayar, tagihans.nominal) else 0 end) as terkumpul")
            ->get()->keyBy('tarif_iuran_id');

        return view('iuran.tarif', [
            'tarif' => $tarif,
            'ringkas' => $ringkas,
            'rts' => $user->managedRts()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $jenis = TarifIuran::query()->create($this->validasi($request));

        $pesan = match ($jenis->frekuensi) {
            'bulanan' => 'Jenis iuran bulanan ditambahkan. Tagihan dibuat otomatis tiap tanggal 1, atau klik "Terbitkan" untuk bulan ini.',
            'tahunan' => 'Jenis iuran tahunan ditambahkan. Tagihan dibuat otomatis tiap bulan '.$jenis->frekuensiLabel().'.',
            default => 'Iuran insidental ditambahkan. Klik "Terbitkan tagihan" bila sudah siap ditagihkan ke warga.',
        };

        return back()->with('sukses', $pesan);
    }

    public function update(Request $request, TarifIuran $tarif)
    {
        $this->pastikanBoleh($tarif);
        $tarif->update($this->validasi($request, $tarif));

        return back()->with('sukses', 'Jenis iuran diperbarui. Tagihan yang sudah terbit tidak berubah.');
    }

    public function destroy(TarifIuran $tarif)
    {
        $this->pastikanBoleh($tarif);

        if ($tarif->tagihans()->exists()) {
            $tarif->update(['aktif' => false]);

            return back()->with('sukses', "“{$tarif->nama}” sudah punya tagihan, jadi dinonaktifkan (tidak dihapus) agar riwayat kas tetap utuh.");
        }

        $tarif->delete();

        return back()->with('sukses', 'Jenis iuran dihapus.');
    }

    /** Terbitkan tagihan jenis ini sekarang (insidental, atau bulanan/tahunan untuk periode berjalan). */
    public function terbitkan(TarifIuran $tarif, TagihanService $service)
    {
        $user = $this->user();
        // Iuran RW boleh diterbitkan pengurus RT, tetapi hanya untuk KK di RT-nya
        abort_unless($tarif->rt_id ? $user->canManageRt($tarif->rt_id) : $user->isPengurus(), 403);
        abort_unless($tarif->aktif, 422, 'Jenis iuran ini nonaktif.');

        $jumlah = $service->terbitkan($tarif, null, $user->isPengurusRt() ? $user->rt_id : null);

        return back()->with('sukses', $jumlah
            ? "{$jumlah} tagihan “{$tarif->judulTagihan($service->periodeUntuk($tarif))}” diterbitkan."
            : 'Tidak ada tagihan baru — semua KK sudah memiliki tagihan ini.');
    }

    private function pastikanBoleh(TarifIuran $tarif): void
    {
        // Jenis iuran tingkat RW hanya boleh diubah pengurus RW
        abort_unless($tarif->rt_id ? $this->user()->canManageRt($tarif->rt_id) : $this->user()->isAdmin(), 403);
    }

    private function validasi(Request $request, ?TarifIuran $tarif = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'frekuensi' => ['required', Rule::in(array_keys(TarifIuran::FREKUENSI))],
            'nominal' => ['required', 'integer', 'min:0', 'max:100000000'],
            'sukarela' => ['nullable', 'boolean'],
            'bulan_tagih' => ['nullable', 'required_if:frekuensi,tahunan', 'integer', 'between:1,12'],
            'tenggat' => ['nullable', 'date'],
            'rt_id' => ['nullable', 'exists:rts,id'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'bulan_tagih.required_if' => 'Pilih bulan penagihan untuk iuran tahunan.',
        ]);

        $user = $this->user();
        if ($user->isPengurusRt()) {
            $data['rt_id'] = $user->rt_id;
        }

        $data['sukarela'] = $request->boolean('sukarela');
        $data['aktif'] = $tarif ? $request->boolean('aktif') : true;

        if (! $data['sukarela'] && (int) $data['nominal'] < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['nominal' => 'Nominal iuran wajib minimal Rp1. Centang "Sukarela" untuk sumbangan bebas.']);
        }
        if ($data['frekuensi'] !== 'tahunan') {
            $data['bulan_tagih'] = null;
        }
        if ($data['frekuensi'] !== 'insidental') {
            $data['tenggat'] = null;
        }

        return $data;
    }
}
