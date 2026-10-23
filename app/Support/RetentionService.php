<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ArchiveIndex;
use App\Models\Customer;
use App\Models\MonthlyRevenue;
use App\Models\RoomLog;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RetentionService
{
    private const LAST_CHECK_KEY = 'retention:last-check';

    private const EMERGENCY_KEY = 'retention:emergency';

    private static bool $running = false;

    public function __construct(private readonly UsageService $usage) {}

    /**
     * Dipanggil dari model event. Dibatasi 60 detik agar tidak membebani request.
     */
    public function autoRun(): void
    {
        if (self::$running) {
            return;
        }

        try {
            $last = (int) Cache::get(self::LAST_CHECK_KEY, 0);

            if ($last > 0 && (time() - $last) < 60) {
                return;
            }

            Cache::put(self::LAST_CHECK_KEY, time(), 120);
        } catch (Throwable) {
            // Cache bisa gagal saat disk penuh; cleanup tetap dicoba.
        }

        if ($this->needsEnforcement()) {
            $this->enforce();
        }
    }

    private function needsEnforcement(): bool
    {
        $limits = (array) config('retention.limits', []);

        if (Transaction::count() > (int) ($limits['transactions'] ?? 50000)) {
            return true;
        }

        if (RoomLog::count() > (int) ($limits['room_logs'] ?? 100000)) {
            return true;
        }

        if (ActivityLog::count() > (int) ($limits['activity_logs'] ?? 50000)) {
            return true;
        }

        if (Customer::withTrashed()->count() > (int) ($limits['customers'] ?? 100000)) {
            return true;
        }

        $budget = max((int) config('retention.db_budget_mb', 400), 1) * 1024 * 1024;
        $warn = (float) config('retention.thresholds.warn', 0.60) * $budget;

        return $this->usage->databaseSizeBytes() >= $warn;
    }

    public function enforce(): array
    {
        if (self::$running) {
            return ['skipped' => true, 'reason' => 'Retention sedang berjalan.'];
        }

        self::$running = true;
        $summary = [
            'transactions' => 0,
            'room_logs' => 0,
            'activity_logs' => 0,
            'customers' => 0,
            'sessions' => 0,
            'cache' => 0,
            'logs' => 0,
            'archives' => 0,
            'backups' => 0,
            'db_percent' => 0,
            'disk_percent' => 0,
        ];

        try {
            $summary['sessions'] = $this->pruneExpiredSessions();
            $summary['cache'] = $this->pruneExpiredCache();
            $summary['logs'] = $this->pruneApplicationLogs();

            $summary['activity_logs'] = $this->enforceLimit(
                'activity_logs',
                (int) config('retention.limits.activity_logs', 50000),
                fn () => ActivityLog::count(),
                fn () => $this->archiveActivityLogs()
            );

            $summary['room_logs'] = $this->enforceLimit(
                'room_logs',
                (int) config('retention.limits.room_logs', 100000),
                fn () => RoomLog::count(),
                fn () => $this->archiveRoomLogs()
            );

            $summary['transactions'] = $this->enforceLimit(
                'transactions',
                (int) config('retention.limits.transactions', 50000),
                fn () => Transaction::count(),
                fn () => $this->archiveTransactions()
            );

            // Setelah transaksi lama keluar, customer tanpa transaksi boleh diarsipkan.
            $summary['customers'] = $this->enforceLimit(
                'customers',
                (int) config('retention.limits.customers', 100000),
                fn () => Customer::withTrashed()->count(),
                fn () => $this->archiveCustomers()
            );

            // Setelah arsip lama dibuat, baru pangkas file foto yang melewati budget.
            $this->pruneMedia();

            // Tekanan ukuran database: archive transaksi tambahan walau batas baris belum lewat.
            $archiveThreshold = (float) config('retention.thresholds.archive', 0.65);
            $budget = max((int) config('retention.db_budget_mb', 400), 1) * 1024 * 1024;

            for ($i = 0; $i < 10; $i++) {
                $dbPercent = ($this->usage->databaseSizeBytes() / $budget) * 100;

                if ($dbPercent < ($archiveThreshold * 100)) {
                    break;
                }

                $index = $this->archiveTransactions();

                if (! $index) {
                    break;
                }

                $summary['transactions'] += $index->record_count;
            }

            $summary['archives'] = $this->pruneArchiveStorage();
            $summary['backups'] = $this->pruneBackupStorage();
            $this->pruneArchiveIndex();

            $snapshot = $this->usage->snapshot(true);
            $summary['db_percent'] = $snapshot['db_percent'];
            $summary['disk_percent'] = $snapshot['percent'];

            $lockThreshold = (float) config('retention.thresholds.lock', 0.75);

            try {
                if ($snapshot['db_percent'] >= ($lockThreshold * 100)) {
                    Cache::put(self::EMERGENCY_KEY, true, 3600);
                } else {
                    Cache::forget(self::EMERGENCY_KEY);
                }
            } catch (Throwable) {
                // Abaikan.
            }

            Log::info('Retention selesai.', $summary);
        } catch (Throwable $e) {
            Log::error('Retention gagal: '.$e->getMessage(), ['exception' => $e]);
            $summary['error'] = $e->getMessage();
        } finally {
            self::$running = false;
            $this->usage->forget();
        }

        return $summary;
    }

    public function pendingCustomerExportCount(): int
    {
        $lastId = (int) (ArchiveIndex::query()
            ->where('category', ArchiveIndex::CATEGORY_CUSTOMER_EXPORT)
            ->max('last_record_id') ?? 0);

        return Customer::query()->where('id', '>', $lastId)->count();
    }

    public function exportCustomerBatch(?int $batch = null): ?ArchiveIndex
    {
        $batch = max($batch ?? (int) config('retention.customer_export.batch', 300), 1);
        $lastId = (int) (ArchiveIndex::query()
            ->where('category', ArchiveIndex::CATEGORY_CUSTOMER_EXPORT)
            ->max('last_record_id') ?? 0);

        $customers = Customer::query()
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($batch)
            ->get();

        if ($customers->isEmpty()) {
            return null;
        }

        $entries = [
            'customers.csv' => ArchiveBuilder::csv(
                ['id', 'name', 'phone', 'id_card_number', 'rating_status', 'visit_count', 'notes', 'foto_ktp', 'created_at'],
                $customers->map(fn (Customer $customer) => [
                    $customer->id,
                    $customer->name,
                    $customer->phone,
                    $customer->id_card_number,
                    $customer->rating_status,
                    $customer->visit_count,
                    $customer->notes,
                    $customer->id_card_photo ? 'ktp/'.basename($customer->id_card_photo) : '',
                    optional($customer->created_at)->toDateTimeString(),
                ])
            ),
        ];

        $entries = array_merge($entries, $this->photoEntries($customers->pluck('id_card_photo')->all(), 'ktp'));

        $entries['manifest.json'] = json_encode([
            'type' => ArchiveIndex::CATEGORY_CUSTOMER_EXPORT,
            'generated_at' => now()->toDateTimeString(),
            'record_count' => $customers->count(),
            'first_id' => $customers->first()->id,
            'last_id' => $customers->last()->id,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $index = $this->writeArchive(
            ArchiveIndex::CATEGORY_CUSTOMER_EXPORT,
            'customer-export',
            $entries,
            $customers->count(),
            $customers->first()->id,
            $customers->last()->id,
            null,
            null
        );

        $this->pruneArchiveStorage();

        return $index;
    }

    public function createBackup(): ArchiveIndex
    {
        $diskName = (string) config('retention.backup.disk', 'private');
        $directory = trim((string) config('retention.backup.path', 'backups'), '/');
        $tempDirectory = storage_path('app/private/'.$directory.'/.tmp');

        File::ensureDirectoryExists($tempDirectory);

        $tables = [
            'users',
            'room_types',
            'rooms',
            'customers',
            'transactions',
            'payments',
            'room_transfers',
            'stay_extensions',
            'room_logs',
            'activity_logs',
            'monthly_revenue',
            'archive_index',
        ];

        $entries = [];
        $totalRows = 0;

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);
                $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.$table.'.csv';
                $handle = fopen($tempPath, 'w');

                if ($handle === false) {
                    continue;
                }

                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $columns, ';');

                $rows = 0;
                DB::table($table)->orderBy($columns[0])->chunk(500, function ($chunk) use ($handle, $columns, &$rows) {
                    foreach ($chunk as $row) {
                        $values = [];

                        foreach ($columns as $column) {
                            $values[] = $row->{$column};
                        }

                        fputcsv($handle, $values, ';');
                        $rows++;
                    }
                });

                fclose($handle);
                $entries[$table.'.csv'] = ['source' => $tempPath];
                $totalRows += $rows;
            }

            $entries['manifest.json'] = json_encode([
                'type' => ArchiveIndex::CATEGORY_BACKUP,
                'generated_at' => now()->toDateTimeString(),
                'tables' => array_values(array_diff($tables, [])),
                'record_count' => $totalRows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $fileName = 'backup-'.now()->format('Ymd-His').'.zip';
            $path = $directory.'/'.$fileName;
            $size = ArchiveBuilder::zip($diskName, $path, $entries);

            $index = ArchiveIndex::create([
                'category' => ArchiveIndex::CATEGORY_BACKUP,
                'file_name' => $fileName,
                'file_path' => $path,
                'size_bytes' => $size,
                'record_count' => $totalRows,
                'status' => ArchiveIndex::STATUS_READY,
                'meta' => ['tables' => $tables],
            ]);

            $this->pruneBackupStorage();

            return $index;
        } finally {
            File::deleteDirectory($tempDirectory);
        }
    }

    private function enforceLimit(string $category, int $limit, callable $counter, callable $archiver): int
    {
        $archived = 0;

        for ($i = 0; $i < 20 && $counter() > $limit; $i++) {
            $index = $archiver();

            if (! $index) {
                break;
            }

            $archived += $index->record_count;
        }

        return $archived;
    }

    private function archiveTransactions(?int $limit = null): ?ArchiveIndex
    {
        $limit = max($limit ?? (int) config('retention.batch.transactions', 500), 1);

        $transactions = Transaction::query()
            ->with([
                'customer',
                'payments.receiver',
                'stayExtensions.extendedBy',
                'roomTransfers.fromRoom',
                'roomTransfers.toRoom',
                'roomTransfers.transferredBy',
                'room.roomType',
                'user',
            ])
            ->whereIn('status', [
                Transaction::STATUS_CHECKED_OUT,
                Transaction::STATUS_CANCELLED,
                Transaction::STATUS_NO_SHOW,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($transactions->isEmpty()) {
            return null;
        }

        $transactionRows = $transactions->map(fn (Transaction $trx) => [
            $trx->code,
            $trx->customer?->name,
            $trx->customer?->phone,
            $trx->customer?->id_card_number,
            $trx->room?->room_number,
            $trx->room?->roomType?->name,
            $trx->user?->name,
            optional($trx->check_in_date)->toDateString(),
            optional($trx->check_out_date)->toDateString(),
            $trx->total_days,
            $trx->room_price_per_night,
            $trx->total_price,
            $trx->discount_type,
            $trx->discount_amount,
            $trx->final_price,
            $trx->down_payment,
            $trx->remaining_payment,
            $trx->status,
            optional($trx->created_at)->toDateTimeString(),
        ]);

        $paymentRows = $transactions->flatMap(fn (Transaction $trx) => $trx->payments->map(fn ($payment) => [
            $trx->code,
            $payment->amount,
            $payment->payment_method,
            $payment->type,
            $payment->notes,
            $payment->receiver?->name,
            optional($payment->paid_at)->toDateTimeString(),
        ]));

        $extensionRows = $transactions->flatMap(fn (Transaction $trx) => $trx->stayExtensions->map(fn ($extension) => [
            $trx->code,
            optional($extension->old_checkout_date)->toDateString(),
            optional($extension->new_checkout_date)->toDateString(),
            $extension->additional_days,
            $extension->additional_price,
            $extension->extendedBy?->name,
            optional($extension->created_at)->toDateTimeString(),
        ]));

        $transferRows = $transactions->flatMap(fn (Transaction $trx) => $trx->roomTransfers->map(fn ($transfer) => [
            $trx->code,
            $transfer->fromRoom?->room_number,
            $transfer->toRoom?->room_number,
            $transfer->reason,
            $transfer->transferredBy?->name,
            optional($transfer->transferred_at)->toDateTimeString(),
        ]));

        $entries = [
            'transactions.csv' => ArchiveBuilder::csv([
                'kode', 'nama_tamu', 'telepon', 'no_ktp', 'kamar', 'jenis_kamar', 'petugas',
                'check_in', 'check_out', 'total_malam', 'harga_per_malam', 'total_harga',
                'tipe_diskon', 'nilai_diskon', 'final_price', 'dp', 'sisa', 'status', 'dibuat',
            ], $transactionRows),
            'payments.csv' => ArchiveBuilder::csv([
                'kode_transaksi', 'jumlah', 'metode', 'tipe', 'catatan', 'diterima_oleh', 'dibayar_pada',
            ], $paymentRows),
            'stay_extensions.csv' => ArchiveBuilder::csv([
                'kode_transaksi', 'checkout_lama', 'checkout_baru', 'tambahan_malam', 'tambahan_harga', 'diperpanjang_oleh', 'dibuat',
            ], $extensionRows),
            'room_transfers.csv' => ArchiveBuilder::csv([
                'kode_transaksi', 'dari_kamar', 'ke_kamar', 'alasan', 'dipindahkan_oleh', 'waktu',
            ], $transferRows),
            'manifest.json' => json_encode([
                'type' => ArchiveIndex::CATEGORY_TRANSACTIONS,
                'generated_at' => now()->toDateTimeString(),
                'transactions' => $transactions->count(),
                'payments' => $paymentRows->count(),
                'extensions' => $extensionRows->count(),
                'transfers' => $transferRows->count(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        $periodStart = $transactions->min(fn (Transaction $trx) => optional($trx->check_in_date)->toDateString());
        $periodEnd = $transactions->max(fn (Transaction $trx) => optional($trx->check_out_date)->toDateString());

        $index = $this->writeArchive(
            ArchiveIndex::CATEGORY_TRANSACTIONS,
            'transactions',
            $entries,
            $transactions->count(),
            $transactions->first()->id,
            $transactions->last()->id,
            $periodStart,
            $periodEnd
        );

        DB::transaction(function () use ($transactions) {
            $this->recordRevenue($transactions);

            foreach ($transactions as $transaction) {
                $transaction->forceDelete();
            }
        });

        return $index;
    }

    private function archiveRoomLogs(?int $limit = null): ?ArchiveIndex
    {
        $limit = max($limit ?? (int) config('retention.batch.room_logs', 500), 1);

        $logs = RoomLog::query()
            ->with(['room:id,room_number', 'user:id,name'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $disk = Storage::disk('private');
        $photoEntries = [];
        $rows = [];
        $photoPaths = [];

        foreach ($logs as $log) {
            $photoName = '';

            if ($log->proof_photo && $disk->exists($log->proof_photo)) {
                $photoName = 'proofs/'.$log->id.'-'.basename($log->proof_photo);
                $photoEntries[$photoName] = ['source' => $disk->path($log->proof_photo)];
                $photoPaths[] = $log->proof_photo;
            }

            $rows[] = [
                $log->id,
                $log->room?->room_number,
                $log->user?->name,
                $log->status_reported,
                $log->notes,
                $photoName,
                optional($log->created_at)->toDateTimeString(),
            ];
        }

        $entries = [
            'room_logs.csv' => ArchiveBuilder::csv([
                'id', 'kamar', 'petugas', 'status', 'catatan', 'foto_bukti', 'dibuat',
            ], $rows),
            'manifest.json' => json_encode([
                'type' => ArchiveIndex::CATEGORY_ROOM_LOGS,
                'generated_at' => now()->toDateTimeString(),
                'records' => $logs->count(),
                'photos' => count($photoPaths),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        $index = $this->writeArchive(
            ArchiveIndex::CATEGORY_ROOM_LOGS,
            'room-logs',
            array_merge($entries, $photoEntries),
            $logs->count(),
            $logs->first()->id,
            $logs->last()->id,
            optional($logs->first()->created_at)->toDateString(),
            optional($logs->last()->created_at)->toDateString()
        );

        DB::transaction(function () use ($logs) {
            RoomLog::whereKey($logs->pluck('id')->all())->delete();
        });

        if ($photoPaths) {
            $disk->delete($photoPaths);
        }

        return $index;
    }

    private function archiveActivityLogs(?int $limit = null): ?ArchiveIndex
    {
        $limit = max($limit ?? (int) config('retention.batch.activity_logs', 500), 1);

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $rows = $logs->map(fn (ActivityLog $log) => [
            $log->id,
            $log->user?->name,
            $log->action,
            $log->category,
            $log->description,
            optional($log->created_at)->toDateTimeString(),
        ]);

        $entries = [
            'activity_logs.csv' => ArchiveBuilder::csv([
                'id', 'user', 'aksi', 'kategori', 'deskripsi', 'dibuat',
            ], $rows),
            'manifest.json' => json_encode([
                'type' => ArchiveIndex::CATEGORY_ACTIVITY_LOGS,
                'generated_at' => now()->toDateTimeString(),
                'records' => $logs->count(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        $index = $this->writeArchive(
            ArchiveIndex::CATEGORY_ACTIVITY_LOGS,
            'activity-logs',
            $entries,
            $logs->count(),
            $logs->first()->id,
            $logs->last()->id,
            optional($logs->first()->created_at)->toDateString(),
            optional($logs->last()->created_at)->toDateString()
        );

        ActivityLog::whereKey($logs->pluck('id')->all())->delete();

        return $index;
    }

    private function archiveCustomers(?int $limit = null): ?ArchiveIndex
    {
        $limit = max($limit ?? (int) config('retention.batch.customers', 300), 1);

        $customers = Customer::withTrashed()
            ->whereDoesntHave('transactions', fn ($query) => $query->withTrashed())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($customers->isEmpty()) {
            return null;
        }

        $rows = $customers->map(fn (Customer $customer) => [
            $customer->id,
            $customer->name,
            $customer->phone,
            $customer->id_card_number,
            $customer->rating_status,
            $customer->visit_count,
            $customer->notes,
            $customer->id_card_photo ? 'ktp/'.basename($customer->id_card_photo) : '',
            optional($customer->created_at)->toDateTimeString(),
            $customer->deleted_at?->toDateTimeString(),
        ]);

        $entries = [
            'customers.csv' => ArchiveBuilder::csv([
                'id', 'name', 'phone', 'no_ktp', 'rating', 'visit_count', 'notes', 'foto_ktp', 'dibuat', 'dihapus',
            ], $rows),
            'manifest.json' => json_encode([
                'type' => ArchiveIndex::CATEGORY_CUSTOMERS,
                'generated_at' => now()->toDateTimeString(),
                'records' => $customers->count(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        $photoEntries = $this->photoEntries($customers->pluck('id_card_photo')->all(), 'ktp');
        $photoPaths = $customers->pluck('id_card_photo')->filter()->all();

        $index = $this->writeArchive(
            ArchiveIndex::CATEGORY_CUSTOMERS,
            'customers',
            array_merge($entries, $photoEntries),
            $customers->count(),
            $customers->first()->id,
            $customers->last()->id,
            null,
            null
        );

        DB::transaction(function () use ($customers) {
            foreach ($customers as $customer) {
                $customer->forceDelete();
            }
        });

        if ($photoPaths) {
            Storage::disk('private')->delete($photoPaths);
        }

        return $index;
    }

    private function recordRevenue(EloquentCollection $transactions): void
    {
        $revenueByMonth = [];
        $checkOutsByMonth = [];
        $roomNightsByMonth = [];

        foreach ($transactions as $transaction) {
            foreach ($transaction->payments as $payment) {
                if (! in_array($payment->type, ['dp', 'pelunasan'], true)) {
                    continue;
                }

                $month = optional($payment->paid_at)->format('Y-m') ?: now()->format('Y-m');
                $revenueByMonth[$month] = ($revenueByMonth[$month] ?? 0) + (float) $payment->amount;
            }

            if ($transaction->status === Transaction::STATUS_CHECKED_OUT) {
                $month = optional($transaction->check_out_date)->format('Y-m') ?: now()->format('Y-m');
                $checkOutsByMonth[$month] = ($checkOutsByMonth[$month] ?? 0) + 1;
                $roomNightsByMonth[$month] = ($roomNightsByMonth[$month] ?? 0) + (int) $transaction->total_days;
            }
        }

        foreach ($revenueByMonth as $month => $amount) {
            MonthlyRevenue::firstOrCreate(['month' => $month])->increment('revenue', $amount);
        }

        foreach ($checkOutsByMonth as $month => $count) {
            MonthlyRevenue::firstOrCreate(['month' => $month])->increment('check_outs', $count);
        }

        foreach ($roomNightsByMonth as $month => $nights) {
            MonthlyRevenue::firstOrCreate(['month' => $month])->increment('room_nights', $nights);
        }
    }

    /**
     * @param  array<string, string|array{source: string}>  $entries
     */
    private function writeArchive(
        string $category,
        string $prefix,
        array $entries,
        int $recordCount,
        ?int $firstId,
        ?int $lastId,
        ?string $periodStart,
        ?string $periodEnd
    ): ArchiveIndex {
        $diskName = (string) config('retention.archive.disk', 'private');
        $directory = trim((string) config('retention.archive.path', 'archives'), '/');
        $fileName = $prefix.'-'.now()->format('Ymd-His').'-'.($firstId ?? 0).'-'.($lastId ?? 0).'.zip';
        $path = $directory.'/'.$fileName;

        $size = ArchiveBuilder::zip($diskName, $path, $entries);

        return ArchiveIndex::create([
            'category' => $category,
            'file_name' => $fileName,
            'file_path' => $path,
            'size_bytes' => $size,
            'record_count' => $recordCount,
            'first_record_id' => $firstId,
            'last_record_id' => $lastId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => ArchiveIndex::STATUS_READY,
        ]);
    }

    /**
     * @param  array<int, string|null>  $paths
     * @return array<string, array{source: string}>
     */
    private function photoEntries(array $paths, string $folder): array
    {
        $disk = Storage::disk('private');
        $entries = [];

        foreach (array_filter($paths) as $path) {
            if ($disk->exists($path)) {
                $entries[$folder.'/'.basename($path)] = ['source' => $disk->path($path)];
            }
        }

        return $entries;
    }

    private function pruneExpiredSessions(): int
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable('sessions')) {
            return 0;
        }

        $cutoff = now()->subMinutes((int) config('session.lifetime', 720))->timestamp;

        return (int) DB::table('sessions')->where('last_activity', '<', $cutoff)->delete();
    }

    private function pruneExpiredCache(): int
    {
        if (config('cache.default') !== 'database') {
            return 0;
        }

        $table = (string) config('cache.stores.database.table', 'cache');

        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->where('expiration', '<', now()->timestamp)->delete();
    }

    private function pruneApplicationLogs(): int
    {
        $days = max((int) config('retention.log_days', 14), 1);
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach (File::glob(storage_path('logs/*.log')) ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (File::delete($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function pruneMedia(): void
    {
        foreach (['id-cards', 'room-proofs'] as $folder) {
            PhotoRetention::prune(
                $folder,
                (int) config('retention.media.'.$folder.'.max_files', 5000),
                (int) config('retention.media.'.$folder.'.max_bytes', 300 * 1024 * 1024)
            );
        }
    }

    private function pruneArchiveStorage(): int
    {
        return $this->pruneCategoryFiles(
            [
                ArchiveIndex::CATEGORY_TRANSACTIONS,
                ArchiveIndex::CATEGORY_ROOM_LOGS,
                ArchiveIndex::CATEGORY_ACTIVITY_LOGS,
                ArchiveIndex::CATEGORY_CUSTOMERS,
                ArchiveIndex::CATEGORY_CUSTOMER_EXPORT,
            ],
            (int) config('retention.archive.budget_mb', 250) * 1024 * 1024
        );
    }

    private function pruneBackupStorage(): int
    {
        return $this->pruneCategoryFiles(
            [ArchiveIndex::CATEGORY_BACKUP],
            (int) config('retention.backup.budget_mb', 100) * 1024 * 1024
        );
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function pruneCategoryFiles(array $categories, int $budgetBytes): int
    {
        $disk = Storage::disk('private');
        $items = ArchiveIndex::query()
            ->whereIn('category', $categories)
            ->where('status', ArchiveIndex::STATUS_READY)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $total = 0;
        $sizes = [];

        foreach ($items as $item) {
            $size = $disk->exists($item->file_path) ? (int) $disk->size($item->file_path) : 0;
            $sizes[$item->id] = $size;
            $total += $size;
        }

        $deleted = 0;

        foreach ($items as $item) {
            if ($total <= $budgetBytes) {
                break;
            }

            $size = $sizes[$item->id] ?? 0;

            if ($disk->exists($item->file_path)) {
                $disk->delete($item->file_path);
            }

            $item->update(['status' => ArchiveIndex::STATUS_DELETED]);
            $total -= $size;
            $deleted++;
        }

        return $deleted;
    }

    private function pruneArchiveIndex(): void
    {
        $limit = max((int) config('retention.limits.archive_index', 5000), 1);
        $count = ArchiveIndex::count();

        if ($count <= $limit) {
            return;
        }

        $items = ArchiveIndex::query()
            ->orderByRaw("CASE WHEN status = 'deleted' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->limit($count - $limit)
            ->get();

        $disk = Storage::disk('private');

        foreach ($items as $item) {
            if ($item->status === ArchiveIndex::STATUS_READY && $disk->exists($item->file_path)) {
                $disk->delete($item->file_path);
            }

            $item->delete();
        }
    }
}
