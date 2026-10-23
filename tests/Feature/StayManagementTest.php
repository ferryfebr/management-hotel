<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLog;
use App\Models\RoomTransfer;
use App\Models\StayExtension;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class StayManagementTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $frontOffice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
    }

    public function test_extend_updates_dates_totals_remaining_and_history(): void
    {
        $transaction = $this->makeTransaction([
            'user' => $this->frontOffice,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'room_price_per_night' => 100000,
            'total_price' => 200000,
            'final_price' => 200000,
            'remaining_payment' => 150000,
        ]);
        $this->makePayment($transaction, 50000);

        $newCheckout = now()->addDays(4)->toDateString();

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->patch(route('transactions.extend', $transaction), ['new_checkout_date' => $newCheckout])
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame($newCheckout, $transaction->check_out_date->toDateString());
        $this->assertSame(4, $transaction->total_days);
        $this->assertSame(400000.0, (float) $transaction->total_price);
        $this->assertSame(400000.0, (float) $transaction->final_price);
        $this->assertSame(350000.0, (float) $transaction->remaining_payment);

        $extension = StayExtension::firstOrFail();
        $this->assertSame(2, $extension->additional_days);
        $this->assertSame(200000.0, (float) $extension->additional_price);
        $this->assertSame($this->frontOffice->id, $extension->extended_by);
    }

    public function test_extend_includes_charge_in_remaining_payment(): void
    {
        $transaction = $this->makeTransaction([
            'user' => $this->frontOffice,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'room_price_per_night' => 100000,
            'total_price' => 100000,
            'final_price' => 100000,
        ]);
        $this->makePayment($transaction, 30000, Payment::TYPE_DP);
        $this->makePayment($transaction, 20000, Payment::TYPE_CHARGE);

        $this->actingAs($this->frontOffice)->patch(route('transactions.extend', $transaction), [
            'new_checkout_date' => now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $transaction->refresh();
        $this->assertSame(200000.0, (float) $transaction->final_price);
        $this->assertSame(190000.0, (float) $transaction->remaining_payment);
    }

    public function test_extend_rejects_overlap_with_other_reservation(): void
    {
        $room = $this->makeRoom();
        $transaction = $this->makeTransaction([
            'room' => $room,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);
        $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);

        $oldCheckout = $transaction->check_out_date->toDateString();

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->patch(route('transactions.extend', $transaction), [
                'new_checkout_date' => now()->addDays(4)->toDateString(),
            ])
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHasErrors();

        $this->assertSame($oldCheckout, $transaction->fresh()->check_out_date->toDateString());
        $this->assertSame(0, StayExtension::count());
    }

    public function test_extend_allows_boundary_touching_next_reservation(): void
    {
        $room = $this->makeRoom();
        $transaction = $this->makeTransaction([
            'room' => $room,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);
        $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->addDays(4)->toDateString(),
            'check_out_date' => now()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.extend', $transaction), [
                'new_checkout_date' => now()->addDays(4)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, StayExtension::count());
    }

    public function test_extend_rejects_non_checked_in_transaction(): void
    {
        $transaction = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.extend', $transaction), [
                'new_checkout_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertNotFound();
    }

    public function test_extend_rejects_new_date_not_after_old_checkout(): void
    {
        $transaction = $this->makeTransaction([
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.extend', $transaction), [
                'new_checkout_date' => $transaction->check_out_date->toDateString(),
            ])
            ->assertSessionHasErrors('new_checkout_date');

        $this->assertSame(0, StayExtension::count());
    }

    public function test_transfer_moves_guest_and_marks_rooms(): void
    {
        $from = $this->makeRoom(null, '701', Room::STATUS_OCCUPIED);
        $to = $this->makeRoom(null, '702', Room::STATUS_AVAILABLE);
        $transaction = $this->makeTransaction(['room' => $from, 'user' => $this->frontOffice]);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->patch(route('transactions.transfer', $transaction), [
                'to_room_id' => $to->id,
                'reason' => 'AC tidak dingin',
            ])
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHas('success');

        $this->assertSame(Room::STATUS_DIRTY, $from->fresh()->status);
        $this->assertSame(Room::STATUS_OCCUPIED, $to->fresh()->status);
        $this->assertSame($to->id, $transaction->fresh()->room_id);

        $transfer = RoomTransfer::firstOrFail();
        $this->assertSame($from->id, $transfer->from_room_id);
        $this->assertSame($to->id, $transfer->to_room_id);
        $this->assertSame('AC tidak dingin', $transfer->reason);
        $this->assertSame($this->frontOffice->id, $transfer->transferred_by);
    }

    public function test_transfer_rejects_unavailable_destination(): void
    {
        $from = $this->makeRoom(null, '703', Room::STATUS_OCCUPIED);
        $to = $this->makeRoom(null, '704', Room::STATUS_DIRTY);
        $transaction = $this->makeTransaction(['room' => $from]);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->patch(route('transactions.transfer', $transaction), [
                'to_room_id' => $to->id,
                'reason' => 'Pindah',
            ])
            ->assertSessionHasErrors();

        $this->assertSame($from->id, $transaction->fresh()->room_id);
        $this->assertSame(0, RoomTransfer::count());
    }

    public function test_transfer_rejects_overlap_with_other_reservation(): void
    {
        $from = $this->makeRoom(null, '706', Room::STATUS_OCCUPIED);
        $to = $this->makeRoom(null, '707', Room::STATUS_AVAILABLE);
        $transaction = $this->makeTransaction([
            'room' => $from,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
        ]);
        $this->makeTransaction([
            'room' => $to,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
        ]);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->patch(route('transactions.transfer', $transaction), [
                'to_room_id' => $to->id,
                'reason' => 'Pindah',
            ])
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHasErrors();

        $this->assertSame(0, RoomTransfer::count());
        $this->assertSame($from->id, $transaction->fresh()->room_id);
        $this->assertSame(Room::STATUS_AVAILABLE, $to->fresh()->status);
    }

    public function test_transfer_validation_rejects_same_room_and_missing_reason(): void
    {
        $room = $this->makeRoom(null, '705', Room::STATUS_OCCUPIED);
        $transaction = $this->makeTransaction(['room' => $room]);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.transfer', $transaction), [
                'to_room_id' => $room->id,
            ])
            ->assertSessionHasErrors(['to_room_id', 'reason']);

        $this->assertSame(0, RoomTransfer::count());
        $this->assertSame($room->id, $transaction->fresh()->room_id);
    }

    public function test_transfer_rejects_non_checked_in_transaction(): void
    {
        $transaction = $this->makeTransaction(['status' => Transaction::STATUS_RESERVED]);
        $to = $this->makeRoom();

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.transfer', $transaction), [
                'to_room_id' => $to->id,
                'reason' => 'Pindah',
            ])
            ->assertNotFound();
    }

    public function test_add_payment_updates_remaining_payment(): void
    {
        $transaction = $this->makeTransaction([
            'user' => $this->frontOffice,
            'total_price' => 300000,
            'final_price' => 300000,
            'remaining_payment' => 300000,
        ]);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->post(route('transactions.add-payment', $transaction), [
                'amount' => 100000,
                'payment_method' => 'transfer',
                'type' => Payment::TYPE_DP,
                'notes' => 'diabaikan untuk DP',
            ])
            ->assertRedirect(route('transactions.active'));

        $this->assertSame(200000.0, (float) $transaction->fresh()->remaining_payment);
        $payment = Payment::firstOrFail();
        $this->assertSame(100000.0, (float) $payment->amount);
        $this->assertSame('transfer', $payment->payment_method);
        $this->assertNull($payment->notes);
        $this->assertSame($this->frontOffice->id, $payment->received_by);
    }

    public function test_charge_increases_bill_and_remaining_payment(): void
    {
        $transaction = $this->makeTransaction([
            'user' => $this->frontOffice,
            'total_price' => 300000,
            'final_price' => 300000,
            'remaining_payment' => 300000,
        ]);
        $this->makePayment($transaction, 100000);

        $this->actingAs($this->frontOffice)->post(route('transactions.add-payment', $transaction), [
            'amount' => 50000,
            'payment_method' => 'cash',
            'type' => Payment::TYPE_CHARGE,
            'notes' => 'Tambah jam',
        ])->assertRedirect();

        $this->assertSame(250000.0, (float) $transaction->fresh()->remaining_payment);
        $this->assertDatabaseHas('payments', [
            'type' => Payment::TYPE_CHARGE,
            'notes' => 'Tambah jam',
        ]);
    }

    public function test_overpayment_clamps_remaining_payment_to_zero(): void
    {
        $transaction = $this->makeTransaction([
            'final_price' => 100000,
            'remaining_payment' => 100000,
        ]);

        $this->actingAs($this->frontOffice)->post(route('transactions.add-payment', $transaction), [
            'amount' => 150000,
            'payment_method' => 'cash',
            'type' => Payment::TYPE_PELUNASAN,
        ])->assertRedirect();

        $this->assertSame(0.0, (float) $transaction->fresh()->remaining_payment);
    }

    public function test_add_payment_validation_and_non_active_transaction(): void
    {
        $transaction = $this->makeTransaction();

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.add-payment', $transaction), [
                'amount' => 0,
                'payment_method' => 'bitcoin',
                'type' => 'unknown',
            ])
            ->assertSessionHasErrors(['amount', 'payment_method', 'type']);

        $checkedOut = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);

        $this->actingAs($this->frontOffice)
            ->post(route('transactions.add-payment', $checkedOut), [
                'amount' => 1000,
                'payment_method' => 'cash',
                'type' => Payment::TYPE_DP,
            ])
            ->assertNotFound();
    }

    public function test_check_out_marks_transaction_complete_and_room_dirty_when_paid(): void
    {
        $room = $this->makeRoom(null, '801', Room::STATUS_OCCUPIED);
        $transaction = $this->makeTransaction([
            'room' => $room,
            'final_price' => 200000,
            'remaining_payment' => 200000,
        ]);
        $this->makePayment($transaction, 200000, Payment::TYPE_PELUNASAN);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.checkout', $transaction))
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame(Transaction::STATUS_CHECKED_OUT, $transaction->status);
        $this->assertSame(0.0, (float) $transaction->remaining_payment);
        $this->assertSame(Room::STATUS_DIRTY, $room->fresh()->status);
    }

    public function test_check_out_is_blocked_when_balance_remains(): void
    {
        $room = $this->makeRoom(null, '802', Room::STATUS_OCCUPIED);
        $transaction = $this->makeTransaction([
            'room' => $room,
            'final_price' => 200000,
            'remaining_payment' => 200000,
        ]);

        $this->actingAs($this->frontOffice)
            ->from(route('transactions.active'))
            ->patch(route('transactions.checkout', $transaction))
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHasErrors();

        $this->assertSame(Transaction::STATUS_CHECKED_IN, $transaction->fresh()->status);
        $this->assertSame(Room::STATUS_OCCUPIED, $room->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_check_out_with_outstanding_balance_records_settlement(): void
    {
        $room = $this->makeRoom(null, '803', Room::STATUS_OCCUPIED);
        $transaction = $this->makeTransaction([
            'room' => $room,
            'final_price' => 100000,
            'remaining_payment' => 100000,
        ]);
        $this->makePayment($transaction, 25000, Payment::TYPE_DP);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.checkout', $transaction), [
                'payment_method' => 'transfer',
            ])
            ->assertRedirect(route('transactions.active'))
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame(Transaction::STATUS_CHECKED_OUT, $transaction->status);
        $this->assertSame(0.0, (float) $transaction->remaining_payment);
        $this->assertSame(Room::STATUS_DIRTY, $room->fresh()->status);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'amount' => 75000,
            'payment_method' => 'transfer',
            'type' => Payment::TYPE_PELUNASAN,
            'received_by' => $this->frontOffice->id,
        ]);
    }

    public function test_check_out_settlement_includes_charge_and_existing_payment(): void
    {
        $transaction = $this->makeTransaction([
            'final_price' => 100000,
            'remaining_payment' => 100000,
        ]);
        $this->makePayment($transaction, 20000, Payment::TYPE_DP);
        $this->makePayment($transaction, 10000, Payment::TYPE_CHARGE);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.checkout', $transaction), [
                'payment_method' => 'qris',
            ])
            ->assertRedirect(route('transactions.active'));

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'amount' => 90000,
            'payment_method' => 'qris',
            'type' => Payment::TYPE_PELUNASAN,
        ]);
    }

    public function test_check_out_rejects_invalid_payment_method(): void
    {
        $transaction = $this->makeTransaction([
            'final_price' => 100000,
            'remaining_payment' => 100000,
        ]);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.checkout', $transaction), [
                'payment_method' => 'bitcoin',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(Transaction::STATUS_CHECKED_IN, $transaction->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_check_out_rejects_non_checked_in_transaction(): void
    {
        $transaction = $this->makeTransaction(['status' => Transaction::STATUS_RESERVED]);

        $this->actingAs($this->frontOffice)
            ->patch(route('transactions.checkout', $transaction))
            ->assertNotFound();
    }

    public function test_history_lists_only_checked_out_with_optional_date_filter(): void
    {
        $checkedOut = $this->makeTransaction([
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_out_date' => now()->toDateString(),
        ]);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);

        $this->actingAs($this->frontOffice)
            ->get(route('transactions.history'))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1
                && $paginator->first()->id === $checkedOut->id);

        $this->actingAs($this->frontOffice)
            ->get(route('transactions.history', [
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(6)->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 0);

        $this->actingAs($this->frontOffice)
            ->get(route('transactions.history', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_history_detail_requires_checked_out_and_builds_timeline(): void
    {
        $room = $this->makeRoom(null, '901', Room::STATUS_DIRTY);
        $transaction = $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_CHECKED_OUT,
        ]);
        $this->makePayment($transaction, 20000, Payment::TYPE_DP);
        StayExtension::create([
            'transaction_id' => $transaction->id,
            'old_checkout_date' => now()->toDateString(),
            'new_checkout_date' => now()->addDay()->toDateString(),
            'additional_days' => 1,
            'additional_price' => 100000,
            'extended_by' => $transaction->user_id,
        ]);
        RoomTransfer::create([
            'transaction_id' => $transaction->id,
            'from_room_id' => $room->id,
            'to_room_id' => $this->makeRoom(null, '902')->id,
            'reason' => 'Pindah',
            'transferred_by' => $transaction->user_id,
            'transferred_at' => now(),
        ]);
        RoomLog::create([
            'room_id' => $room->id,
            'user_id' => $transaction->user_id,
            'status_reported' => 'clean',
            'notes' => 'Bersih',
        ]);

        $response = $this->actingAs($this->frontOffice)
            ->get(route('transactions.history.detail', $transaction))
            ->assertOk();

        $types = collect($response->viewData('timeline'))->pluck('type')->all();
        $this->assertContains('transaksi', $types);
        $this->assertContains('pembayaran', $types);
        $this->assertContains('perpanjangan', $types);
        $this->assertContains('transfer', $types);
        $this->assertContains('kamar', $types);

        $active = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);
        $this->actingAs($this->frontOffice)
            ->get(route('transactions.history.detail', $active))
            ->assertNotFound();
    }

    public function test_export_history_streams_csv_with_bom_and_filters(): void
    {
        $checkedOut = $this->makeTransaction([
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_out_date' => now()->toDateString(),
        ]);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);

        $response = $this->actingAs($this->frontOffice)
            ->get(route('transactions.history.export', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ]))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Kode;"Nama Tamu";"No. Telepon";"No. KTP"', $content);
        $this->assertStringContainsString($checkedOut->code, $content);
    }

    public function test_active_list_search_filters_by_code_or_customer_name(): void
    {
        $match = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);
        $match->customer->update(['name' => 'Pencarian Unik']);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);

        $this->actingAs($this->frontOffice)
            ->get(route('transactions.active', ['q' => 'Pencarian Unik']))
            ->assertOk()
            ->assertViewHas('transactions', fn ($items) => $items->count() === 1
                && $items->first()->id === $match->id);

        $this->actingAs($this->frontOffice)
            ->get(route('transactions.active', ['q' => $match->code]))
            ->assertOk()
            ->assertViewHas('transactions', fn ($items) => $items->count() === 1);
    }
}
