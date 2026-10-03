<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GaleriFoto extends Model
{
    protected $table = 'galeri_fotos';

    protected $fillable = ['galeri_id', 'path', 'keterangan', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function galeri(): BelongsTo
    {
        return $this->belongsTo(Galeri::class);
    }

    public function url(): string
    {
        // gambar contoh bawaan aplikasi ada di public/img, foto unggahan di storage publik
        if (str_starts_with($this->path, 'img/')) {
            return asset($this->path);
        }
        // data contoh versi lama (disalin ke storage/galeri/contoh): pakai salinan bawaan aplikasi
        if (str_starts_with($this->path, 'galeri/contoh/') && is_file(public_path('img/galeri-contoh/'.basename($this->path)))) {
            return asset('img/galeri-contoh/'.basename($this->path));
        }

        return Storage::disk('public')->url($this->path);
    }
}
