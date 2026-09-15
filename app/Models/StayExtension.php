<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property \Illuminate\Support\Carbon $old_checkout_date
 * @property \Illuminate\Support\Carbon $new_checkout_date
 * @property int $additional_days
 * @property numeric $additional_price
 * @property int $extended_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $extendedBy
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereAdditionalDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereAdditionalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereExtendedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereNewCheckoutDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereOldCheckoutDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StayExtension whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class StayExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'old_checkout_date',
        'new_checkout_date',
        'additional_days',
        'additional_price',
        'extended_by',
    ];

    protected function casts(): array
    {
        return [
            'old_checkout_date' => 'date',
            'new_checkout_date' => 'date',
            'additional_price' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function extendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extended_by');
    }
}