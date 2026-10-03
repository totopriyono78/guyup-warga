<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donasi bisa dibayar lewat QRIS (AINO). Transaksi donasi memakai tabel pembayarans yang sama
 * dengan iuran, sehingga callback Finish Notify & sinkron status berjalan untuk keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            // donatur dari halaman umum tidak selalu punya Kartu Keluarga
            $table->foreignId('kartu_keluarga_id')->nullable()->change();
            $table->foreignId('donasi_id')->nullable()->after('kartu_keluarga_id')->constrained('donasis')->nullOnDelete();
            $table->string('donatur_nama')->nullable()->after('donasi_id');
            $table->boolean('donatur_anonim')->default(false)->after('donatur_nama');
            $table->string('donatur_pesan', 255)->nullable()->after('donatur_anonim');
        });

        Schema::table('donasi_donaturs', function (Blueprint $table) {
            $table->foreignId('pembayaran_id')->nullable()->unique()->constrained('pembayarans')->nullOnDelete();
        });

        Schema::table('donasis', function (Blueprint $table) {
            $table->boolean('terima_qris')->default(true)->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('donasis', fn (Blueprint $t) => $t->dropColumn('terima_qris'));
        Schema::table('donasi_donaturs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pembayaran_id');
        });
        Schema::table('pembayarans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('donasi_id');
            $t->dropColumn(['donatur_nama', 'donatur_anonim', 'donatur_pesan']);
        });
    }
};
