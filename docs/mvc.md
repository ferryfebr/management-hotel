# MVC — Kode Lengkap Sistem Manajemen Hotel (Laravel)

## Cara pasang ke project Laravel yang sudah ada

1. Copy folder `app/`, `database/`, `resources/`, `routes/web.php` ke root project Laravel-mu (timpa `routes/web.php` yang lama).
2. Semua file migration sudah termasuk lengkap di bagian "Migrations" pada dokumen ini — copy ke `database/migrations/`.
3. Daftarkan middleware `role` — lihat `app/Http/Middleware/README_REGISTER_MIDDLEWARE.md`.
4. Tambahkan disk `private` di `config/filesystems.php` — lihat `config/README_FILESYSTEM_PRIVATE_DISK.md`.
5. Jalankan:
   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link
   php artisan serve
   ```
6. Login dengan salah satu akun contoh dari seeder (password semua: `password`):
   - `owner@hotel.test` → Dashboard, Laporan, Jenis Kamar
   - `resepsionis@hotel.test` → Kamar, Reservasi, Check-in, Tamu Aktif, Pelanggan
   - `roomkeeper@hotel.test` → Status Kamar (mobile-friendly)

## Struktur

- **Models**: `RoomType`, `Room`, `Customer`, `Transaction`, `Payment`, `RoomTransfer`, `StayExtension`, `RoomLog`, `User` (+role).
- **Middleware**: `EnsureUserHasRole` — proteksi route berbasis role lewat `middleware('role:owner')`, bukan cuma redirect setelah login.
- **Controllers**: sesuai rancangan awal + `RoomTypeController` terpisah dari `RoomController` untuk pemisahan tanggung jawab yang lebih jelas.
- **Views**: Tailwind (CDN) — layout dengan sidebar adaptif per role.

## Yang sudah diperbaiki dari rancangan awal

- Tabel & alur `payments` (bisa dicicil, bukan cuma 1 kolom DP + 1 kolom sisa).
- Tabel & alur `stay_extensions` (histori setiap kali tamu extend).
- Status transaksi `no_show` untuk reservasi yang tidak datang.
- Foto KTP disimpan di disk **private**, diakses lewat route ber-middleware `auth`, bukan URL publik.
- Validasi bentrok reservasi (`Room::availableBetween()`) sebelum kamar dipesan.
- Proteksi akses per halaman pakai middleware role di routes, bukan cuma disembunyikan dari menu sidebar.
- `DB::transaction()` dibungkus di setiap operasi multi-tabel (check-in, extend, transfer, check-out) supaya atomik.

## Yang belum dibuat (di luar cakupan MVP ini, bisa lanjutan)

- Form edit untuk `RoomType` dan `Room` (route `update` sudah ada, tinggal tambah tombol edit di view).
- Observer untuk auto-update `visit_count` (saat ini di-increment manual di `TransactionController`).
- Export laporan ke PDF/Excel (`ReportController` baru menampilkan tabel, belum export).
- Form Request classes terpisah (validasi saat ini inline di controller demi keringkasan).

---

# Dokumentasi Kode Lengkap — Sistem Manajemen Hotel (Laravel)

Seluruh kode migration, model, middleware, controller, routes, seeder, dan views digabung dalam satu dokumen ini.

## Daftar Isi

- [Migrations](#migrations)
- [Models](#models)
- [Middleware](#middleware)
- [Controllers](#controllers)
- [Routes](#routes)
- [Seeder](#seeder)
- [Views](#views)


## Migrations

### `database/migrations/2024_01_01_000001_add_role_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom role pada tabel users bawaan Laravel
     * untuk kebutuhan multi-role (owner, resepsionis, room_keeper).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['owner', 'resepsionis', 'room_keeper'])
                ->default('resepsionis')
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

### `database/migrations/2024_01_01_000002_create_room_types_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Standard, Deluxe, VIP, dst
            $table->decimal('price', 12, 2); // Harga dasar per malam
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
```

### `database/migrations/2024_01_01_000003_create_rooms_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')
                ->constrained('room_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // Cegah hapus room_type jika masih dipakai kamar

            $table->string('room_number')->unique(); // Nomor/nama kamar, misal: 101, 102
            $table->enum('status', ['available', 'occupied', 'dirty', 'maintenance'])
                ->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
```

### `database/migrations/2024_01_01_000004_create_customers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('id_card_number')->nullable()->unique(); // Nomor KTP, untuk deteksi tamu lama
            $table->string('id_card_photo')->nullable(); // Path file foto KTP (simpan di disk private)
            $table->unsignedInteger('visit_count')->default(0); // Total kunjungan menginap
            $table->enum('rating_status', ['regular', 'vip', 'blacklisted'])->default('regular');
            $table->text('notes')->nullable(); // Catatan khusus perilaku tamu
            $table->timestamps();
            $table->softDeletes(); // Data tamu tidak dihapus permanen demi histori/audit
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

### `database/migrations/2024_01_01_000005_create_transactions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Kode transaksi/booking unik

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete(); // Resepsionis yang melayani

            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('total_days');

            $table->decimal('room_price_per_night', 12, 2); // Snapshot harga saat transaksi dibuat
            $table->decimal('total_price', 12, 2); // Total biaya kamar kotor

            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2); // Total bersih setelah diskon

            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('remaining_payment', 12, 2)->default(0);

            $table->enum('status', ['reserved', 'checked_in', 'checked_out', 'cancelled', 'no_show'])
                ->default('reserved');

            $table->timestamps();
            $table->softDeletes(); // Jangan hapus permanen data transaksi keuangan

            // Index untuk mempercepat query cek bentrok reservasi & laporan
            $table->index(['room_id', 'check_in_date', 'check_out_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

### `database/migrations/2024_01_01_000006_create_payments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel tambahan (di luar skema awal) agar riwayat pembayaran
     * tercatat per transaksi pembayaran, bukan cuma 2 kolom total.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'debit', 'kartu_kredit'])
                ->default('cash');
            $table->enum('type', ['dp', 'pelunasan', 'refund'])->default('dp');

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete(); // Petugas yang menerima pembayaran

            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

