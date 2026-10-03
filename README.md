# Rukoon — Aplikasi Warga RT/RW

<img src="public/img/rukoon.svg" alt="Rukoon" height="56">

*Rukoon* (dari kata **rukun**, seperti Rukun Tetangga dan Rukun Warga): dua cincin bertaut di bawah satu atap, warga yang rukun di satu lingkungan.

Aplikasi web untuk pengurus RW/RT dan warga, dibangun dengan **Laravel 12 + PostgreSQL**.

| # | Fitur | Keterangan |
|---|-------|-----------|
| 1 | **Denah wilayah** | Denah interaktif per RT → blok → rumah (posisi baris/kolom, otomatis diselingi jalan). Warna petak menurut status hunian, atau status iuran bulan ini (pengurus). Ketuk petak untuk melihat penghuni + foto. Bisa menambah gambar peta wilayah. |
| 2 | **Pendataan warga** | Kartu Keluarga + anggota (NIK, TTL, agama, pendidikan, pekerjaan, dst). Foto keluarga & foto wajah tiap anggota, otomatis dikompres. Tandai KK "sudah pindah". |
| 3 | **Pencarian** | Nama warga, NIK / No. KK / No. HP (khusus pengurus), kode rumah (`A-12`), dan isi pengumuman. Ada juga "sorot nama" langsung di denah. |
| 4 | **Pengumuman** | Dari RW (semua warga) atau RT (warga RT tsb). Penting/pin, lampiran gambar/PDF, jadwal terbit, draf, tombol bagikan ke WhatsApp. |
| 5 | **Iuran bulanan + QRIS** | Komponen iuran tingkat RW & RT, tagihan dibuat otomatis tiap tanggal 1. Warga bayar beberapa bulan sekaligus lewat **QRIS dinamis AINO**; status lunas otomatis. Pengurus bisa catat tunai/transfer, lihat rekap, tunggakan, unduh CSV. |

## Peran pengguna

| Peran | Hak akses |
|-------|-----------|
| **Pengurus RW** (`admin`) | Semua data & semua RT, kelola RT, tarif RW, akun, pengaturan. |
| **Pengurus RT** (`rt`) | Kelola blok/rumah/KK/iuran/pengumuman **hanya di RT-nya**, buat akun warga RT-nya. |
| **Warga** (`warga`) | Lihat denah (nama & foto penghuni, tanpa NIK/No. KK), data lengkap keluarga sendiri, pengumuman RW + RT-nya, bayar iuran keluarganya. |

Login bisa memakai **email atau No. HP**.

---

## Kebutuhan server

- PHP **8.2+** (disarankan 8.3) dengan ekstensi: `pdo_pgsql`, `gd`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `bcmath`, `fileinfo` (opsional `exif` untuk memutar foto HP otomatis)
- PostgreSQL 13+
- Composer 2
- Nginx (atau Apache) + **HTTPS** (wajib, karena callback AINO hanya boleh ke URL HTTPS)
- Node.js **tidak** diperlukan di server — CSS/JS sudah dibangun di `public/css` dan `public/js`.

## Instalasi (Ubuntu/Debian)

