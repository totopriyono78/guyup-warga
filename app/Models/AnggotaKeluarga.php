<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AnggotaKeluarga extends Model
{
    use HasFactory;

    protected $table = 'anggota_keluargas';

    public const HUBUNGAN = [
        'Kepala Keluarga', 'Suami', 'Istri', 'Anak', 'Menantu', 'Cucu',
        'Orang Tua', 'Mertua', 'Famili Lain', 'Pembantu', 'Lainnya',
    ];

    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan'];

    public const PENDIDIKAN = [
        'Tidak/Belum Sekolah', 'Belum Tamat SD', 'SD/Sederajat', 'SMP/Sederajat', 'SMA/Sederajat',
        'Diploma I/II', 'Diploma III', 'Diploma IV/S1', 'S2', 'S3',
    ];

    public const STATUS_PERKAWINAN = ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'];

    protected $fillable = [
        'kartu_keluarga_id', 'nik', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
        'hubungan', 'agama', 'pendidikan', 'pekerjaan', 'status_perkawinan', 'no_hp', 'foto', 'urutan',
    ];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    public function kartuKeluarga(): BelongsTo
    {
        return $this->belongsTo(KartuKeluarga::class);
    }

    public function getUmurAttribute(): ?int
    {
        return $this->tanggal_lahir?->age;
    }

    public function fotoUrl(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    /** NIK disamarkan untuk tampilan ke warga lain: 3201********0001 */
    public function getNikSamarAttribute(): ?string
    {
        if (! $this->nik) {
            return null;
        }

        return substr($this->nik, 0, 4).str_repeat('•', max(0, strlen($this->nik) - 8)).substr($this->nik, -4);
    }
}
