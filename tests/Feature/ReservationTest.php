<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $frontOffice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
    }

    public function test_store_creates_reserved_transaction_with_price_snapshot(): void
    {
        $room = $this->makeRoom(null, '301');
        $room->roomType->update(['price' => 200000]);

        $response = $this->actingAs($this->frontOffice)->post(route('reservations.store'), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'room_id' => $room->id,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertRedirect(route('reservations.index'));

        $transaction = Transaction::first();
        $this->assertSame(Transaction::STATUS_RESERVED, $transaction->status);
        $this->assertStringStartsWith('INV-', $transaction->code);
        $this->assertSame(3, $transaction->total_days);
        $this->assertSame(600000.0, (float) $transaction->total_price);
        $this->assertSame(600000.0, (float) $transaction->final_price);
        $this->assertSame($this->frontOffice->id, $transaction->user_id);
        $this->assertDatabaseHas('customers', [
            'phone' => '081234567890',
            'name' => 'Budi',
        ]);
    }

    public function test_store_does_not_change_room_status_to_occupied(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)->post(route('reservations.store'), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(Room::STATUS_AVAILABLE, $room->fresh()->status);
    }

    public function test_store_rejects_overlapping_reservation_for_same_room(): void
    {
        $room = $this->makeRoom();
        $existing = $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), [
                'customer_name' => 'Bentrok',
                'customer_phone' => '081234567891',
                'room_id' => $room->id,
                'check_in_date' => now()->addDays(4)->toDateString(),
                'check_out_date' => now()->addDays(6)->toDateString(),
            ])
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame($existing->id, Transaction::first()->id);
    }

    public function test_store_allows_adjacent_reservation_boundaries(): void
    {
        $room = $this->makeRoom();
        $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)->post(route('reservations.store'), [
            'customer_name' => 'Lanjutan',
            'customer_phone' => '081234567892',
            'room_id' => $room->id,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect(route('reservations.index'));

        $this->assertSame(2, Transaction::count());
    }

    public function test_store_validates_dates_phone_and_room(): void
    {
        $this->actingAs($this->frontOffice)
            ->post(route('reservations.store'), [
                'customer_name' => 'X',
                'customer_phone' => '123',
                'room_id' => 999999,
                'check_in_date' => now()->subDay()->toDateString(),
                'check_out_date' => now()->subDays(2)->toDateString(),
            ])
            ->assertSessionHasErrors(['customer_phone', 'room_id', 'check_in_date', 'check_out_date']);
    }

    public function test_store_reuses_customer_by_phone(): void
    {
        $room = $this->makeRoom();
        $existing = Customer::create([
            'name' => 'Nama Lama',
            'phone' => '081234567899',
        ]);

        $this->actingAs($this->frontOffice)->post(route('reservations.store'), [
            'customer_name' => 'Nama Baru',
            'customer_phone' => '081234567899',
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, Customer::count());
        $transaction = Transaction::first();
        $this->assertSame($existing->id, $transaction->customer_id);
        $this->assertSame('Nama Lama', $existing->fresh()->name);
    }

    public function test_create_page_only_lists_available_rooms(): void
    {
        $type = $this->makeRoomType();
        $available = $this->makeRoom($type, '401', Room::STATUS_AVAILABLE);
        $dirty = $this->makeRoom($type, '402', Room::STATUS_DIRTY);

        $response = $this->actingAs($this->frontOffice)->get(route('reservations.create'));

        $response->assertOk();
        $rooms = $response->viewData('roomTypes')->flatMap->rooms;

        $this->assertTrue($rooms->contains('id', $available->id));
        $this->assertFalse($rooms->contains('id', $dirty->id));
    }

    public function test_edit_is_only_available_for_reserved_status(): void
    {
        $reserved = $this->makeTransaction(['status' => Transaction::STATUS_RESERVED]);
        $checkedIn = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);

        $this->actingAs($this->frontOffice)
            ->get(route('reservations.edit', $reserved))
            ->assertOk();

        $this->actingAs($this->frontOffice)
            ->get(route('reservations.edit', $checkedIn))
            ->assertNotFound();
    }

    public function test_cancel_changes_status_to_cancelled(): void
    {
        $reservation = $this->makeTransaction(['status' => Transaction::STATUS_RESERVED]);

        $this->actingAs($this->frontOffice)
            ->patch(route('reservations.cancel', $reservation))
            ->assertRedirect();

        $this->assertSame(Transaction::STATUS_CANCELLED, $reservation->fresh()->status);
    }

    public function test_cancel_rejects_non_reserved_transaction(): void
    {
        $transaction = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);

        $this->actingAs($this->frontOffice)
            ->patch(route('reservations.cancel', $transaction))
            ->assertNotFound();
    }

    public function test_no_show_changes_status_and_rejects_non_reserved(): void
    {
        $reservation = $this->makeTransaction(['status' => Transaction::STATUS_RESERVED]);
        $checkedIn = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);

        $this->actingAs($this->frontOffice)
            ->patch(route('reservations.no-show', $reservation))
            ->assertRedirect();

        $this->assertSame(Transaction::STATUS_NO_SHOW, $reservation->fresh()->status);

        $this->actingAs($this->frontOffice)
            ->patch(route('reservations.no-show', $checkedIn))
            ->assertNotFound();
    }
}
