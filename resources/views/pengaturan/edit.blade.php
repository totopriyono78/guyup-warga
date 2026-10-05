@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
    <x-page-header judul="Pengaturan" sub="Identitas wilayah dan status payment gateway." />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form method="post" action="{{ route('pengaturan.update') }}" enctype="multipart/form-data" class="card card-body space-y-4 lg:col-span-2">
            @csrf @method('put')
            <h2 class="card-title">Identitas wilayah</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="label">Nama RW</label><input name="nama_rw" value="{{ old('nama_rw', $p['nama_rw']) }}" required class="input" placeholder="RW 02"></div>
                <div><label class="label">Dusun / padukuhan</label><input name="dusun" value="{{ old('dusun', $p['dusun']) }}" class="input" placeholder="Sidorejo"></div>
                <div><label class="label">Kelurahan / kalurahan / desa</label><input name="kelurahan" value="{{ old('kelurahan', $p['kelurahan']) }}" class="input" placeholder="Selomartani"></div>
                <div><label class="label">Kecamatan / kapanewon</label><input name="kecamatan" value="{{ old('kecamatan', $p['kecamatan']) }}" class="input" placeholder="Kalasan"></div>
                <div><label class="label">Kabupaten / kota</label><input name="kabupaten" value="{{ old('kabupaten', $p['kabupaten']) }}" class="input" placeholder="Sleman"></div>
                <div><label class="label">Provinsi</label><input name="provinsi" value="{{ old('provinsi', $p['provinsi']) }}" class="input" placeholder="D.I. Yogyakarta"></div>
                <div class="sm:col-span-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                    Tampil sebagai: <b class="text-slate-900">{{ $p['nama_rw'] }}</b> · {{ wilayah() ?: '—' }}
                    <span class="block text-xs text-slate-500">Data RT (tambah/ubah/hapus) dikelola di menu <a href="{{ route('wilayah') }}" class="text-brand-700 underline">RT, Blok &amp; Rumah</a>.</span>
                </div>
                <div><label class="label">Kontak pengurus</label><input name="kontak" value="{{ old('kontak', $p['kontak']) }}" class="input" placeholder="WA sekretariat"></div>
                <div class="sm:col-span-2"><label class="label">Alamat sekretariat</label><textarea name="alamat_sekretariat" rows="2" class="input">{{ old('alamat_sekretariat', $p['alamat_sekretariat']) }}</textarea></div>
                <div class="sm:col-span-2">
                    <label class="label">Gambar peta wilayah (opsional)</label>
                    @if ($p['peta_wilayah'])
                        <img src="{{ Storage::disk('public')->url($p['peta_wilayah']) }}" alt="Peta wilayah" class="mb-2 max-h-60 rounded-lg ring-1 ring-slate-200">
                        <label class="mb-2 flex items-center gap-2 text-sm text-rose-600"><input type="checkbox" name="hapus_peta" value="1" class="rounded border-slate-300"> Hapus peta</label>
                    @endif
                    <x-pilih-file name="peta_wilayah" accept="image/*" label="Pilih gambar peta" hint="JPG / PNG, maks. 10 MB" />
                    <p class="hint">Mis. hasil foto/scan denah perumahan atau tangkapan layar Google Maps. Ditampilkan di atas halaman Denah.</p>
                </div>
            </div>
            <div class="space-y-2 border-t border-slate-100 pt-4">
                <h2 class="card-title">Halaman umum (website)</h2>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                    <input type="hidden" name="peta_umum_nama" value="0">
                    <input type="checkbox" name="peta_umum_nama" value="1" @checked(old('peta_umum_nama', $p['peta_umum_nama']) === '1') class="mt-0.5 rounded border-slate-300 text-brand-700">
                    <span>
                        <b class="text-slate-900">Tampilkan nama kepala keluarga di peta</b>
                        <span class="block text-slate-500">Pengunjung tanpa login dapat mengklik titik rumah untuk melihat nomor rumah dan nama kepala keluarga (atau pemilik). Anggota keluarga, NIK, No. KK, dan nomor HP tetap hanya untuk warga yang masuk.</span>
                    </span>
                </label>
            </div>
            <div class="border-t border-slate-100 pt-4"><button class="btn btn-primary">Simpan pengaturan</button></div>
        </form>

        <div class="card card-body h-fit space-y-3 text-sm">
            <h2 class="card-title">Payment gateway AINO</h2>
            @if ($aino['aktif'])
                <span class="badge badge-green">Terkonfigurasi</span>
            @else
                <span class="badge badge-red">Belum dikonfigurasi</span>
            @endif
            <dl class="space-y-2">
                <div><dt class="text-xs text-slate-500">Endpoint</dt><dd class="break-all font-mono text-xs">{{ $aino['base_url'] }}</dd></div>
                <div><dt class="text-xs text-slate-500">Merchant code</dt><dd class="font-mono text-xs">{{ $aino['merchant'] ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">URL callback (Finish Notify)</dt><dd class="break-all font-mono text-xs">{{ $aino['callback'] }}</dd></div>
                <div><dt class="text-xs text-slate-500">Biaya layanan ke warga</dt><dd>{{ $aino['biaya'] > 0 ? $aino['biaya'].'%' : 'Tidak ada (ditanggung kas)' }}</dd></div>
            </dl>
            <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                Kredensial diatur di file <code>.env</code> server: <code>AINO_MERCHANT_CODE</code>, <code>AINO_SECRET_KEY</code>, <code>AINO_BASE_URL</code>.
                Setelah mengubah, jalankan <code>php artisan config:cache</code>.
                @if (! str_starts_with($aino['callback'], 'https://'))
                    <br><b class="text-rose-600">Perhatian: AINO mewajibkan URL callback HTTPS.</b>
                @endif
            </p>
        </div>
    </div>
@endsection
