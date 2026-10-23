<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $frontOffice;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
    }

    private function payload(Room $room, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Siti',
            'customer_phone' => '081298765432',
            'id_card_number' => '1234567890123456',
            'id_card_photo' => UploadedFile::fake()->image('ktp.jpg', 1200, 800),
            'room_id' => $room->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'discount_type' => '',
            'discount_amount' => 0,
            'down_payment' => 100000,
            'payment_method' => 'cash',
        ], $overrides);
    }

    public function test_check_in_creates_transaction_payment_customer_and_compressed_photo(): void
    {
        $room = $this->makeRoom(null, '501');
        $room->roomType->update(['price' => 150000]);

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), $this->payload($room))
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHas('success');

        $transaction = Transaction::firstOrFail();
        $this->assertSame(Transaction::STATUS_CHECKED_IN, $transaction->status);
        $this->assertStringStartsWith('INV-', $transaction->code);
        $this->assertSame(2, $transaction->total_days);
        $this->assertSame(300000.0, (float) $transaction->total_price);
        $this->assertSame(300000.0, (float) $transaction->final_price);
        $this->assertSame(100000.0, (float) $transaction->down_payment);
        $this->assertSame(200000.0, (float) $transaction->remaining_payment);
        $this->assertSame(Room::STATUS_OCCUPIED, $room->fresh()->status);

        $customer = Customer::firstOrFail();
        $this->assertSame(1, $customer->visit_count);
        $this->assertNotNull($customer->id_card_photo);
        Storage::disk('private')->assertExists($customer->id_card_photo);

        $imageInfo = getimagesizefromstring(Storage::disk('private')->get($customer->id_card_photo));
        $this->assertNotFalse($imageInfo);
        $this->assertLessThanOrEqual(1000, $imageInfo[0]);
        $this->assertSame('image/jpeg', $imageInfo['mime']);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'type' => Payment::TYPE_DP,
            'payment_method' => 'cash',
            'received_by' => $this->frontOffice->id,
        ]);
    }

    public function test_check_in_without_down_payment_does_not_create_payment_row(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), $this->payload($room, [
                'down_payment' => 0,
            ]))
            ->assertRedirect(route('transactions.active'));

        $this->assertSame(0, Payment::count());
        $this->assertSame(300000.0, (float) Transaction::first()->remaining_payment);
    }

    public function test_fixed_discount_reduces_final_price(): void
    {
        $room = $this->makeRoom();
        $room->roomType->update(['price' => 100000]);

        $this->actingAs($this->frontOffice)->post(route('transactions.checkin'), $this->payload($room, [
            'discount_type' => 'fixed',
            'discount_amount' => 50000,
            'down_payment' => 20000,
        ]))->assertRedirect();

        $transaction = Transaction::first();
        $this->assertSame(200000.0, (float) $transaction->total_price);
        $this->assertSame(150000.0, (float) $transaction->final_price);
        $this->assertSame(130000.0, (float) $transaction->remaining_payment);
    }

    public function test_percentage_discount_reduces_final_price(): void
    {
        $room = $this->makeRoom();
        $room->roomType->update(['price' => 100000]);

        $this->actingAs($this->frontOffice)->post(route('transactions.checkin'), $this->payload($room, [
            'discount_type' => 'percentage',
            'discount_amount' => 25,
            'down_payment' => 0,
        ]))->assertRedirect();

        $transaction = Transaction::first();
        $this->assertSame(150000.0, (float) $transaction->final_price);
        $this->assertSame(150000.0, (float) $transaction->remaining_payment);
    }

    public function test_discount_is_clamped_to_zero_when_larger_than_total(): void
    {
        $room = $this->makeRoom();
        $room->roomType->update(['price' => 100000]);

        $this->actingAs($this->frontOffice)->post(route('transactions.checkin'), $this->payload($room, [
            'discount_type' => 'fixed',
            'discount_amount' => 999999,
            'down_payment' => 0,
        ]))->assertRedirect();

        $transaction = Transaction::first();
        $this->assertSame(0.0, (float) $transaction->final_price);
        $this->assertSame(0.0, (float) $transaction->remaining_payment);
    }

    public function test_same_day_stay_counts_as_one_night(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)->post(route('transactions.checkin'), $this->payload($room, [
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->toDateString(),
        ]))->assertRedirect();

        $this->assertSame(1, Transaction::first()->total_days);
    }

    public function test_existing_customer_by_id_card_is_reused_and_visit_count_incremented(): void
    {
        $room = $this->makeRoom();
        $existing = Customer::create([
            'name' => 'Nama Lama',
            'phone' => '081200000000',
            'id_card_number' => '1234567890123456',
        ]);
        $existing->forceFill(['visit_count' => 2])->save();

        $this->actingAs($this->frontOffice)->post(route('transactions.checkin'), $this->payload($room, [
            'customer_name' => 'Nama Baru',
            'customer_phone' => '081211111111',
        ]))->assertRedirect();

        $this->assertSame(1, Customer::count());
        $existing->refresh();
        $this->assertSame('Nama Baru', $existing->name);
        $this->assertSame('081211111111', $existing->phone);
        $this->assertSame(3, $existing->visit_count);
        $this->assertSame($existing->id, Transaction::first()->customer_id);
    }

    public function test_validation_rejects_incomplete_and_malformed_input(): void
    {
        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), [])
            ->assertSessionHasErrors([
                'customer_name',
                'customer_phone',
                'id_card_number',
                'id_card_photo',
                'room_id',
                'check_in_date',
                'check_out_date',
                'down_payment',
                'payment_method',
            ]);

        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), $this->payload($room, [
                'customer_phone' => '12345',
                'id_card_number' => '123',
                'check_out_date' => now()->subDays(2)->toDateString(),
                'discount_type' => 'invalid',
                'down_payment' => -1,
                'payment_method' => 'bitcoin',
            ]))
            ->assertSessionHasErrors([
                'customer_phone',
                'id_card_number',
                'check_out_date',
                'discount_type',
                'down_payment',
                'payment_method',
            ]);
    }

    public function test_non_image_id_card_is_rejected(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), $this->payload($room, [
                'id_card_photo' => UploadedFile::fake()->create('ktp.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('id_card_photo');

        $this->assertSame(0, Transaction::count());
    }

    public function test_unavailable_room_is_rejected(): void
    {
        $room = $this->makeRoom(null, '601', Room::STATUS_DIRTY);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.checkin-form'))
            ->post(route('transactions.checkin'), $this->payload($room))
            ->assertRedirect(route('transactions.checkin-form'))
            ->assertSessionHasErrors();

        $this->assertSame(0, Transaction::count());
    }

    public function test_overlapping_reservation_is_rejected(): void
    {
        $room = $this->makeRoom();
        $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), $this->payload($room, [
                'check_in_date' => now()->addDays(2)->toDateString(),
                'check_out_date' => now()->addDays(4)->toDateString(),
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(1, Transaction::count());
    }

    public function test_reservation_is_converted_in_place_instead_of_creating_duplicate(): void
    {
        $room = $this->makeRoom();
        $reservation = $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);
        $reservationCustomer = $reservation->customer;

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.checkin'), $this->payload($room, [
                'reservation_id' => $reservation->id,
            ]))
            ->assertRedirect(route('transactions.active'));

        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Customer::count());

        $reservation->refresh();
        $this->assertSame(Transaction::STATUS_CHECKED_IN, $reservation->status);
        $this->assertSame('1234567890123456', $reservation->customer->id_card_number);
        $this->assertSame(1, $reservation->customer->visit_count);
        $this->assertSame($reservationCustomer->id, $reservation->customer_id);
        $this->assertSame(Room::STATUS_OCCUPIED, $room->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'transaction_id' => $reservation->id,
            'type' => Payment::TYPE_DP,
        ]);
    }

    public function test_reservation_id_must_point_to_a_reserved_transaction(): void
    {
        $room = $this->makeRoom();
        $alreadyCheckedIn = $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_CHECKED_IN,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.checkin-form'))
            ->post(route('transactions.checkin'), $this->payload($room, [
                'reservation_id' => $alreadyCheckedIn->id,
            ]))
            ->assertRedirect(route('transactions.checkin-form'))
            ->assertSessionHasErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(Transaction::STATUS_CHECKED_IN, $alreadyCheckedIn->fresh()->status);
    }

    public function test_ajax_success_returns_redirect_json(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)
            ->postJson(route('transactions.checkin'), $this->payload($room))
            ->assertOk()
            ->assertJson(['redirect' => route('transactions.active')]);
    }

    public function test_ajax_unavailable_room_returns_422(): void
    {
        $room = $this->makeRoom(null, '602', Room::STATUS_MAINTENANCE);

        $this->actingAs($this->frontOffice)
            ->postJson(route('transactions.checkin'), $this->payload($room))
            ->assertStatus(422)
            ->assertJson(['message' => 'Kamar tidak tersedia untuk check-in.']);
    }

    public function test_ajax_conflict_returns_422(): void
    {
        $room = $this->makeRoom();
        $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_CHECKED_IN,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->postJson(route('transactions.checkin'), $this->payload($room))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
