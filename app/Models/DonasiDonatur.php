<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonasiDonatur extends Model
{
    protected $table = 'donasi_donaturs';

    protected $fillable = ['donasi_id', 'nama', 'anonim', 'nominal', 'tanggal', 'kartu_keluarga_id', 'catatan', 'dicatat_oleh', 'pembayaran_id'];

    protected function casts(): array
    {
        return ['anonim' => 'boolean', 'nominal' => 'integer', 'tanggal' => 'date'];
    }

    public function donasi(): BelongsTo
    {
        return $this->belongsTo(Donasi::class);
    }

    public function kartuKeluarga(): BelongsTo
    {
        return $this->belongsTo(KartuKeluarga::class);
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    /** Nama yang aman ditampilkan di halaman umum. */
    public function getNamaTampilAttribute(): string
    {
        return $this->anonim ? 'Donatur anonim' : $this->nama;
    }
}
