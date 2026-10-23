<?php

namespace App\Console\Commands;

use App\Support\RetentionService;
use Illuminate\Console\Command;

class RunRetentionCommand extends Command
{
    protected $signature = 'retention:run';

    protected $description = 'Jalankan retensi otomatis dan pembersihan kuota';

    public function handle(RetentionService $retention): int
    {
        $summary = $retention->enforce();

        if (! empty($summary['error'])) {
            $this->error('Retensi gagal: '.$summary['error']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Selesai. Transaksi=%d, room_log=%d, activity_log=%d, customer=%d, DB=%s%%.',
            $summary['transactions'] ?? 0,
            $summary['room_logs'] ?? 0,
            $summary['activity_logs'] ?? 0,
            $summary['customers'] ?? 0,
            number_format((float) ($summary['db_percent'] ?? 0), 2, '.', '')
        ));

        if (($summary['archives'] ?? 0) > 0 || ($summary['backups'] ?? 0) > 0) {
            $this->line(sprintf(
                'File lama dihapus: arsip=%d, backup=%d.',
                $summary['archives'] ?? 0,
                $summary['backups'] ?? 0
            ));
        }

        return self::SUCCESS;
    }
}
