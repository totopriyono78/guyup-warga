<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rt_id')->nullable()->constrained('rts')->nullOnDelete();   // null = kegiatan tingkat RW
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('lokasi')->nullable();
            $table->boolean('publik')->default(true);                                    // tampil di halaman umum
            $table->unsignedBigInteger('sampul_id')->nullable();
            $table->timestamps();
            $table->index(['publik', 'tanggal']);
        });

        Schema::create('galeri_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('galeri_id')->constrained('galeris')->cascadeOnDelete();
            $table->string('path');
            $table->string('keterangan')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeri_fotos');
        Schema::dropIfExists('galeris');
    }
};
