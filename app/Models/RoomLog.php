<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $room_id
 * @property int $user_id
 * @property string $status_reported
 * @property string|null $notes
 * @property string|null $proof_photo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Room $room
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereStatusReported($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomLog whereUserId($value)
 * @mixin \Eloquent
 */
class RoomLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'status_reported',
        'notes',
        'proof_photo',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}