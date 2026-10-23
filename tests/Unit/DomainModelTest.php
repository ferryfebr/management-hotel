<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_user_role_helpers_labels_default_routes_and_active_scope(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
        $keeper = $this->makeUser(User::ROLE_ROOM_KEEPER, false);

        $this->assertTrue($owner->isOwner());
        $this->assertFalse($owner->isSuperAdmin());
        $this->assertTrue($owner->hasOwnerAccess());
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->hasOwnerAccess());
        $this->assertFalse($frontOffice->hasOwnerAccess());
        $this->assertFalse($owner->isResepsionis());
        $this->assertTrue($frontOffice->isResepsionis());
        $this->assertTrue($keeper->isRoomKeeper());
        $this->assertFalse($keeper->isActive());

        $this->assertSame('dashboard.index', $owner->defaultRouteName());
        $this->assertSame('admin.dashboard', $superAdmin->defaultRouteName());
        $this->assertSame('transactions.active', $frontOffice->defaultRouteName());
        $this->assertSame('room-keeper.index', $keeper->defaultRouteName());
        $this->assertSame('dashboard.index', (new User(['role' => 'unknown']))->defaultRouteName());

        $this->assertSame('Owner', User::roleLabel(User::ROLE_OWNER));
        $this->assertSame('Super Admin', User::roleLabel(User::ROLE_SUPER_ADMIN));
        $this->assertSame('Resepsionis', User::roleLabel(User::ROLE_RESEPSIONIS));
        $this->assertSame('Room Keeper', User::roleLabel(User::ROLE_ROOM_KEEPER));
        $this->assertSame('Super Admin', User::roleLabel('super_admin'));

        $this->assertSame(3, User::active()->count());
    }

    public function test_room_status_labels_and_available_scope(): void
    {
        $available = $this->makeRoom(null, 'A', Room::STATUS_AVAILABLE);
        $occupied = $this->makeRoom(null, 'B', Room::STATUS_OCCUPIED);
        $dirty = $this->makeRoom(null, 'C', Room::STATUS_DIRTY);
        $maintenance = $this->makeRoom(null, 'D', Room::STATUS_MAINTENANCE);

        $this->assertSame('Tersedia', Room::statusLabel(Room::STATUS_AVAILABLE));
        $this->assertSame('Terisi', Room::statusLabel(Room::STATUS_OCCUPIED));
        $this->assertSame('Kotor', Room::statusLabel(Room::STATUS_DIRTY));
        $this->assertSame('Perbaikan', Room::statusLabel(Room::STATUS_MAINTENANCE));
        $this->assertSame('Unknown', Room::statusLabel('unknown'));

        $this->assertSame([$available->id], Room::available()->pluck('id')->all());
    }

    public function test_room_available_between_excludes_reserved_and_checked_in_overlaps_only(): void
    {
        $room = $this->makeRoom();
        $other = $this->makeRoom(null, 'B');

        $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-15',
        ]);
        $this->makeTransaction([
            'room' => $other,
            'status' => Transaction::STATUS_CANCELLED,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-15',
        ]);

        $this->assertFalse(Room::whereKey($room->id)->availableBetween('2026-11-12', '2026-11-14')->exists());
        $this->assertTrue(Room::whereKey($room->id)->availableBetween('2026-11-15', '2026-11-18')->exists());
        $this->assertTrue(Room::whereKey($room->id)->availableBetween('2026-11-05', '2026-11-10')->exists());
        $this->assertTrue(Room::whereKey($other->id)->availableBetween('2026-11-12', '2026-11-14')->exists());
    }

    public function test_transaction_billing_helpers_include_charge_exclude_refund_and_schedule_checkout(): void
    {
        $transaction = $this->makeTransaction([
            'check_out_date' => '2026-11-15',
            'final_price' => 300000,
            'remaining_payment' => 300000,
        ]);
        $this->makePayment($transaction, 200000, Payment::TYPE_DP);
        $this->makePayment($transaction, 50000, Payment::TYPE_CHARGE);
        $this->makePayment($transaction, 40000, Payment::TYPE_REFUND);

        $this->assertSame(200000.0, $transaction->totalPaid());
        $this->assertSame(50000.0, $transaction->totalCharge());
        $this->assertSame(350000.0, $transaction->totalBill());
        $this->assertFalse($transaction->isFullyPaid());

        $this->makePayment($transaction, 150000, Payment::TYPE_PELUNASAN);
        $this->assertTrue($transaction->isFullyPaid());

        $this->assertSame('2026-11-15 12:00:00', $transaction->scheduledCheckoutAt()->toDateTimeString());
    }

    public function test_customer_rating_and_id_card_photo_url(): void
    {
        $customer = $this->makeCustomer([
            'rating_status' => Customer::RATING_BLACKLISTED,
            'id_card_photo' => 'id-cards/a.jpg',
        ]);
        $withoutPhoto = $this->makeCustomer();

        $this->assertTrue($customer->isBlacklisted());
        $this->assertSame(route('customers.id-card', $customer), $customer->id_card_photo_url);
        $this->assertNull($withoutPhoto->id_card_photo_url);
        $this->assertFalse($withoutPhoto->isBlacklisted());
    }

    public function test_activity_log_record_uses_authenticated_user(): void
    {
        $user = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($user);
        ActivityLog::record('Aksi', ActivityLog::CATEGORY_KAMAR, 'Detail');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'Aksi',
            'category' => ActivityLog::CATEGORY_KAMAR,
            'description' => 'Detail',
        ]);
    }
}
