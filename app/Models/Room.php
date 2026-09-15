<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $room_type_id
 * @property string $room_number
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomLog> $roomLogs
 * @property-read int|null $room_logs_count
 * @property-read \App\Models\RoomType $roomType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomTransfer> $transfersFrom
 * @property-read int|null $transfers_from_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomTransfer> $transfersTo
 * @property-read int|null $transfers_to_count
 * @method static Builder<static>|Room available()
 * @method static Builder<static>|Room availableBetween(string $checkIn, string $checkOut)
 * @method static Builder<static>|Room newModelQuery()
 * @method static Builder<static>|Room newQuery()
 * @method static Builder<static>|Room query()
 * @method static Builder<static>|Room whereCreatedAt($value)
 * @method static Builder<static>|Room whereId($value)
 * @method static Builder<static>|Room whereRoomNumber($value)
 * @method static Builder<static>|Room whereRoomTypeId($value)
 * @method static Builder<static>|Room whereStatus($value)
 * @method static Builder<static>|Room whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Room extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_DIRTY = 'dirty';
    public const STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'room_type_id',
        'room_number',
        'status',
    ];

    // ==== Relasi ====

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function roomLogs(): HasMany
    {
        return $this->hasMany(RoomLog::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(RoomTransfer::class, 'from_room_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(RoomTransfer::class, 'to_room_id');
    }

    // ==== Scope ====

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * Kamar yang benar-benar bebas untuk rentang tanggal tertentu
     * (tidak bentrok dengan transaksi reserved/checked_in yang overlap).
     */
    public function scopeAvailableBetween(Builder $query, string $checkIn, string $checkOut): Builder
    {
        return $query->whereDoesntHave('transactions', function (Builder $q) use ($checkIn, $checkOut) {
            $q->whereIn('status', ['reserved', 'checked_in'])
                ->where('check_in_date', '<', $checkOut)
                ->where('check_out_date', '>', $checkIn);
        });
    }

    /**
     * Label Bahasa Indonesia untuk tampilan UI.
     * Nilai enum statis (immutable) demi konsistensi data — hanya label display yg diterjemahkan.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_AVAILABLE => 'Tersedia',
            self::STATUS_OCCUPIED => 'Terisi',
            self::STATUS_DIRTY => 'Kotor',
            self::STATUS_MAINTENANCE => 'Perbaikan',
            default => ucfirst($status),
        };
    }
}