```bash
# 1. Paket sistem (contoh PHP 8.3)
sudo apt install nginx postgresql php8.3-fpm php8.3-pgsql php8.3-gd php8.3-mbstring \
     php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath unzip
# Composer: https://getcomposer.org/download/

# 2. Database
sudo -u postgres psql -c "CREATE USER siwarga WITH PASSWORD 'GANTI_PASSWORD_KUAT';"
sudo -u postgres psql -c "CREATE DATABASE siwarga OWNER siwarga;"

# 3. Kode aplikasi
sudo mkdir -p /var/www/siwarga && sudo chown $USER:www-data /var/www/siwarga
cd /var/www/siwarga
unzip ~/siwarga.zip -d .            # atau git clone
composer install --no-dev --optimize-autoloader

# 4. Konfigurasi
cp .env.example .env
php artisan key:generate
nano .env        # isi APP_URL, DB_*, ADMIN_*, SIWARGA_*, AINO_*

# 5. Tabel, akun admin, folder foto
php artisan migrate --force
php artisan db:seed --force         # membuat akun admin RW dari ADMIN_EMAIL / ADMIN_PASSWORD
# Lupa password admin / login gagal? →  php artisan admin:atur email@anda.id --password=PasswordBaru123
php artisan storage:link

# 6. Izin folder
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

# 7. Cache produksi
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Lalu:

- **Nginx**: salin `deploy/nginx.conf` ke `/etc/nginx/sites-available/siwarga`, sesuaikan domain & versi PHP-FPM, aktifkan, lalu pasang SSL: `sudo certbot --nginx -d warga.domainanda.id`.
- **Cron** (wajib, untuk tagihan otomatis & cek status QRIS): tambahkan baris di `deploy/crontab.txt` ke `crontab -u www-data -e`.
- Update berikutnya cukup jalankan `deploy/deploy.sh`.

> Sebelum HTTPS aktif, set `SESSION_SECURE_COOKIE=false` sementara — kalau tidak, login akan selalu kembali ke halaman masuk.

### Deploy ke Railway

Railway (Railpack) membaca versi PHP dari `composer.json` (`"php": "^8.4"`), memasang ekstensi `gd` & `pdo_pgsql`,
menjalankan `npm run build`, `php artisan migrate --force`, dan seeder admin secara otomatis.

1. Tambahkan layanan **PostgreSQL** di proyek Railway.
2. Di layanan aplikasi, isi **Variables**:

   ```
   APP_NAME=Rukoon
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...            # hasil: php artisan key:generate --show
   APP_URL=https://<nama>.up.railway.app
   DB_CONNECTION=pgsql
   DB_URL=${{Postgres.DATABASE_URL}}
   TRUSTED_PROXIES=*             # wajib: HTTPS diakhiri di proxy Railway
   FORCE_HTTPS=true              # semua URL (CSS/JS/form) memakai https
   SESSION_SECURE_COOKIE=true
   LOG_CHANNEL=stderr            # log tampil di tab Logs Railway
   ADMIN_EMAIL=admin@domain.id
   ADMIN_PASSWORD=...
   AINO_MERCHANT_CODE=...
   AINO_SECRET_KEY=...
   AINO_CALLBACK_URL=https://<nama>.up.railway.app/aino/notify
   ```
3. **Volume** untuk foto: buat Volume dan pasang (mount) di `/app/storage/app/public`.
   Tanpa volume, foto yang diunggah hilang setiap kali deploy ulang. Tautan `public/storage` dibuat otomatis.
4. **Migrasi, akun admin & data contoh**: di *Settings → Deploy → Pre-deploy Command* isi
   `php artisan rukoon:siapkan` (tambahkan `--contoh` atau variabel `SEED_CONTOH=true` untuk mengisi data contoh
   RW 02 bila database masih kosong). Aman dijalankan setiap deploy.
5. **Penjadwal** (tagihan bulanan & cek status QRIS): buat layanan kedua dari repo yang sama dengan
   *Custom Start Command* `php artisan schedule:work` dan variabel yang sama (bisa memakai *Shared Variables*).

### Mencoba dengan data contoh (opsional, jangan di server produksi)

```bash
composer install                    # butuh paket dev (Faker)
php artisan db:seed --class=DemoSeeder
```

Membuat RT 03, 04, 05 (RW 02 Dusun Sidorejo), blok, ±110 rumah & keluarga, tarif, tagihan 3 bulan,
pengumuman, galeri kegiatan, dan contoh penggalangan dana.
Akun contoh (password `password`): `ketua.rt03@rw.local` (pengurus RT 03), `warga@rw.local` (warga).

Database yang **sudah berisi data** cukup menambah data contoh RW 02 (identitas wilayah, RT 03–05,
warga tambahan + titik peta di sekitar titik yang sudah ada, pengumuman, galeri, donasi):

```bash
php artisan db:seed --class=ContohRw02Seeder
```

Bila RT yang ada masih RT 01, 02, 03 (data contoh lama), seeder ini menomori ulang menjadi RT 03, 04, 05.
Foto galeri contoh adalah ilustrasi; hapus/ganti lewat menu **Galeri Kegiatan**.

---

## Peta wilayah (Leaflet)

Menu **Denah Wilayah** punya dua tampilan: **Peta** (posisi nyata rumah di atas peta satelit) dan **Denah blok** (petak per blok).

**Menandai rumah di peta** — menu *RT, Blok & Rumah → Atur titik rumah di peta* (khusus pengurus):

1. Cari lokasi perumahan di kotak "Cari lokasi", perbesar sampai atap rumah terlihat. Pengurus RW bisa menyimpan tampilan ini sebagai posisi awal peta.
2. **Rumah yang sudah ada di daftar**: klik rumahnya di daftar kanan, lalu klik atapnya di peta. Titik bisa digeser untuk merapikan.
3. **Rumah baru**: klik langsung di peta → pilih blok, isi nomor → Simpan (posisi di denah blok diisi otomatis).
4. **Impor dari OpenStreetMap** (opsional): tombol "Impor bangunan di area ini" menampilkan bangunan yang sudah tergambar di OpenStreetMap sebagai titik abu-abu. Klik titik abu-abu untuk menjadikannya rumah (nomor rumah terisi otomatis bila tersedia di OSM).

Warna titik sama dengan denah blok (status hunian, atau status iuran untuk pengurus). Klik titik untuk melihat penghuni.

**Sumber peta**

| `.env` | Hasil |
|---|---|
| `GOOGLE_MAPS_KEY` kosong (default) | Peta jalan OpenStreetMap + citra satelit Esri World Imagery. Tanpa API key. |
| `GOOGLE_MAPS_KEY=...` | Citra satelit Google melalui **Map Tiles API**. Aktifkan "Map Tiles API" di Google Cloud Console, lalu batasi key dengan *HTTP referrer* domain Anda karena key ini terlihat di browser. |

Peta memuat tile langsung dari browser pengguna (OpenStreetMap, Esri/Google, Nominatim untuk pencarian, Overpass untuk impor bangunan), bukan dari server Anda. Layanan gratis tersebut punya kebijakan pemakaian wajar; untuk satu RW umumnya aman. Periksa ketentuan penyedia bila aplikasi dipakai lebih luas.

**Privasi di peta** — `SIWARGA_PETA_WARGA` menentukan apa yang dilihat warga biasa untuk rumah selain rumahnya sendiri:
`nama` (default: titik + nama & foto penghuni), `titik` (titik saja tanpa nama/foto), `sendiri` (hanya rumahnya sendiri yang tampil). Pengurus selalu melihat data wilayah yang dikelolanya.

## Langkah awal setelah login sebagai Pengurus RW

1. **Pengaturan** → isi nama RW, kelurahan, dsb. (opsional unggah gambar peta).
2. **RT, Blok & Rumah** → tambah RT → tambah blok → buka blok → "Tambah banyak rumah" (mis. No. 1–16 dalam 2 baris). Rapikan baris/kolom agar sama dengan posisi asli.
3. **Data Warga** → Tambah KK (pilih rumah, isi kepala keluarga + foto) → tambah anggota.
4. **Kelola Iuran → Jenis iuran** → mis. Keamanan Rp30.000 (bulanan), Kebersihan Rp20.000 (bulanan), iuran tahunan, atau iuran acara (insidental/sukarela).
5. **Kelola Iuran → Rekap** → klik "Buat tagihan" untuk bulan berjalan. Bulan berikutnya dibuat otomatis oleh cron tiap tanggal 1 pukul 00:05.
6. **Akun Pengguna** → buat akun pengurus RT, dan akun warga (hubungkan ke KK-nya). Dari halaman detail KK juga ada tombol "Buatkan akun".

---

## Jenis iuran

Menu *Kelola Iuran → Jenis iuran*. Setiap jenis menjadi **tagihan terpisah** per KK (mis. "Kebersihan – Oktober 2026", "Iuran Tahunan Lingkungan 2026", "Sumbangan HUT RI").

| Frekuensi | Kapan tagihan dibuat |
|---|---|
| Bulanan | Otomatis tiap tanggal 1 (cron `iuran:generate`), atau tombol "Terbitkan sekarang" |
| Tahunan | Otomatis pada bulan penagihan yang dipilih, setiap tahun |
| Insidental / acara | Saat pengurus klik **Terbitkan tagihan** (dengan tenggat bayar opsional) |

- **Sukarela**: warga mengisi sendiri nominalnya (minimal sesuai nominal yang diatur, boleh 0). Iuran sukarela tidak dihitung sebagai tunggakan.
- **Berlaku untuk** semua RT (iuran RW) atau RT tertentu. Pengurus RT hanya bisa membuat/mengubah jenis iuran RT-nya, tetapi boleh menerbitkan iuran RW untuk KK di RT-nya.
- Menghapus jenis iuran yang sudah punya tagihan hanya menonaktifkannya agar riwayat kas tetap utuh.
- Warga membayar beberapa tagihan sekaligus (termasuk yang sukarela) dengan satu QRIS; pengurus mencatat tunai/transfer per tagihan.
- Rekap bisa difilter per jenis iuran; untuk jenis tahunan/insidental rekap menampilkan semua tagihannya tanpa filter bulan.

Tagihan yang dibuat sebelum fitur ini (satu tagihan gabungan per bulan) tetap tampil sebagai "Iuran <bulan>". Untuk bulan yang sudah punya tagihan gabungan, iuran bulanan per jenis tidak dibuat lagi agar tidak tertagih dua kali.

## Integrasi QRIS — AINO Payment Gateway

Mengikuti dokumen *API INTEGRATION – QRIS MPM or VA v1.0.0 (Feb 2026)*.

### Konfigurasi `.env`

```dotenv
AINO_BASE_URL=https://svc-core-go-dev.ainosi.com   # produksi: https://apg.ainosi.com
AINO_MERCHANT_CODE=kode_merchant_dari_aino
AINO_SECRET_KEY=secret_key_dari_aino
AINO_PAYMENT_TYPE=qr
AINO_CALLBACK_URL=                                  # kosong = otomatis https://domain-anda/aino/notify
AINO_BIAYA_PERSEN=0                                 # mis. 0.7 bila biaya MDR dibebankan ke warga
AINO_ALLOWED_IPS=                                   # opsional: IP server AINO, pisahkan koma
```

Setelah mengubah `.env`: `php artisan config:cache`. Status konfigurasi terlihat di menu **Pengaturan**.

Berikan URL callback ini ke tim AINO bila diminta: `https://domain-anda/aino/notify`.