### `database/migrations/2024_01_01_000007_create_room_transfers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->foreignId('from_room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->foreignId('to_room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->text('reason'); // Alasan pindah kamar, misal: AC tidak dingin
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_transfers');
    }
};
```

### `database/migrations/2024_01_01_000008_create_stay_extensions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel tambahan agar setiap perpanjangan masa inap (extendStay)
     * tercatat sebagai histori, bukan menimpa langsung check_out_date.
     */
    public function up(): void
    {
        Schema::create('stay_extensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->date('old_checkout_date');
            $table->date('new_checkout_date');
            $table->unsignedInteger('additional_days');
            $table->decimal('additional_price', 12, 2); // Tambahan tagihan akibat extend

            $table->foreignId('extended_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_extensions');
    }
};
```

### `database/migrations/2024_01_01_000009_create_room_logs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete(); // Room keeper yang melapor

            $table->enum('status_reported', ['dirty', 'clean', 'maintenance']);
            $table->text('notes')->nullable(); // Catatan kerusakan fasilitas jika ada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_logs');
    }
};
```


## Models

### `app/Models/Customer.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public const RATING_REGULAR = 'regular';
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
```

### `app/Models/Payment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const TYPE_DP = 'dp';
    public const TYPE_PELUNASAN = 'pelunasan';
    public const TYPE_REFUND = 'refund';

    protected $fillable = [
        'transaction_id',
        'amount',
        'payment_method',
        'type',
        'received_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
```

### `app/Models/Room.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
```

### `app/Models/RoomLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'status_reported',
        'notes',
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
```

### `app/Models/RoomTransfer.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'from_room_id',
        'to_room_id',
        'reason',
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
}
```

### `app/Models/RoomType.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
```

### `app/Models/StayExtension.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
```

### `app/Models/Transaction.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

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

    public function isFullyPaid(): bool
    {
        return $this->totalPaid() >= (float) $this->final_price;
    }
}
```

### `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
}
```


## Middleware

### `app/Http/Middleware/EnsureUserHasRole.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Membatasi akses route hanya untuk role tertentu.
     * Pakai di routes: ->middleware('role:owner') atau ->middleware('role:owner,resepsionis')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
