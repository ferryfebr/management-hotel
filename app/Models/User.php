<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property bool $is_active
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $paymentsReceived
 * @property-read int|null $payments_received_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoomLog> $roomLogs
 * @property-read int|null $room_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StayExtension> $stayExtensions
 * @property-read int|null $stay_extensions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static Builder<static>|User active()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_RESEPSIONIS = 'resepsionis';
    public const ROLE_ROOM_KEEPER = 'room_keeper';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ==== Relasi ====

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function roomLogs(): HasMany
    {
        return $this->hasMany(RoomLog::class);
    }

    public function stayExtensions(): HasMany
    {
        return $this->hasMany(StayExtension::class, 'extended_by');
    }

    // ==== Helper role ====

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isResepsionis(): bool
    {
        return $this->role === self::ROLE_RESEPSIONIS;
    }

    public function isRoomKeeper(): bool
    {
        return $this->role === self::ROLE_ROOM_KEEPER;
    }

    /**
     * Halaman default setelah login, disesuaikan dengan role.
     */
    public function defaultRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_OWNER => 'dashboard.index',
            self::ROLE_RESEPSIONIS => 'transactions.active',
            self::ROLE_ROOM_KEEPER => 'room-keeper.index',
            default => 'dashboard.index',
        };
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::ROLE_OWNER => 'Owner',
            self::ROLE_RESEPSIONIS => 'Resepsionis',
            self::ROLE_ROOM_KEEPER => 'Room Keeper',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    /**
     * Hanya akun yang bisa login (aktif).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}