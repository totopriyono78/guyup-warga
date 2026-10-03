<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis iuran (tarif_iurans) kini punya frekuensi bulanan / tahunan / insidental
 * dan bisa bersifat sukarela. Tagihan dibuat terpisah per jenis iuran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarif_iurans', function (Blueprint $table) {
            $table->string('frekuensi', 12)->default('bulanan')->after('nominal'); // bulanan|tahunan|insidental
            $table->unsignedTinyInteger('bulan_tagih')->nullable()->after('frekuensi'); // tahunan: bulan 1-12
            $table->date('tenggat')->nullable()->after('bulan_tagih');                  // insidental: batas bayar
            $table->boolean('sukarela')->default(false)->after('tenggat');             // nominal = minimal
            $table->timestamp('diterbitkan_pada')->nullable()->after('sukarela');      // insidental: kapan tagihan dibuat
        });

        Schema::table('tagihans', function (Blueprint $table) {
            $table->foreignId('tarif_iuran_id')->nullable()->after('kartu_keluarga_id')->constrained('tarif_iurans')->nullOnDelete();
            $table->string('judul')->nullable()->after('periode');              // mis. "Kebersihan – Oktober 2026"
            $table->date('jatuh_tempo')->nullable()->after('judul');
            $table->boolean('sukarela')->default(false)->after('nominal');       // nominal = minimal
            $table->unsignedInteger('nominal_dibayar')->nullable()->after('sukarela');
        });

        Schema::table('tagihans', function (Blueprint $table) {
            $table->dropUnique(['kartu_keluarga_id', 'periode']);
            $table->unique(['kartu_keluarga_id', 'tarif_iuran_id', 'periode']);
            $table->index('tarif_iuran_id');
        });

        Schema::table('pembayaran_tagihan', function (Blueprint $table) {
            $table->unsignedInteger('nominal')->nullable(); // nominal yang dibayar untuk tagihan ini
        });
    }

    public function down(): void
    {
        // Tagihan per jenis tidak bisa dikembalikan ke format lama (1 tagihan per KK per bulan) tanpa kehilangan data.
        if (\Illuminate\Support\Facades\DB::table('tagihans')->whereNotNull('tarif_iuran_id')->exists()) {
            throw new \RuntimeException('Rollback dibatalkan: sudah ada tagihan per jenis iuran. Cadangkan lalu hapus tagihan tersebut terlebih dahulu bila benar-benar ingin rollback.');
        }

        Schema::table('pembayaran_tagihan', function (Blueprint $table) {
            $table->dropColumn('nominal');
        });

        Schema::table('tagihans', function (Blueprint $table) {
            $table->dropUnique(['kartu_keluarga_id', 'tarif_iuran_id', 'periode']);
            $table->dropIndex(['tarif_iuran_id']);
            $table->dropConstrainedForeignId('tarif_iuran_id');
            $table->dropColumn(['judul', 'jatuh_tempo', 'sukarela', 'nominal_dibayar']);
            $table->unique(['kartu_keluarga_id', 'periode']);
        });

        Schema::table('tarif_iurans', function (Blueprint $table) {
            $table->dropColumn(['frekuensi', 'bulan_tagih', 'tenggat', 'sukarela', 'diterbitkan_pada']);
        });
    }
};