```


## Controllers

### `app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        return redirect()->route($user->defaultRouteName())
            ->with('success', 'Selamat datang kembali, ' . $user->name . '.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

### `app/Http/Controllers/CustomerController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('id_card_number', 'like', "%{$request->search}%");
            })
            ->orderByDesc('visit_count')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'transactions' => fn ($q) => $q->with('room')->latest('check_in_date'),
        ]);

        return view('customers.show', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'rating_status' => ['required', 'in:regular,vip,blacklisted'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update($data);

        return back()->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete(); // soft delete

        return back()->with('success', 'Data pelanggan dihapus (masih tersimpan untuk audit).');
    }

    /**
     * Streaming foto KTP lewat route terproteksi middleware auth,
     * bukan URL publik langsung, karena ini data PII sensitif.
     */
    public function idCardPhoto(Customer $customer): StreamedResponse
    {
        abort_unless($customer->id_card_photo, 404);

        return Storage::disk('private')->response($customer->id_card_photo);
    }
}
```

### `app/Http/Controllers/DashboardController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $roomStats = [
            'total' => Room::count(),
            'available' => Room::where('status', Room::STATUS_AVAILABLE)->count(),
            'occupied' => Room::where('status', Room::STATUS_OCCUPIED)->count(),
            'dirty' => Room::where('status', Room::STATUS_DIRTY)->count(),
            'maintenance' => Room::where('status', Room::STATUS_MAINTENANCE)->count(),
        ];

        $todayCheckIns = Transaction::whereDate('check_in_date', today())
            ->whereIn('status', [Transaction::STATUS_RESERVED, Transaction::STATUS_CHECKED_IN])
            ->count();

        $todayCheckOuts = Transaction::whereDate('check_out_date', today())
            ->where('status', Transaction::STATUS_CHECKED_IN)
            ->count();

        $revenueThisMonth = Payment::whereIn('type', ['dp', 'pelunasan'])
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $recentTransactions = Transaction::with(['customer', 'room'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'roomStats',
            'todayCheckIns',
            'todayCheckOuts',
            'revenueThisMonth',
            'recentTransactions'
        ));
    }
}
```

### `app/Http/Controllers/ReportController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $startDate = $request->date('start_date') ?? now()->startOfMonth();
        $endDate = $request->date('end_date') ?? now()->endOfMonth();

        $payments = Payment::with(['transaction.customer', 'transaction.room'])
            ->whereIn('type', ['dp', 'pelunasan'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->orderBy('paid_at')
            ->get();

        $totalRevenue = $payments->sum('amount');

        $checkOuts = Transaction::where('status', Transaction::STATUS_CHECKED_OUT)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->count();

        $totalRoomNights = Transaction::where('status', Transaction::STATUS_CHECKED_OUT)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->sum('total_days');

        return view('reports.index', compact(
            'payments',
            'totalRevenue',
            'checkOuts',
            'totalRoomNights',
            'startDate',
            'endDate'
        ));
    }
}
```

### `app/Http/Controllers/ReservationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(): View
    {
        $reservations = Transaction::with(['customer', 'room'])
            ->upcomingReservations()
            ->paginate(15);

        return view('reservations.index', compact('reservations'));
    }

    public function create(): View
    {
        $roomTypes = RoomType::with(['rooms' => fn ($q) => $q->available()])->orderBy('name')->get();

        return view('reservations.create', compact('roomTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'room_id' => ['required', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
        ]);

        $room = Room::findOrFail($data['room_id']);

        $isAvailable = Room::query()
            ->whereKey($room->id)
            ->availableBetween($data['check_in_date'], $data['check_out_date'])
            ->exists();

        if (! $isAvailable) {
            return back()
                ->withInput()
                ->withErrors('Kamar sudah dipesan pada rentang tanggal tersebut.');
        }

        DB::transaction(function () use ($data, $room) {
            $customer = Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['name' => $data['customer_name']]
            );

            $checkIn = $data['check_in_date'];
            $checkOut = $data['check_out_date'];
            $totalDays = (int) now()->parse($checkIn)->diffInDays($checkOut);
            $totalPrice = $totalDays * (float) $room->roomType->price;

            Transaction::create([
                'code' => 'RSV-' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'room_id' => $room->id,
                'user_id' => auth()->id(),
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'total_days' => $totalDays,
                'room_price_per_night' => $room->roomType->price,
                'total_price' => $totalPrice,
                'final_price' => $totalPrice,
                'status' => Transaction::STATUS_RESERVED,
            ]);
        });

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reservasi berhasil dibuat.');
    }

    /**
     * Konversi reservasi menjadi transaksi check-in penuh.
     * Data lengkap (KTP, DP, diskon) tetap diisi lewat TransactionController::createCheckIn.
     */
    public function edit(Transaction $reservation): View
    {
        abort_unless($reservation->status === Transaction::STATUS_RESERVED, 404);

        $rooms = Room::query()
            ->where(function ($q) use ($reservation) {
                $q->where('status', Room::STATUS_AVAILABLE)
                    ->orWhere('id', $reservation->room_id);
            })
            ->with('roomType')
            ->orderBy('room_number')
            ->get();

        return view('transactions.checkin', [
            'reservation' => $reservation,
            'rooms' => $rooms,
        ]);
    }

    public function cancel(Request $request, Transaction $reservation): RedirectResponse
    {
        abort_unless($reservation->status === Transaction::STATUS_RESERVED, 404);

        $reservation->update(['status' => Transaction::STATUS_CANCELLED]);

        return back()->with('success', 'Reservasi dibatalkan.');
    }

    public function markNoShow(Transaction $reservation): RedirectResponse
    {
        abort_unless($reservation->status === Transaction::STATUS_RESERVED, 404);

        $reservation->update(['status' => Transaction::STATUS_NO_SHOW]);

        return back()->with('success', 'Reservasi ditandai sebagai tidak datang (no-show).');
    }
}
```

### `app/Http/Controllers/RoomController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(): View
    {
        $rooms = Room::with('roomType')->orderBy('room_number')->get();
        $roomTypes = RoomType::orderBy('name')->get();

        return view('rooms.index', compact('rooms', 'roomTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20', 'unique:rooms,room_number'],
        ]);

        $data['status'] = Room::STATUS_AVAILABLE;

        Room::create($data);

        return back()->with('success', 'Kamar berhasil ditambahkan.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20', 'unique:rooms,room_number,' . $room->id],
        ]);

        $room->update($data);

        return back()->with('success', 'Data kamar berhasil diperbarui.');
    }

    /**
     * Override status kamar secara manual oleh Owner/Resepsionis.
     * Perubahan status rutin (dirty/clean) idealnya lewat RoomKeeperController.
     */
    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,occupied,dirty,maintenance'],
        ]);

        $room->update($data);

        return back()->with('success', 'Status kamar berhasil diperbarui.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->transactions()->exists()) {
            return back()->withErrors('Kamar tidak bisa dihapus karena memiliki histori transaksi.');
        }

        $room->delete();

        return back()->with('success', 'Kamar berhasil dihapus.');
    }
}
```

### `app/Http/Controllers/RoomKeeperController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoomKeeperController extends Controller
{
    public function index(): View
    {
        $rooms = Room::with('roomType')
            ->orderByRaw("FIELD(status, 'dirty', 'maintenance', 'occupied', 'available')")
            ->orderBy('room_number')
            ->get();

        return view('room-keeper.index', compact('rooms'));
    }

    /**
     * Update status kebersihan (dirty -> clean/available) dan catat log.
     */
    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'status_reported' => ['required', 'in:dirty,clean,maintenance'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $room) {
            RoomLog::create([
                'room_id' => $room->id,
                'user_id' => auth()->id(),
                'status_reported' => $data['status_reported'],
                'notes' => $data['notes'] ?? null,
            ]);

            // "clean" pada log berarti kamar siap dipakai lagi -> available
            $newRoomStatus = $data['status_reported'] === 'clean'
                ? Room::STATUS_AVAILABLE
                : $data['status_reported'];

            $room->update(['status' => $newRoomStatus]);
        });

        return back()->with('success', 'Status kamar berhasil diperbarui.');
    }
}
```

### `app/Http/Controllers/RoomTypeController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::withCount('rooms')->orderBy('name')->get();

        return view('room-types.index', compact('roomTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        RoomType::create($data);

        return back()->with('success', 'Jenis kamar berhasil ditambahkan.');
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $roomType->update($data);

        return back()->with('success', 'Jenis kamar berhasil diperbarui.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        if ($roomType->rooms()->exists()) {
            return back()->withErrors('Jenis kamar tidak bisa dihapus karena masih memiliki kamar.');
        }

        $roomType->delete();

        return back()->with('success', 'Jenis kamar berhasil dihapus.');
    }
}
```

### `app/Http/Controllers/TransactionController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\StayExtension;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /**
     * Daftar tamu yang sedang menginap (checked_in).
     */
    public function active(): View
    {
        $transactions = Transaction::with(['customer', 'room.roomType'])
            ->active()
            ->orderBy('check_out_date')
            ->get();

        $availableRooms = Room::available()->orderBy('room_number')->get();

        return view('transactions.active', compact('transactions', 'availableRooms'));
    }

    public function createCheckInForm(): View
    {
        $rooms = Room::with('roomType')->available()->get();

        return view('transactions.checkin', compact('rooms'));
    }

    /**
     * Proses check-in baru: validasi & upload KTP wajib, input DP, kalkulasi diskon.
     */
    public function createCheckIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'id_card_number' => ['required', 'string', 'max:50'],
            'id_card_photo' => ['required', 'image', 'max:4096'], // wajib upload, maks 4MB
            'room_id' => ['required', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,transfer,qris,debit,kartu_kredit'],
        ]);

        $room = Room::with('roomType')->findOrFail($data['room_id']);

        if ($room->status !== Room::STATUS_AVAILABLE) {
            return back()->withInput()->withErrors('Kamar tidak tersedia untuk check-in.');
        }

        $transaction = DB::transaction(function () use ($data, $room, $request) {
            $customer = Customer::firstOrCreate(
                ['id_card_number' => $data['id_card_number']],
                ['name' => $data['customer_name'], 'phone' => $data['customer_phone']]
            );

            // Simpan foto KTP di disk PRIVATE, bukan public — data PII sensitif.
            $idCardPath = $request->file('id_card_photo')->store('id-cards', 'private');

            $customer->update([
                'name' => $data['customer_name'],
                'phone' => $data['customer_phone'],
                'id_card_photo' => $idCardPath,
            ]);

            $totalDays = (int) now()->parse($data['check_in_date'])->diffInDays($data['check_out_date']);
            $totalDays = max($totalDays, 1);
            $pricePerNight = (float) $room->roomType->price;
            $totalPrice = $totalDays * $pricePerNight;

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $finalPrice = $data['discount_type'] === 'percentage'
                ? $totalPrice - ($totalPrice * $discountAmount / 100)
                : $totalPrice - $discountAmount;
            $finalPrice = max($finalPrice, 0);

            $downPayment = (float) $data['down_payment'];

            $transaction = Transaction::create([
                'code' => 'TRX-' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'room_id' => $room->id,
                'user_id' => auth()->id(),
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'total_days' => $totalDays,
                'room_price_per_night' => $pricePerNight,
                'total_price' => $totalPrice,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_amount' => $discountAmount,
                'final_price' => $finalPrice,
                'down_payment' => $downPayment,
                'remaining_payment' => max($finalPrice - $downPayment, 0),
                'status' => Transaction::STATUS_CHECKED_IN,
            ]);

            if ($downPayment > 0) {
                Payment::create([
                    'transaction_id' => $transaction->id,
                    'amount' => $downPayment,
                    'payment_method' => $data['payment_method'],
                    'type' => Payment::TYPE_DP,
                    'received_by' => auth()->id(),
                    'paid_at' => now(),
                ]);
            }

            $room->update(['status' => Room::STATUS_OCCUPIED]);

            $customer->increment('visit_count');

            return $transaction;
        });

        return redirect()
            ->route('transactions.active')
            ->with('success', "Check-in berhasil. Kode transaksi: {$transaction->code}");
    }

    /**
     * Tambah durasi menginap; otomatis update total tagihan & catat histori.
     */
    public function extendStay(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->status === Transaction::STATUS_CHECKED_IN, 404);

        $data = $request->validate([
            'new_checkout_date' => ['required', 'date', 'after:' . $transaction->check_out_date->format('Y-m-d')],
        ]);

        DB::transaction(function () use ($data, $transaction) {
            $oldCheckout = $transaction->check_out_date;
            $newCheckout = $data['new_checkout_date'];

            $additionalDays = (int) now()->parse($oldCheckout)->diffInDays($newCheckout);
            $additionalPrice = $additionalDays * (float) $transaction->room_price_per_night;

            StayExtension::create([
                'transaction_id' => $transaction->id,
                'old_checkout_date' => $oldCheckout,
                'new_checkout_date' => $newCheckout,
                'additional_days' => $additionalDays,
                'additional_price' => $additionalPrice,
                'extended_by' => auth()->id(),
            ]);

            $newTotalPrice = (float) $transaction->total_price + $additionalPrice;
            $newFinalPrice = (float) $transaction->final_price + $additionalPrice;

            $transaction->update([
                'check_out_date' => $newCheckout,
                'total_days' => $transaction->total_days + $additionalDays,
                'total_price' => $newTotalPrice,
                'final_price' => $newFinalPrice,
                'remaining_payment' => max($newFinalPrice - $transaction->totalPaid(), 0),
            ]);
        });

        return back()->with('success', 'Masa inap berhasil diperpanjang.');
    }

    /**
     * Pindah kamar tamu aktif, alasan wajib diisi.
     */
    public function transferRoom(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->status === Transaction::STATUS_CHECKED_IN, 404);

        $data = $request->validate([
            'to_room_id' => ['required', 'exists:rooms,id', 'different:' . $transaction->room_id],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $toRoom = Room::findOrFail($data['to_room_id']);

        if ($toRoom->status !== Room::STATUS_AVAILABLE) {
            return back()->withErrors('Kamar tujuan tidak tersedia.');
        }

        DB::transaction(function () use ($data, $transaction, $toRoom) {
            $fromRoom = $transaction->room;

            RoomTransfer::create([
                'transaction_id' => $transaction->id,
                'from_room_id' => $fromRoom->id,
                'to_room_id' => $toRoom->id,
                'reason' => $data['reason'],
                'transferred_at' => now(),
            ]);

            $fromRoom->update(['status' => Room::STATUS_DIRTY]);
            $toRoom->update(['status' => Room::STATUS_OCCUPIED]);

            $transaction->update(['room_id' => $toRoom->id]);
        });

        return back()->with('success', 'Tamu berhasil dipindahkan ke kamar ' . $toRoom->room_number . '.');
    }

    /**
     * Tambah pembayaran (DP tambahan / pelunasan) tanpa harus check-out.
     */
    public function addPayment(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->status === Transaction::STATUS_CHECKED_IN, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,transfer,qris,debit,kartu_kredit'],
            'type' => ['required', 'in:dp,pelunasan'],
        ]);

        DB::transaction(function () use ($data, $transaction) {
            Payment::create([
                'transaction_id' => $transaction->id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'type' => $data['type'],
                'received_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $transaction->update([
                'remaining_payment' => max((float) $transaction->final_price - $transaction->totalPaid(), 0),
            ]);
        });

        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }

    /**
     * Selesaikan transaksi: hitung sisa pelunasan, catat pembayaran akhir, kamar jadi dirty.
     */
    public function processCheckOut(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->status === Transaction::STATUS_CHECKED_IN, 404);

        $data = $request->validate([
            'payment_method' => ['required_if:remaining,>,0', 'nullable', 'in:cash,transfer,qris,debit,kartu_kredit'],
        ]);

        DB::transaction(function () use ($data, $transaction) {
            $remaining = max((float) $transaction->final_price - $transaction->totalPaid(), 0);

            if ($remaining > 0) {
                Payment::create([
                    'transaction_id' => $transaction->id,
                    'amount' => $remaining,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'type' => Payment::TYPE_PELUNASAN,
                    'received_by' => auth()->id(),
                    'paid_at' => now(),
                ]);
            }

            $transaction->update([
                'remaining_payment' => 0,
                'status' => Transaction::STATUS_CHECKED_OUT,
            ]);

            $transaction->room->update(['status' => Room::STATUS_DIRTY]);
        });

        return redirect()
            ->route('transactions.active')
            ->with('success', 'Check-out berhasil diselesaikan.');
    }
}
```


## Routes

### `routes/web.php`

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomKeeperController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// ==== Guest ====
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', fn () => redirect()->route('login'));

// ==== Semua role yang sudah login ====
Route::middleware('auth')->group(function () {

    // ---- Owner only ----
    Route::middleware('role:owner')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        Route::get('/room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
        Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
        Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');
        Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    // ---- Owner & Resepsionis ----
    Route::middleware('role:owner,resepsionis')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::patch('/rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.update-status');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('/customers/{customer}/id-card', [CustomerController::class, 'idCardPhoto'])->name('customers.id-card');

        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::patch('/reservations/{reservation}/no-show', [ReservationController::class, 'markNoShow'])->name('reservations.no-show');

        Route::get('/transactions/active', [TransactionController::class, 'active'])->name('transactions.active');
        Route::get('/transactions/checkin', [TransactionController::class, 'createCheckInForm'])->name('transactions.checkin-form');
        Route::post('/transactions/checkin', [TransactionController::class, 'createCheckIn'])->name('transactions.checkin');
        Route::patch('/transactions/{transaction}/extend', [TransactionController::class, 'extendStay'])->name('transactions.extend');
        Route::patch('/transactions/{transaction}/transfer', [TransactionController::class, 'transferRoom'])->name('transactions.transfer');
        Route::post('/transactions/{transaction}/payments', [TransactionController::class, 'addPayment'])->name('transactions.add-payment');
        Route::patch('/transactions/{transaction}/checkout', [TransactionController::class, 'processCheckOut'])->name('transactions.checkout');
    });

    // ---- Room Keeper only ----
    Route::middleware('role:room_keeper')->group(function () {
        Route::get('/room-keeper', [RoomKeeperController::class, 'index'])->name('room-keeper.index');
        Route::patch('/room-keeper/{room}/status', [RoomKeeperController::class, 'updateStatus'])->name('room-keeper.update-status');
    });
});
```


