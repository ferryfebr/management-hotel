<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class UsageService
{
    private const CACHE_KEY = 'system:usage-snapshot';

    private const CACHE_SECONDS = 600;

    /**
     * Estimasi pemakaian aplikasi. Bukan pembacaan kuota resmi cPanel.
     */
    public function snapshot(bool $fresh = false): array
    {
        if (! $fresh) {
            try {
                $cached = Cache::get(self::CACHE_KEY);

                if (is_array($cached)) {
                    return $cached;
                }
            } catch (Throwable) {
                // Cache database bisa gagal saat disk penuh; tetap hitung langsung.
            }
        }

        $snapshot = $this->calculate();

        try {
            Cache::put(self::CACHE_KEY, $snapshot, self::CACHE_SECONDS);
        } catch (Throwable) {
            // Abaikan kegagalan cache.
        }

        return $snapshot;
    }

    public function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Abaikan.
        }
    }

    public function calculate(): array
    {
        $mb = 1024 * 1024;

        $code = 0;

        foreach ([
            base_path('app'),
            base_path('bootstrap'),
            base_path('config'),
            base_path('database'),
            base_path('lang'),
            base_path('public'),
            base_path('resources'),
            base_path('routes'),
            base_path('vendor'),
        ] as $path) {
            $code += $this->directorySize($path);
        }

        foreach (glob(base_path('*')) ?: [] as $file) {
            if (is_file($file)) {
                $code += (int) filesize($file);
            }
        }

        $idCards = $this->directorySize(storage_path('app/private/id-cards'));
        $roomProofs = $this->directorySize(storage_path('app/private/room-proofs'));
        $archives = $this->directorySize(storage_path('app/private/archives'));
        $backups = $this->directorySize(storage_path('app/private/backups'));
        $logs = $this->directorySize(storage_path('logs'));

        $privateTotal = $this->directorySize(storage_path('app/private'));
        $otherStorage = max($privateTotal - $idCards - $roomProofs - $archives - $backups, 0)
            + $this->directorySize(storage_path('app/public'))
            + $this->directorySize(storage_path('framework'));

        $database = $this->databaseSizeBytes();

        $used = $code + $database + $idCards + $roomProofs + $archives + $backups + $logs + $otherStorage;

        $quotaMb = max((int) config('hosting.quota_mb', 2048), 1);
        $quotaBytes = $quotaMb * $mb;
        $dbBudgetMb = max((int) config('retention.db_budget_mb', 400), 1);
        $dbBudgetBytes = $dbBudgetMb * $mb;

        $percent = round(($used / $quotaBytes) * 100, 2);
        $dbPercent = round(($database / $dbBudgetBytes) * 100, 2);

        return [
            'quota_mb' => $quotaMb,
            'reserve_mb' => (int) config('hosting.reserve_mb', 300),
            'used_bytes' => $used,
            'used_mb' => round($used / $mb, 2),
            'free_mb' => round(max($quotaBytes - $used, 0) / $mb, 2),
            'percent' => $percent,
            'status' => $this->diskStatus($percent),
            'db_budget_mb' => $dbBudgetMb,
            'db_used_bytes' => $database,
            'db_used_mb' => round($database / $mb, 2),
            'db_percent' => $dbPercent,
            'db_status' => $this->databaseStatus($dbPercent),
            'categories' => [
                ['key' => 'code', 'label' => 'Kode + Vendor', 'bytes' => $code],
                ['key' => 'database', 'label' => 'Database', 'bytes' => $database],
                ['key' => 'id_cards', 'label' => 'Foto KTP', 'bytes' => $idCards],
                ['key' => 'room_proofs', 'label' => 'Foto Bukti Kerja', 'bytes' => $roomProofs],
                ['key' => 'archives', 'label' => 'File Arsip', 'bytes' => $archives],
                ['key' => 'backups', 'label' => 'Backup', 'bytes' => $backups],
                ['key' => 'logs', 'label' => 'Log Aplikasi', 'bytes' => $logs],
                ['key' => 'other', 'label' => 'Storage Lain', 'bytes' => $otherStorage],
            ],
        ];
    }

    public function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $size += $file->getSize();
                }
            }
        } catch (Throwable) {
            return $size;
        }

        return $size;
    }

    public function databaseSizeBytes(): int
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                $database = DB::connection()->getDatabaseName();

                if ($database === ':memory:' || ! is_file($database)) {
                    return 0;
                }

                return (int) filesize($database);
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS size
                     FROM information_schema.tables
                     WHERE table_schema = ?',
                    [DB::connection()->getDatabaseName()]
                );

                return (int) ($row->size ?? 0);
            }
        } catch (Throwable) {
            return 0;
        }

        return 0;
    }

    private function diskStatus(float $percent): string
    {
        return match (true) {
            $percent >= 95 => 'lock',
            $percent >= 85 => 'critical',
            $percent >= 75 => 'warn',
            default => 'ok',
        };
    }

    private function databaseStatus(float $percent): string
    {
        return match (true) {
            $percent >= 75 => 'lock',
            $percent >= 70 => 'force',
            $percent >= 65 => 'archive',
            $percent >= 60 => 'warn',
            default => 'ok',
        };
    }
}
