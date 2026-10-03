<?php

use App\Models\Rt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman umum: pengumuman yang boleh tampil publik, penggalangan dana (donasi) & donatur,
 * serta warna pembeda per RT (RT 01 merah, RT 02 biru, RT 03 kuning, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengumumans', function (Blueprint $table) {
            $table->boolean('publik')->default(false)->after('penting');
        });

        Schema::create('donasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rt_id')->nullable()->constrained('rts')->cascadeOnDelete(); // null = penggalangan RW
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('judul');
            $table->string('ringkasan', 255)->nullable();
            $table->text('deskripsi')->nullable();
            $table->unsignedBigInteger('target')->nullable();
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->string('gambar')->nullable();
            $table->text('cara_donasi')->nullable();          // rekening / kontak bendahara
            $table->boolean('tampilkan_total')->default(true); // total terkumpul tampil di halaman umum
            $table->boolean('publik')->default(true);
            $table->boolean('aktif')->default(true);           // false = sudah ditutup
            $table->timestamps();
        });

        Schema::create('donasi_donaturs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donasi_id')->constrained('donasis')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('anonim')->default(false);         // tampil sebagai "Donatur anonim"
            $table->unsignedBigInteger('nominal');             // tidak pernah ditampilkan di halaman umum
            $table->date('tanggal');
            $table->foreignId('kartu_keluarga_id')->nullable()->constrained('kartu_keluargas')->nullOnDelete();
            $table->string('catatan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['donasi_id', 'tanggal']);
        });

        // Warna pembeda RT: ganti warna bawaan lama (hijau tua / biru tua / cokelat) dengan palet baru
        $lama = ['#0f766e', '#1d4ed8', '#b45309'];
        foreach (DB::table('rts')->orderBy('nomor')->get(['id', 'warna']) as $i => $rt) {
            if (in_array(strtolower((string) $rt->warna), $lama, true)) {
                DB::table('rts')->where('id', $rt->id)->update(['warna' => Rt::PALET[$i % count(Rt::PALET)]]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('donasi_donaturs');
        Schema::dropIfExists('donasis');
        Schema::table('pengumumans', function (Blueprint $table) {
            $table->dropColumn('publik');
        });
    }
};
