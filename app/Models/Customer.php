<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $id_card_number
 * @property string|null $id_card_photo
 * @property int $visit_count
 * @property string $rating_status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string|null $id_card_photo_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIdCardNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIdCardPhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereRatingStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereVisitCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withoutTrashed()
 * @mixin \Eloquent
 */
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public const RATING_REGULAR = 'regular';
    public const RATING_WARNING = 'warning';
    public const RATING_VIP = 'vip';
    public const RATING_BLACKLISTED = 'blacklisted';

    protected $fillable = [
        'name',
        'phone',
        'id_card_number',
        'id_card_photo',
        'visit_count',
        'rating_status',
        'notes',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isBlacklisted(): bool
    {
        return $this->rating_status === self::RATING_BLACKLISTED;
    }

    /**
     * URL foto KTP lewat route yang dilindungi otorisasi,
     * BUKAN URL publik langsung ke storage (data KTP = PII sensitif).
     */
    public function getIdCardPhotoUrlAttribute(): ?string
    {
        return $this->id_card_photo
            ? route('customers.id-card', $this->id)
            : null;
    }
}