## Seeder

### `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\RoomType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun awal per role — GANTI PASSWORD ini sebelum dipakai production.
        User::create([
            'name' => 'Owner',
            'email' => 'owner@hotel.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_OWNER,
        ]);

        User::create([
            'name' => 'Resepsionis',
            'email' => 'resepsionis@hotel.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RESEPSIONIS,
        ]);

        User::create([
            'name' => 'Room Keeper',
            'email' => 'roomkeeper@hotel.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ROOM_KEEPER,
        ]);

        // Contoh data jenis kamar & kamar supaya aplikasi langsung bisa dicoba.
        $standard = RoomType::create([
            'name' => 'Standard',
            'price' => 150000,
            'description' => 'Kamar standar dengan kipas angin.',
        ]);

        $deluxe = RoomType::create([
            'name' => 'Deluxe',
            'price' => 250000,
            'description' => 'Kamar dengan AC dan TV.',
        ]);

        foreach (range(101, 105) as $number) {
            Room::create([
                'room_type_id' => $standard->id,
                'room_number' => (string) $number,
                'status' => Room::STATUS_AVAILABLE,
            ]);
        }

        foreach (range(201, 203) as $number) {
            Room::create([
                'room_type_id' => $deluxe->id,
                'room_number' => (string) $number,
                'status' => Room::STATUS_AVAILABLE,
            ]);
        }
    }
}
```


## Views

### `resources/views/auth/login.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Hotel Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow w-full max-w-sm">
        <h1 class="text-xl font-bold mb-6 text-center">🏨 Hotel Manager</h1>

        @if($errors->any())
            <div class="mb-4 rounded bg-red-100 text-red-800 px-4 py-2 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember"> Ingat saya
            </label>
            <button type="submit" class="w-full bg-gray-900 text-white rounded py-2 text-sm hover:bg-gray-800">
                Masuk
            </button>
        </form>
    </div>