### Alur pembayaran

```
Warga pilih bulan ─► POST /payment/v1/request  (payment_type=qr, order_id=UUID, gross_amount, callback)
                     ◄─ 2004700 + paymentContent (string QRIS) + referenceNo + expiryDate
Aplikasi tampilkan QR (dirender di browser), hitung mundur, cek status tiap 5 detik
Warga scan & bayar
AINO ─► POST /aino/notify (Finish Notify: orderId, referenceNo, statusCode, amount, ...)
Aplikasi ─► POST /payment/v1/inquiry (order_id, reference_no)  ◄─ 2005500 + transactionStatusDesc
          jika "paid" DAN nominal & order cocok → transaksi paid, tagihan ditandai lunas (metode QRIS)
Aplikasi ◄─ balas {"responseCode":"200","responseMessage":"Successful"}
```

**Keamanan callback.** Finish Notify dari AINO **tidak memiliki tanda tangan (signature)**, sehingga siapa pun yang tahu URL-nya bisa mengirim data palsu. Karena itu aplikasi **tidak pernah** menandai lunas berdasarkan isi callback; status selalu dikonfirmasi ulang ke API Query Payment AINO (memakai kredensial merchant), lalu nominal dan order ID dicocokkan. Bila Query Payment gagal (jaringan), callback dibalas HTTP 503 dan cron `pembayaran:sinkron` mencoba lagi setiap 5 menit. Bila memungkinkan, minta daftar IP server AINO dan isi `AINO_ALLOWED_IPS`.

