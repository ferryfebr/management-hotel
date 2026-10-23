<?php

return [
    // Budget database aktif. 75% dari angka ini adalah hard guard.
    'db_budget_mb' => (int) env('RETENTION_DB_BUDGET_MB', 400),

    'thresholds' => [
        'warn' => 0.60,
        'archive' => 0.65,
        'force' => 0.70,
        'lock' => 0.75,
    ],

    // Batas baris aktif. Payment/extend/transfer mengikuti transaksi.
    'limits' => [
        'customers' => (int) env('RETENTION_CUSTOMERS_MAX', 100000),
        'transactions' => (int) env('RETENTION_TRANSACTIONS_MAX', 50000),
        'room_logs' => (int) env('RETENTION_ROOM_LOGS_MAX', 100000),
        'activity_logs' => (int) env('RETENTION_ACTIVITY_LOGS_MAX', 50000),
        'archive_index' => (int) env('RETENTION_ARCHIVE_INDEX_MAX', 5000),
    ],

    // Jumlah baris per batch archive supaya request tidak timeout.
    'batch' => [
        'transactions' => 500,
        'room_logs' => 500,
        'activity_logs' => 500,
        'customers' => 300,
    ],

    'archive' => [
        'disk' => 'private',
        'path' => 'archives',
        'budget_mb' => (int) env('RETENTION_ARCHIVE_BUDGET_MB', 250),
        'temp_path' => 'tmp-archives',
    ],

    'backup' => [
        'disk' => 'private',
        'path' => 'backups',
        'budget_mb' => (int) env('RETENTION_BACKUP_BUDGET_MB', 100),
    ],

    // Batas file foto. Byte dan jumlah file sama-sama dicek.
    'media' => [
        'id-cards' => [
            'max_files' => (int) env('RETENTION_KTP_MAX_FILES', 6000),
            'max_bytes' => (int) env('RETENTION_KTP_MAX_MB', 600) * 1024 * 1024,
        ],
        'room-proofs' => [
            'max_files' => (int) env('RETENTION_PROOF_MAX_FILES', 5000),
            'max_bytes' => (int) env('RETENTION_PROOF_MAX_MB', 300) * 1024 * 1024,
        ],
    ],

    'customer_export' => [
        'batch' => (int) env('RETENTION_CUSTOMER_EXPORT_BATCH', 300),
        'reminder' => (int) env('RETENTION_CUSTOMER_EXPORT_REMINDER', 300),
    ],

    'log_days' => (int) env('RETENTION_APP_LOG_DAYS', 14),
];