</body>
</html>
```

### `resources/views/customers/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Pelanggan')

@section('content')
<form method="GET" class="mb-4">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, telepon, atau no. KTP..."
           class="border rounded px-3 py-2 text-sm w-full max-w-md">
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-2">Nama</th>
                <th class="px-4 py-2">Telepon</th>
                <th class="px-4 py-2">Kunjungan</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($customers as $customer)
                <tr class="border-t">
                    <td class="px-4 py-2">{{ $customer->name }}</td>
                    <td class="px-4 py-2">{{ $customer->phone }}</td>
                    <td class="px-4 py-2">{{ $customer->visit_count }}x</td>
                    <td class="px-4 py-2">
                        <span @class([
                            'text-xs px-2 py-1 rounded',
                            'bg-purple-100 text-purple-800' => $customer->rating_status === 'vip',
                            'bg-gray-100 text-gray-800' => $customer->rating_status === 'regular',
                            'bg-red-100 text-red-800' => $customer->rating_status === 'blacklisted',
                        ])>{{ ucfirst($customer->rating_status) }}</span>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('customers.show', $customer) }}" class="text-blue-600 text-xs hover:underline">Detail</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $customers->links() }}</div>
@endsection
```

### `resources/views/customers/show.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Detail Pelanggan')

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-1">{{ $customer->name }}</h2>
        <p class="text-sm text-gray-500 mb-4">{{ $customer->phone }} &middot; {{ $customer->visit_count }}x kunjungan</p>

        @if($customer->id_card_photo)
            <a href="{{ $customer->id_card_photo_url }}" target="_blank" class="text-blue-600 text-xs hover:underline">Lihat Foto KTP</a>
        @endif

        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-3 text-sm mt-4">
            @csrf @method('PUT')
            <div>
                <label class="block mb-1">Status</label>
                <select name="rating_status" class="w-full border rounded px-3 py-2">
                    <option value="regular" @selected($customer->rating_status === 'regular')>Regular</option>
                    <option value="vip" @selected($customer->rating_status === 'vip')>VIP</option>
                    <option value="blacklisted" @selected($customer->rating_status === 'blacklisted')>Blacklisted</option>
                </select>
            </div>
            <div>
                <label class="block mb-1">Catatan</label>
                <textarea name="notes" rows="3" class="w-full border rounded px-3 py-2">{{ $customer->notes }}</textarea>
            </div>
            <button class="w-full bg-gray-900 text-white rounded py-2 hover:bg-gray-800">Simpan</button>
        </form>
    </div>

    <div class="col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b font-semibold text-sm">Histori Menginap</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Kode</th>
                    <th class="px-4 py-2">Kamar</th>
                    <th class="px-4 py-2">Check-in</th>
                    <th class="px-4 py-2">Check-out</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customer->transactions as $trx)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $trx->code }}</td>
                        <td class="px-4 py-2">{{ $trx->room->room_number }}</td>
                        <td class="px-4 py-2">{{ $trx->check_in_date->format('d M Y') }}</td>
                        <td class="px-4 py-2">{{ $trx->check_out_date->format('d M Y') }}</td>
                        <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $trx->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Belum ada histori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
