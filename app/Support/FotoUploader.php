<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan foto ke disk "public" setelah diperkecil & dikompres (GD),
 * supaya foto dari kamera HP (3–10 MB) tidak membebani server.
 */
class FotoUploader
{
    public static function simpan(UploadedFile $file, string $folder): string
    {
        $maxPx = (int) config('siwarga.foto_max_px', 1000);
        $path = trim($folder, '/').'/'.now()->format('Y/m').'/'.Str::random(32).'.jpg';

        $sumber = self::buka($file);

        if (! $sumber) {
            // Format tidak dikenali GD: simpan apa adanya
            return $file->store(trim($folder, '/').'/'.now()->format('Y/m'), 'public');
        }

        $sumber = self::perbaikiOrientasi($sumber, $file);

        $w = imagesx($sumber);
        $h = imagesy($sumber);
        $skala = min(1, $maxPx / max($w, $h));
        $nw = max(1, (int) round($w * $skala));
        $nh = max(1, (int) round($h * $skala));

        $hasil = imagecreatetruecolor($nw, $nh);
        imagefill($hasil, 0, 0, imagecolorallocate($hasil, 255, 255, 255)); // latar putih untuk PNG transparan
        imagecopyresampled($hasil, $sumber, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($hasil, null, 82);
        $jpeg = ob_get_clean();

        imagedestroy($sumber);
        imagedestroy($hasil);

        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    public static function hapus(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function buka(UploadedFile $file): \GdImage|false
    {
        $isi = @file_get_contents($file->getRealPath());

        return $isi === false ? false : @imagecreatefromstring($isi);
    }

    private static function perbaikiOrientasi(\GdImage $img, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'])) {
            return $img;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientasi = $exif['Orientation'] ?? 1;

        $putar = match ((int) $orientasi) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $putar ? (imagerotate($img, $putar, 0) ?: $img) : $img;
    }
}
