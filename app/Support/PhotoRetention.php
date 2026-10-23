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
    public static function prune(string $folder, ?int $max = null, ?int $maxBytes = null): void
    {
        $max ??= 1000;
        $disk = Storage::disk('private');

        // Hanya hitung file foto (abaikan .htaccess/index.html penjaga folder).
        $files = self::photoFiles($disk, $folder);

        $entries = array_map(fn ($path) => [
            'path' => $path,
            'time' => $disk->lastModified($path),
            'size' => $disk->size($path),
        ], $files);

        usort($entries, fn ($a, $b) => $a['time'] <=> $b['time']);

        $remainingCount = count($entries);
        $remainingBytes = array_sum(array_column($entries, 'size'));

        if ($remainingCount <= $max && ($maxBytes === null || $remainingBytes <= $maxBytes)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($remainingCount <= $max && ($maxBytes === null || $remainingBytes <= $maxBytes)) {
                break;
            }

            $disk->delete($entry['path']);
            self::nullReference($folder, $entry['path']);
            $remainingCount--;
            $remainingBytes -= $entry['size'];
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
