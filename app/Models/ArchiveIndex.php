<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveIndex extends Model
{
    protected $table = 'archive_index';

    public const CATEGORY_TRANSACTIONS = 'transactions';

    public const CATEGORY_ROOM_LOGS = 'room_logs';

    public const CATEGORY_ACTIVITY_LOGS = 'activity_logs';

    public const CATEGORY_CUSTOMERS = 'customers';

    public const CATEGORY_CUSTOMER_EXPORT = 'customer_export';

    public const CATEGORY_BACKUP = 'backup';

    public const STATUS_READY = 'ready';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'category',
        'file_name',
        'file_path',
        'size_bytes',
        'record_count',
        'first_record_id',
        'last_record_id',
        'period_start',
        'period_end',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'record_count' => 'integer',
            'first_record_id' => 'integer',
            'last_record_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'meta' => 'array',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
