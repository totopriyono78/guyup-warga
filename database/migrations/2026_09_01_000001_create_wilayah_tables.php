<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rts', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 5)->unique();          // "01", "02", ...
            $table->string('nama_ketua')->nullable();
            $table->string('no_hp_ketua', 20)->nullable();
            $table->string('warna', 7)->default('#0f766e'); // warna penanda di denah
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('bloks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rt_id')->constrained('rts')->cascadeOnDelete();
            $table->string('nama', 30);                     // "A", "B1", "Mawar", ...
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['rt_id', 'nama']);
        });

        Schema::create('rumahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blok_id')->constrained('bloks')->cascadeOnDelete();
            $table->string('nomor', 10);
            // posisi petak rumah di denah blok (grid baris x kolom)
            $table->unsignedSmallInteger('baris')->default(1);
            $table->unsignedSmallInteger('kolom')->default(1);
            $table->string('status_hunian', 12)->default('dihuni'); // dihuni|kosong|kontrakan|usaha
            $table->string('pemilik')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['blok_id', 'nomor']);
            $table->unique(['blok_id', 'baris', 'kolom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rumahs');
        Schema::dropIfExists('bloks');
        Schema::dropIfExists('rts');
    }
};