**Mencegah bayar ganda.** Selama sebuah tagihan masih ada di QRIS yang aktif (belum kedaluwarsa), warga tidak bisa membuat QRIS lain yang mencakup tagihan itu dan pengurus tidak bisa mencatatnya sebagai tunai. Pembuatan QRIS dikunci per KK sehingga klik ganda tidak menghasilkan dua QR. Transaksi yang sudah berstatus kedaluwarsa tetap bisa berubah menjadi lunas bila AINO kemudian melaporkannya lunas.

**Di belakang Cloudflare / load balancer?** Isi `TRUSTED_PROXIES` dengan IP proxy tersebut agar IP asli pengunjung terbaca benar (dipakai untuk pembatasan percobaan login dan `AINO_ALLOWED_IPS`). Default hanya `127.0.0.1,::1`.

**Hal yang perlu dikonfirmasi ke AINO** (dokumen v1.0.0 belum konsisten):

- Path Query Payment: tabel menyebut `/payment/v1/inquiry`, contoh request menulis `/payment/v1/status`. Default aplikasi `/payment/v1/inquiry`; ganti dengan `AINO_INQUIRY_PATH` bila perlu.
- Nama field respons inquiry: tabel `referenceNo`/`partnerReferenceNo`, contoh `referenceNumber`/`partnerReferenceNumber`. Aplikasi menerima keduanya.
- Status inquiry: aplikasi membaca `transactionStatusDesc` (`paid`/`pending`/`fail`), dengan cadangan kode `latestTransactionStatus` (`00`/`02` lunas, `01` menunggu, `05` batal, `07` tidak ditemukan).
- Apakah callback diulang (retry) bila merchant membalas selain 200.

