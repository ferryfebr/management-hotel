<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ArchiveBuilder
{
    /**
     * Bangun isi CSV dengan BOM agar terbaca benar di Excel.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function csv(array $headers, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Tidak bisa membuat buffer CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false ? '' : $content;
    }

    /**
     * Simpan beberapa file ke ZIP di disk privat.
     *
     * @param  array<string, string|array{source: string}>  $entries  nama file di ZIP => isi file / path sumber
     */
    public static function zip(string $diskName, string $path, array $entries): int
    {
        $disk = Storage::disk($diskName);
        $directory = dirname($path);

        if ($directory !== '.') {
            $disk->makeDirectory($directory);
        }

        $fullPath = $disk->path($path);

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        $zip = new ZipArchive;

        if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file ZIP.');
        }

        foreach ($entries as $name => $content) {
            $added = is_array($content) && isset($content['source'])
                ? $zip->addFile($content['source'], $name)
                : $zip->addFromString($name, (string) $content);

            if (! $added) {
                $zip->close();
                @unlink($fullPath);

                throw new RuntimeException('Gagal menambahkan file ke ZIP: '.$name);
            }
        }

        $zip->close();

        return is_file($fullPath) ? (int) filesize($fullPath) : 0;
    }
}
