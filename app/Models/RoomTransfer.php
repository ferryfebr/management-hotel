<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $from_room_id
 * @property int $to_room_id
 * @property string $reason
 * @property int|null $transferred_by
 * @property \Illuminate\Support\Carbon $transferred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Room $fromRoom
 * @property-read \App\Models\Room $toRoom
 * @property-read \App\Models\Transaction|null $transaction
 * @property-read \App\Models\User|null $transferredBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereFromRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereToRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereTransferredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereTransferredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomTransfer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RoomTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'from_room_id',
        'to_room_id',
        'reason',
        'transferred_by',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}