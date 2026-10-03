<?php

namespace App\Services;

use App\Models\Pembayaran;
use InvalidArgumentException;

/** Salah satu tagihan yang dipilih sudah punya QRIS aktif yang belum kedaluwarsa. */
class PembayaranAktifException extends InvalidArgumentException
{
    public function __construct(public readonly Pembayaran $pembayaran)
    {
        parent::__construct(
            'Masih ada QRIS aktif untuk '.$pembayaran->tagihans->map(fn ($t) => $t->periode_label)->join(', ')
            .' (berlaku sampai '.$pembayaran->expired_at?->format('H:i').'). Selesaikan pembayaran itu atau tunggu sampai kedaluwarsa.'
        );
    }
}
