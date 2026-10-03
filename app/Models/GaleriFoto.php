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
        return Storage::disk('public')->url($this->path);
    }
}
