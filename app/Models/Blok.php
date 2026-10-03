<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blok extends Model
{
    use HasFactory;

    protected $table = 'bloks';

    protected $fillable = ['rt_id', 'nama', 'urutan', 'keterangan'];

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function rumahs(): HasMany
    {
        return $this->hasMany(Rumah::class)->orderBy('baris')->orderBy('kolom');
    }

    public function getLabelAttribute(): string
    {
        return 'Blok '.$this->nama;
    }
}
