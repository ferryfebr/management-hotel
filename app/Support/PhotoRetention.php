<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\RoomLog;
use Illuminate\Support\Facades\Storage;

/**
 * Retensi foto berbasis jumlah file per folder.
 *
 * Hosting hanya punya storage terbatas, sedangkan foto KTP & foto bukti kerja
 * terus bertambah setiap check-in / update status kamar. Helper ini menjaga
 * tiap folder tetap maksimal $max file: bila melebihi, file paling lama
 * (berdasarkan waktu modifikasi) dihapus permanen dan kolom DB yang menunjuk
 * file tersebut di-NULL-kan agar tidak ada link mati.
 *
 * Sengaja dijalankan in-request (bukan queue) karena hosting production
 * tidak mendukung queue worker (prd.md §5, FR-8).
 */
class PhotoRetention
{
    /**
     * Folder -> model + kolom yang menyimpan path file.
     */
    private const MAP = [
        'id-cards' => [Customer::class, 'id_card_photo'],
        'room-proofs' => [RoomLog::class, 'proof_photo'],
    ];

    /**
     * Hapus file paling lama di folder bila jumlahnya melebihi batas.
     *
     * Optimasi: cek jumlah file dulu (murah); daftar + hapus hanya
     * dijalankan saat benar-benar melewati batas.
     */
    public static function prune(string $folder, int $max = 1000): void
    {
        $disk = Storage::disk('private');

        // Hanya hitung file foto (abaikan .htaccess/index.html penjaga folder).
        $files = self::photoFiles($disk, $folder);

        if (count($files) <= $max) {
            return;
        }

        $timestamps = array_map(fn ($path) => $disk->lastModified($path), $files);
        array_multisort($timestamps, SORT_ASC, $files);

        $overflow = count($files) - $max;

        foreach (array_slice($files, 0, $overflow) as $path) {
            $disk->delete($path);
            self::nullReference($folder, $path);
        }
    }

    /**
     * Daftar file foto (ekstensi gambar) di folder, bukan file sistem.
     */
    private static function photoFiles($disk, string $folder): array
    {
        return array_values(array_filter(
            $disk->files($folder),
            fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)
        ));
    }

    /**
     * Kosongkan kolom DB yang menunjuk file yang sudah dihapus.
     */
    private static function nullReference(string $folder, string $path): void
    {
        if (! isset(self::MAP[$folder])) {
            return;
        }

        [$model, $column] = self::MAP[$folder];

        $model::query()->where($column, $path)->update([$column => null]);
    }
}