Semua permintaan & respons AINO dicatat (tanpa secret) di `storage/logs/laravel.log` (butuh `LOG_LEVEL=info`, sudah menjadi default di `.env.example`).

### Donasi lewat QRIS

Penggalangan dana yang dicentang **Terima donasi lewat QRIS** menampilkan formulir donasi di halaman umum
(boleh tanpa login). Pengunjung memilih nominal (minimal `AINO_DONASI_MINIMAL`, bawaan Rp10.000), bisa
menyembunyikan nama, lalu membayar QRIS. Setelah AINO melaporkan lunas (diverifikasi lewat Query Payment,
sama seperti iuran), donatur otomatis tercatat. Nominal per donatur tetap tidak ditampilkan untuk umum.
Transaksi donasi terlihat di halaman pengelolaan penggalangan dana, terpisah dari rekap iuran.

### Perintah terkait

```bash
php artisan iuran:generate            # buat tagihan bulan ini (aman diulang)
php artisan iuran:generate 2026-10    # untuk bulan tertentu
php artisan pembayaran:sinkron        # cek ulang semua transaksi QRIS yang masih pending
```

---

## Pengujian otomatis

```bash
composer install          # termasuk paket dev
php artisan test
```

Tes memakai SQLite in-memory dan memalsukan API AINO (`Http::fake`), mencakup: login & akun nonaktif, hak akses per peran & per RT, kerahasiaan NIK, tambah KK + kompres foto, denah, pencarian, pengumuman per RT, pembuatan tagihan, alur QRIS sampai lunas, callback palsu, nominal tidak cocok, pencegahan QRIS ganda, transaksi kedaluwarsa yang kemudian lunas, dan pencatatan tunai.

## Mengubah tampilan (opsional, perlu Node.js di komputer pengembang)

```bash
npm install
npm run build    # membangun public/css/app.css (Tailwind CSS 4) & menyalin Alpine.js + qrcode-generator
```

## Struktur penting

```
app/Http/Controllers/     Denah, Keluarga, Anggota, Pencarian, Pengumuman, Iuran, Pembayaran, AinoNotify, ...
app/Services/AinoClient.php         klien API AINO (generate, inquiry, pemetaan status)
app/Services/PembayaranService.php  buat QRIS, verifikasi, tandai lunas (transaksi DB + row lock)
app/Services/TagihanService.php     hitung tarif & buat tagihan bulanan
app/Support/FotoUploader.php        kompres & simpan foto (GD)
database/migrations/                skema PostgreSQL
resources/views/                    tampilan Blade (Tailwind + Alpine.js)
routes/web.php, routes/console.php  rute & jadwal cron
deploy/                             contoh konfigurasi Nginx, crontab, skrip update
```

## Privasi data warga

Aplikasi menyimpan NIK, No. KK, tanggal lahir, dan foto — termasuk **data pribadi** menurut UU No. 27/2022 tentang Pelindungan Data Pribadi. Disarankan:

- Minta persetujuan warga sebelum mendata & memasang foto.
- Gunakan HTTPS, password kuat, dan nonaktifkan akun pengurus yang sudah tidak menjabat.
- Warga lain hanya melihat nama, hubungan keluarga, dan foto — NIK & No. KK disembunyikan.
- Cadangkan database & folder `storage/app/public` secara berkala, mis. `pg_dump siwarga > backup.sql`.
