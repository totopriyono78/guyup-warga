<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_keluargas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rumah_id')->nullable()->constrained('rumahs')->nullOnDelete();
            $table->string('no_kk', 16)->nullable()->unique();
            $table->string('nama_kepala')->index();
            $table->string('status_tinggal', 10)->default('tetap'); // tetap|kontrak|kos
            $table->string('no_hp', 20)->nullable();
            $table->string('foto')->nullable();                     // foto keluarga / rumah
            $table->date('tanggal_masuk')->nullable();
            $table->boolean('aktif')->default(true);                // false = sudah pindah
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('anggota_keluargas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_keluarga_id')->constrained('kartu_keluargas')->cascadeOnDelete();
            $table->string('nik', 16)->nullable()->unique();
            $table->string('nama')->index();
            $table->char('jenis_kelamin', 1);                       // L / P
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('hubungan', 30);                         // Kepala Keluarga, Istri, Anak, ...
            $table->string('agama', 20)->nullable();
            $table->string('pendidikan', 40)->nullable();
            $table->string('pekerjaan', 60)->nullable();
            $table->string('status_perkawinan', 20)->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('foto')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('rt_id')->nullable()->after('role')->constrained('rts')->nullOnDelete();
            $table->foreignId('kartu_keluarga_id')->nullable()->after('rt_id')->constrained('kartu_keluargas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kartu_keluarga_id');
            $table->dropConstrainedForeignId('rt_id');
        });
        Schema::dropIfExists('anggota_keluargas');
        Schema::dropIfExists('kartu_keluargas');
    }
};