```

### `resources/views/dashboard/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Total Kamar</p>
        <p class="text-2xl font-bold">{{ $roomStats['total'] }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Tersedia</p>
        <p class="text-2xl font-bold text-green-600">{{ $roomStats['available'] }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Terisi</p>
        <p class="text-2xl font-bold text-blue-600">{{ $roomStats['occupied'] }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Kotor</p>
        <p class="text-2xl font-bold text-yellow-600">{{ $roomStats['dirty'] }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Maintenance</p>
        <p class="text-2xl font-bold text-red-600">{{ $roomStats['maintenance'] }}</p>
    </div>
</div>

<div class="grid grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Check-in Hari Ini</p>
        <p class="text-2xl font-bold">{{ $todayCheckIns }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Check-out Hari Ini</p>
        <p class="text-2xl font-bold">{{ $todayCheckOuts }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Pendapatan Bulan Ini</p>
        <p class="text-2xl font-bold">Rp {{ number_format($revenueThisMonth, 0, ',', '.') }}</p>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b font-semibold text-sm">Transaksi Terbaru</div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-2">Kode</th>
                <th class="px-4 py-2">Tamu</th>
                <th class="px-4 py-2">Kamar</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentTransactions as $trx)
                <tr class="border-t">
                    <td class="px-4 py-2">{{ $trx->code }}</td>
                    <td class="px-4 py-2">{{ $trx->customer->name }}</td>
                    <td class="px-4 py-2">{{ $trx->room->room_number }}</td>
                    <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $trx->status) }}</td>
                    <td class="px-4 py-2">Rp {{ number_format($trx->final_price, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
```

### `resources/views/layouts/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Hotel Management')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="w-64 bg-gray-900 text-white flex-shrink-0 hidden md:block">
        <div class="p-4 text-xl font-bold border-b border-gray-700">
            🏨 Hotel Manager
        </div>
        <nav class="p-4 space-y-1 text-sm">
            @auth
                @if(auth()->user()->isOwner())
                    <a href="{{ route('dashboard.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('dashboard.*') ? 'bg-gray-700' : '' }}">Dashboard</a>
                    <a href="{{ route('reports.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('reports.*') ? 'bg-gray-700' : '' }}">Laporan</a>
                    <a href="{{ route('room-types.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('room-types.*') ? 'bg-gray-700' : '' }}">Jenis Kamar</a>
                @endif

                @if(auth()->user()->isOwner() || auth()->user()->isResepsionis())
                    <a href="{{ route('rooms.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('rooms.*') ? 'bg-gray-700' : '' }}">Kamar</a>
                    <a href="{{ route('reservations.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('reservations.*') ? 'bg-gray-700' : '' }}">Reservasi</a>
                    <a href="{{ route('transactions.active') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('transactions.*') ? 'bg-gray-700' : '' }}">Tamu Aktif</a>
                    <a href="{{ route('transactions.checkin-form') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('transactions.checkin*') ? 'bg-gray-700' : '' }}">Check-in Baru</a>
                    <a href="{{ route('customers.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('customers.*') ? 'bg-gray-700' : '' }}">Pelanggan</a>
                @endif

                @if(auth()->user()->isRoomKeeper())
                    <a href="{{ route('room-keeper.index') }}" class="block px-3 py-2 rounded hover:bg-gray-700 {{ request()->routeIs('room-keeper.*') ? 'bg-gray-700' : '' }}">Status Kamar</a>
                @endif
            @endauth
        </nav>
    </aside>

    {{-- Main content --}}
    <div class="flex-1 flex flex-col">
        <header class="bg-white border-b px-6 py-3 flex justify-between items-center">
            <h1 class="text-lg font-semibold">@yield('title', 'Hotel Management')</h1>
            @auth
                <div class="flex items-center gap-4 text-sm">
                    <span>{{ auth()->user()->name }} <span class="text-gray-400">({{ auth()->user()->role }})</span></span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-red-600 hover:underline">Keluar</button>
                    </form>
                </div>
            @endauth
        </header>

        <main class="flex-1 p-6">
            @if(session('success'))
                <div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded bg-red-100 text-red-800 px-4 py-2 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
```

### `resources/views/reports/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Laporan')

@section('content')
<form method="GET" class="flex gap-4 items-end mb-6 text-sm">
    <div>
        <label class="block mb-1">Dari Tanggal</label>
        <input type="date" name="start_date" value="{{ \Illuminate\Support\Carbon::parse($startDate)->format('Y-m-d') }}"
               class="border rounded px-3 py-2">
    </div>
    <div>
        <label class="block mb-1">Sampai Tanggal</label>
        <input type="date" name="end_date" value="{{ \Illuminate\Support\Carbon::parse($endDate)->format('Y-m-d') }}"
               class="border rounded px-3 py-2">
    </div>
    <button class="bg-gray-900 text-white rounded px-4 py-2 hover:bg-gray-800">Filter</button>
</form>

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Total Pendapatan</p>
        <p class="text-2xl font-bold">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Jumlah Check-out</p>
        <p class="text-2xl font-bold">{{ $checkOuts }}</p>
    </div>
    <div class="bg-white rounded-lg p-4 shadow">
        <p class="text-xs text-gray-500">Total Malam Terjual</p>
        <p class="text-2xl font-bold">{{ $totalRoomNights }}</p>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b font-semibold text-sm">Rincian Pembayaran</div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-2">Tanggal</th>
                <th class="px-4 py-2">Kode Transaksi</th>
                <th class="px-4 py-2">Tamu</th>
                <th class="px-4 py-2">Kamar</th>
                <th class="px-4 py-2">Jenis</th>
                <th class="px-4 py-2">Metode</th>
                <th class="px-4 py-2 text-right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                <tr class="border-t">
                    <td class="px-4 py-2">{{ $payment->paid_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-2">{{ $payment->transaction->code }}</td>
                    <td class="px-4 py-2">{{ $payment->transaction->customer->name }}</td>
                    <td class="px-4 py-2">{{ $payment->transaction->room->room_number }}</td>
                    <td class="px-4 py-2 capitalize">{{ $payment->type }}</td>
                    <td class="px-4 py-2 uppercase text-xs">{{ $payment->payment_method }}</td>
                    <td class="px-4 py-2 text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Tidak ada data pembayaran pada rentang ini.</td></tr>
            @endforelse
        </tbody>
        @if($payments->isNotEmpty())
            <tfoot>
                <tr class="border-t font-semibold bg-gray-50">
                    <td colspan="6" class="px-4 py-2 text-right">Total</td>
                    <td class="px-4 py-2 text-right">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
```

### `resources/views/reservations/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Reservasi Baru')

@section('content')
<div class="bg-white rounded-lg shadow p-6 max-w-xl">
    <form method="POST" action="{{ route('reservations.store') }}" class="space-y-4 text-sm">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Nama Tamu</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">No. Telepon</label>
                <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1">Kamar</label>
            <select name="room_id" required class="w-full border rounded px-3 py-2">
                @foreach($roomTypes as $type)
                    <optgroup label="{{ $type->name }} - Rp {{ number_format($type->price, 0, ',', '.') }}">
                        @foreach($type->rooms as $room)
                            <option value="{{ $room->id }}">Kamar {{ $room->room_number }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Tanggal Check-in</label>
                <input type="date" name="check_in_date" value="{{ old('check_in_date') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Tanggal Check-out</label>
                <input type="date" name="check_out_date" value="{{ old('check_out_date') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <button class="w-full bg-gray-900 text-white rounded py-2 hover:bg-gray-800">Buat Reservasi</button>
    </form>
</div>
@endsection
```

### `resources/views/reservations/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Reservasi')

@section('content')
<div class="flex justify-between items-center mb-4">
    <p class="text-sm text-gray-500">Daftar reservasi yang akan datang</p>
    <a href="{{ route('reservations.create') }}" class="bg-gray-900 text-white text-sm px-4 py-2 rounded hover:bg-gray-800">
        + Reservasi Baru
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-2">Kode</th>
                <th class="px-4 py-2">Tamu</th>
                <th class="px-4 py-2">Kamar</th>
                <th class="px-4 py-2">Check-in</th>
                <th class="px-4 py-2">Check-out</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($reservations as $r)
                <tr class="border-t">
                    <td class="px-4 py-2">{{ $r->code }}</td>
                    <td class="px-4 py-2">{{ $r->customer->name }}</td>
                    <td class="px-4 py-2">{{ $r->room->room_number }}</td>
                    <td class="px-4 py-2">{{ $r->check_in_date->format('d M Y') }}</td>
                    <td class="px-4 py-2">{{ $r->check_out_date->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <a href="{{ route('reservations.edit', $r) }}" class="text-blue-600 text-xs hover:underline">Check-in</a>
                        <form method="POST" action="{{ route('reservations.no-show', $r) }}" class="inline" onsubmit="return confirm('Tandai sebagai no-show?')">
                            @csrf @method('PATCH')
                            <button class="text-yellow-600 text-xs hover:underline">No-show</button>
                        </form>
                        <form method="POST" action="{{ route('reservations.cancel', $r) }}" class="inline" onsubmit="return confirm('Batalkan reservasi ini?')">
                            @csrf @method('PATCH')
                            <button class="text-red-600 text-xs hover:underline">Batalkan</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada reservasi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $reservations->links() }}</div>
@endsection
```

### `resources/views/room-keeper/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Status Kamar')

@section('content')
@php
$statusColor = [
    'available' => 'bg-green-100 text-green-800 border-green-300',
    'occupied' => 'bg-blue-100 text-blue-800 border-blue-300',
    'dirty' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
    'maintenance' => 'bg-red-100 text-red-800 border-red-300',
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
    @foreach($rooms as $room)
        <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $statusColor[$room->status] }}">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-semibold text-lg">Kamar {{ $room->room_number }}</p>
                    <p class="text-xs text-gray-500">{{ $room->roomType->name }}</p>
                </div>
                <span class="text-xs px-2 py-1 rounded {{ $statusColor[$room->status] }}">
                    {{ ucfirst($room->status) }}
                </span>
            </div>

            <form method="POST" action="{{ route('room-keeper.update-status', $room) }}" class="space-y-2">
                @csrf @method('PATCH')
                <div class="grid grid-cols-3 gap-2">
                    <button type="submit" name="status_reported" value="clean"
                            class="bg-green-600 text-white text-xs rounded py-2 hover:bg-green-700">
                        ✓ Bersih
                    </button>
                    <button type="submit" name="status_reported" value="dirty"
                            class="bg-yellow-500 text-white text-xs rounded py-2 hover:bg-yellow-600">
                        Kotor
                    </button>
                    <button type="submit" name="status_reported" value="maintenance"
                            class="bg-red-600 text-white text-xs rounded py-2 hover:bg-red-700">
                        Rusak
                    </button>
                </div>
                <textarea name="notes" rows="2" placeholder="Catatan kerusakan (opsional)"
                          class="w-full border rounded px-2 py-1 text-xs"></textarea>
            </form>
        </div>
    @endforeach
</div>
@endsection
```

### `resources/views/room-types/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Jenis Kamar')

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Harga/malam</th>
                    <th class="px-4 py-2">Jumlah Kamar</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($roomTypes as $type)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $type->name }}</td>
                        <td class="px-4 py-2">Rp {{ number_format($type->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-2">{{ $type->rooms_count }}</td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('room-types.destroy', $type) }}"
                                  onsubmit="return confirm('Hapus jenis kamar ini?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 text-xs hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold text-sm mb-3">Tambah Jenis Kamar</h2>
        <form method="POST" action="{{ route('room-types.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block mb-1">Nama</label>
                <input type="text" name="name" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Harga per malam (Rp)</label>
                <input type="number" name="price" min="0" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Deskripsi</label>
                <textarea name="description" class="w-full border rounded px-3 py-2"></textarea>
            </div>
            <button class="w-full bg-gray-900 text-white rounded py-2 hover:bg-gray-800">Simpan</button>
        </form>
    </div>
</div>
@endsection
```

### `resources/views/rooms/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Kamar')

@section('content')
@php
$statusColor = [
    'available' => 'bg-green-100 text-green-800',
    'occupied' => 'bg-blue-100 text-blue-800',
    'dirty' => 'bg-yellow-100 text-yellow-800',
    'maintenance' => 'bg-red-100 text-red-800',
];
@endphp

<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 grid grid-cols-2 gap-3">
        @foreach($rooms as $room)
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="font-semibold">Kamar {{ $room->room_number }}</p>
                        <p class="text-xs text-gray-500">{{ $room->roomType->name }}</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded {{ $statusColor[$room->status] }}">
                        {{ ucfirst($room->status) }}
                    </span>
                </div>
                <form method="POST" action="{{ route('rooms.update-status', $room) }}" class="flex gap-2 mt-2">
                    @csrf @method('PATCH')
                    <select name="status" class="flex-1 border rounded text-xs px-2 py-1">
                        @foreach(['available','occupied','dirty','maintenance'] as $s)
                            <option value="{{ $s }}" @selected($room->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button class="text-xs bg-gray-900 text-white px-3 rounded hover:bg-gray-800">Ubah</button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow p-4 h-fit">
        <h2 class="font-semibold text-sm mb-3">Tambah Kamar</h2>
        <form method="POST" action="{{ route('rooms.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block mb-1">Jenis Kamar</label>
                <select name="room_type_id" required class="w-full border rounded px-3 py-2">
                    @foreach($roomTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1">Nomor Kamar</label>
                <input type="text" name="room_number" required class="w-full border rounded px-3 py-2">
            </div>
            <button class="w-full bg-gray-900 text-white rounded py-2 hover:bg-gray-800">Simpan</button>
        </form>
    </div>
</div>
@endsection
```

### `resources/views/transactions/active.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Tamu Aktif')

@section('content')
<div class="space-y-4">
    @forelse($transactions as $trx)
        <details class="bg-white rounded-lg shadow overflow-hidden">
            <summary class="cursor-pointer list-none px-4 py-3 flex justify-between items-center hover:bg-gray-50">
                <div class="flex gap-6 items-center text-sm">
                    <div>
                        <p class="font-semibold">{{ $trx->customer->name }}</p>
                        <p class="text-xs text-gray-500">{{ $trx->code }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Kamar</p>
                        <p>{{ $trx->room->room_number }} &middot; {{ $trx->room->roomType->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Check-in</p>
                        <p>{{ $trx->check_in_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Check-out</p>
                        <p>{{ $trx->check_out_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Sisa Bayar</p>
                        <p @class(['font-semibold', 'text-red-600' => $trx->remaining_payment > 0, 'text-green-600' => $trx->remaining_payment <= 0])>
                            Rp {{ number_format($trx->remaining_payment, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
                <span class="text-xs text-blue-600">Kelola &raquo;</span>
            </summary>

            <div class="border-t px-4 py-4 grid grid-cols-4 gap-4 text-sm bg-gray-50">

                {{-- Perpanjang --}}
                <form method="POST" action="{{ route('transactions.extend', $trx) }}" class="space-y-2">
                    @csrf @method('PATCH')
                    <p class="font-semibold text-xs text-gray-500">Perpanjang Menginap</p>
                    <input type="date" name="new_checkout_date" required class="w-full border rounded px-2 py-1 text-xs"
                           min="{{ $trx->check_out_date->addDay()->format('Y-m-d') }}">
                    <button class="w-full bg-gray-900 text-white rounded py-1 text-xs hover:bg-gray-800">Perpanjang</button>
                </form>

                {{-- Pindah Kamar --}}
                <form method="POST" action="{{ route('transactions.transfer', $trx) }}" class="space-y-2">
                    @csrf @method('PATCH')
                    <p class="font-semibold text-xs text-gray-500">Pindah Kamar</p>
                    <select name="to_room_id" required class="w-full border rounded px-2 py-1 text-xs">
                        <option value="">Pilih kamar tujuan</option>
                        @foreach($availableRooms as $r)
                            <option value="{{ $r->id }}">{{ $r->room_number }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="reason" placeholder="Alasan pindah" required class="w-full border rounded px-2 py-1 text-xs">
                    <button class="w-full bg-gray-900 text-white rounded py-1 text-xs hover:bg-gray-800">Pindahkan</button>
                </form>

                {{-- Tambah Pembayaran --}}
                <form method="POST" action="{{ route('transactions.add-payment', $trx) }}" class="space-y-2">
                    @csrf
                    <p class="font-semibold text-xs text-gray-500">Tambah Pembayaran</p>
                    <input type="number" name="amount" min="1" placeholder="Nominal (Rp)" required class="w-full border rounded px-2 py-1 text-xs">
                    <select name="payment_method" required class="w-full border rounded px-2 py-1 text-xs">
                        <option value="cash">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                        <option value="debit">Kartu Debit</option>
                        <option value="kartu_kredit">Kartu Kredit</option>
                    </select>
                    <select name="type" required class="w-full border rounded px-2 py-1 text-xs">
                        <option value="dp">DP Tambahan</option>
                        <option value="pelunasan">Pelunasan</option>
                    </select>
                    <button class="w-full bg-gray-900 text-white rounded py-1 text-xs hover:bg-gray-800">Catat Pembayaran</button>
                </form>

                {{-- Check-out --}}
                <form method="POST" action="{{ route('transactions.checkout', $trx) }}" class="space-y-2"
                      onsubmit="return confirm('Selesaikan check-out untuk {{ $trx->customer->name }}?')">
                    @csrf @method('PATCH')
                    <p class="font-semibold text-xs text-gray-500">Check-out</p>
                    <p class="text-xs text-gray-500">
                        Total: Rp {{ number_format($trx->final_price, 0, ',', '.') }}<br>
                        Sudah dibayar: Rp {{ number_format($trx->totalPaid(), 0, ',', '.') }}
                    </p>
                    @if($trx->remaining_payment > 0)
                        <select name="payment_method" required class="w-full border rounded px-2 py-1 text-xs">
                            <option value="cash">Tunai</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="debit">Kartu Debit</option>
                            <option value="kartu_kredit">Kartu Kredit</option>
                        </select>
                    @endif
                    <button class="w-full bg-red-600 text-white rounded py-1 text-xs hover:bg-red-700">Selesaikan Check-out</button>
                </form>
            </div>
        </details>
    @empty
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-400 text-sm">
            Belum ada tamu yang sedang menginap.
        </div>
    @endforelse
</div>
@endsection
```

### `resources/views/transactions/checkin.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Check-in Tamu')

@php
    $reservation = $reservation ?? null;
@endphp

@section('content')
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="{{ route('transactions.checkin') }}" enctype="multipart/form-data" class="space-y-4 text-sm">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Nama Tamu</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $reservation?->customer->name ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">No. Telepon</label>
                <input type="text" name="customer_phone" value="{{ old('customer_phone', $reservation?->customer->phone ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Nomor KTP</label>
                <input type="text" name="id_card_number" value="{{ old('id_card_number') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Foto KTP</label>
                <input type="file" name="id_card_photo" accept="image/*" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1">Kamar</label>
            <select name="room_id" required class="w-full border rounded px-3 py-2">
                @foreach($rooms as $room)
                    <option value="{{ $room->id }}" @selected($reservation?->room_id === $room->id)>
                        Kamar {{ $room->room_number }} — {{ $room->roomType->name }} (Rp {{ number_format($room->roomType->price, 0, ',', '.') }}/malam)
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Tanggal Check-in</label>
                <input type="date" name="check_in_date" value="{{ old('check_in_date', $reservation?->check_in_date->format('Y-m-d') ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Tanggal Check-out</label>
                <input type="date" name="check_out_date" value="{{ old('check_out_date', $reservation?->check_out_date->format('Y-m-d') ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block mb-1">Jenis Diskon</label>
                <select name="discount_type" class="w-full border rounded px-3 py-2">
                    <option value="">Tanpa Diskon</option>
                    <option value="fixed">Nominal (Rp)</option>
                    <option value="percentage">Persentase (%)</option>
                </select>
            </div>
            <div>
                <label class="block mb-1">Nilai Diskon</label>
                <input type="number" name="discount_amount" min="0" value="0" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Uang Muka / DP (Rp)</label>
                <input type="number" name="down_payment" min="0" value="0" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1">Metode Pembayaran DP</label>
            <select name="payment_method" required class="w-full border rounded px-3 py-2">
                <option value="cash">Tunai</option>
                <option value="transfer">Transfer</option>
                <option value="qris">QRIS</option>
                <option value="debit">Kartu Debit</option>
                <option value="kartu_kredit">Kartu Kredit</option>
            </select>
        </div>

        <button class="w-full bg-gray-900 text-white rounded py-2 hover:bg-gray-800">Proses Check-in</button>
    </form>
</div>
@endsection
```
