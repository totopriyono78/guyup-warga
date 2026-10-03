<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_iurans', function (Blueprint $table) {
            $table->id();
            $table->string('nama');                                  // Iuran Keamanan, Kebersihan, Kas RT, ...
            $table->unsignedInteger('nominal');
            // null = berlaku untuk semua RT
            $table->foreignId('rt_id')->nullable()->constrained('rts')->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('tagihans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_keluarga_id')->constrained('kartu_keluargas')->cascadeOnDelete();
            $table->date('periode');                                 // selalu tanggal 1 bulan tsb
            $table->unsignedInteger('nominal');
            $table->json('rincian')->nullable();                     // [{nama, nominal}]
            $table->string('status', 10)->default('belum');          // belum|lunas
            $table->string('metode', 10)->nullable();                // qris|va|tunai|transfer
            $table->timestamp('dibayar_pada')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan')->nullable();
            $table->timestamps();
            $table->unique(['kartu_keluarga_id', 'periode']);
            $table->index(['periode', 'status']);
        });

        Schema::create('pembayarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_keluarga_id')->constrained('kartu_keluargas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('order_id')->unique();                      // transaction_details.order_id
            $table->string('reference_no', 64)->nullable()->index(); // referenceNo dari AINO
            $table->string('acquire_reference_no', 64)->nullable();
            $table->string('payment_type', 20)->default('qr');
            $table->unsignedInteger('jumlah_iuran');
            $table->unsignedInteger('biaya')->default(0);
            $table->unsignedInteger('total');                        // gross_amount
            $table->string('status', 10)->default('pending')->index(); // pending|paid|expired|failed|canceled
            $table->text('payment_content')->nullable();             // string QRIS / nomor VA
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('response_generate')->nullable();
            $table->json('response_terakhir')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_tagihan', function (Blueprint $table) {
            $table->foreignId('pembayaran_id')->constrained('pembayarans')->cascadeOnDelete();
            $table->foreignId('tagihan_id')->constrained('tagihans')->cascadeOnDelete();
            $table->primary(['pembayaran_id', 'tagihan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_tagihan');
        Schema::dropIfExists('pembayarans');
        Schema::dropIfExists('tagihans');
        Schema::dropIfExists('tarif_iurans');
    }
};
