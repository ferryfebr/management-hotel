<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property int $customer_id
 * @property int $room_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $check_in_date
 * @property \Illuminate\Support\Carbon $check_out_date
 * @property int $total_days
 * @property numeric $room_price_per_night
 * @property numeric $total_price
 * @property string|null $discount_type
 * @property numeric $discount_amount
 * @property numeric $final_price
 * @property numeric $down_payment
 * @property numeric $remaining_payment
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Room $room
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomTransfer> $roomTransfers
 * @property-read int|null $room_transfers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StayExtension> $stayExtensions
 * @property-read int|null $stay_extensions_count
 * @property-read \App\Models\User $user
 * @method static Builder<static>|Transaction active()
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction onlyTrashed()
 * @method static Builder<static>|Transaction query()
 * @method static Builder<static>|Transaction upcomingReservations()
 * @method static Builder<static>|Transaction whereCheckInDate($value)
 * @method static Builder<static>|Transaction whereCheckOutDate($value)
 * @method static Builder<static>|Transaction whereCode($value)
 * @method static Builder<static>|Transaction whereCreatedAt($value)
 * @method static Builder<static>|Transaction whereCustomerId($value)
 * @method static Builder<static>|Transaction whereDeletedAt($value)
 * @method static Builder<static>|Transaction whereDiscountAmount($value)
 * @method static Builder<static>|Transaction whereDiscountType($value)
 * @method static Builder<static>|Transaction whereDownPayment($value)
 * @method static Builder<static>|Transaction whereFinalPrice($value)
 * @method static Builder<static>|Transaction whereId($value)
 * @method static Builder<static>|Transaction whereRemainingPayment($value)
 * @method static Builder<static>|Transaction whereRoomId($value)
 * @method static Builder<static>|Transaction whereRoomPricePerNight($value)
 * @method static Builder<static>|Transaction whereStatus($value)
 * @method static Builder<static>|Transaction whereTotalDays($value)
 * @method static Builder<static>|Transaction whereTotalPrice($value)
 * @method static Builder<static>|Transaction whereUpdatedAt($value)
 * @method static Builder<static>|Transaction whereUserId($value)
 * @method static Builder<static>|Transaction withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Transaction withoutTrashed()
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    /** Jam check-out terjadwal (12:00 siang) pada tanggal check_out_date. */
    public const CHECKOUT_HOUR = 12;

    protected $fillable = [
        'code',
        'customer_id',
        'room_id',
        'user_id',
        'check_in_date',
        'check_out_date',
        'total_days',
        'room_price_per_night',
        'total_price',
        'discount_type',
        'discount_amount',
        'final_price',
        'down_payment',
        'remaining_payment',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'room_price_per_night' => 'decimal:2',
            'total_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'remaining_payment' => 'decimal:2',
        ];
    }

    // ==== Relasi ====

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function roomTransfers(): HasMany
    {
        return $this->hasMany(RoomTransfer::class);
    }

    public function stayExtensions(): HasMany
    {
        return $this->hasMany(StayExtension::class);
    }

    // ==== Scope ====

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeUpcomingReservations(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESERVED)
            ->orderBy('check_in_date');
    }

    // ==== Helper ====

    public function totalPaid(): float
    {
        return (float) $this->payments()
            ->whereIn('type', ['dp', 'pelunasan'])
            ->sum('amount');
    }

    public function totalCharge(): float
    {
        return (float) $this->payments()
            ->where('type', Payment::TYPE_CHARGE)
            ->sum('amount');
    }

    public function totalBill(): float
    {
        return (float) $this->final_price + $this->totalCharge();
    }

    public function isFullyPaid(): bool
    {
        return $this->totalPaid() >= $this->totalBill();
    }

    /**
     * Waktu check-out terjadwal: tanggal check_out_date jam 12:00.
     */
    public function scheduledCheckoutAt(): \Illuminate\Support\Carbon
    {
        return $this->check_out_date->copy()->setTime(self::CHECKOUT_HOUR, 0);
    }
}