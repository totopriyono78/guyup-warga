<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Support\FotoUploader;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function edit()
    {
        return view('pengaturan.edit', [
            'p' => Pengaturan::semua(),
            'aino' => [
                'aktif' => app(\App\Services\AinoClient::class)->dikonfigurasi(),
                'base_url' => config('aino.base_url'),
                'merchant' => config('aino.merchant_code'),
                'callback' => config('aino.callback_url') ?: route('aino.notify'),
                'biaya' => config('aino.biaya_persen'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nama_rw' => ['required', 'string', 'max:100'],
            'dusun' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'alamat_sekretariat' => ['nullable', 'string', 'max:500'],
            'kontak' => ['nullable', 'string', 'max:500'],
            'peta_wilayah' => ['nullable', 'image', 'max:10240'],
            'hapus_peta' => ['nullable', 'boolean'],
        ]);
        $data['peta_umum_nama'] = $request->boolean('peta_umum_nama') ? '1' : '0';

        $lama = Pengaturan::ambil('peta_wilayah');

        if ($request->hasFile('peta_wilayah')) {
            FotoUploader::hapus($lama);
            config(['siwarga.foto_max_px' => 2400]); // peta butuh resolusi lebih besar
            $data['peta_wilayah'] = FotoUploader::simpan($request->file('peta_wilayah'), 'peta');
        } elseif ($request->boolean('hapus_peta')) {
            FotoUploader::hapus($lama);
            $data['peta_wilayah'] = null;
        } else {
            unset($data['peta_wilayah']);
        }
        unset($data['hapus_peta']);
        $data['kota'] = null; // digantikan "kabupaten"
        foreach (['dusun', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi'] as $k) {
            $data[$k] = $data[$k] ?? '';
        }

        Pengaturan::simpan($data);

        return back()->with('sukses', 'Pengaturan disimpan.');
    }
}
