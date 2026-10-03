<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumumans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // null = pengumuman RW (untuk semua warga), terisi = khusus RT tersebut
            $table->foreignId('rt_id')->nullable()->constrained('rts')->cascadeOnDelete();
            $table->string('judul');
            $table->text('isi');
            $table->boolean('penting')->default(false);
            $table->string('lampiran')->nullable();
            $table->string('lampiran_nama')->nullable();
            $table->timestamp('terbit_pada')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pengaturans', function (Blueprint $table) {
            $table->string('kunci', 60)->primary();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturans');
        Schema::dropIfExists('pengumumans');
    }
};
