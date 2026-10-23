<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait CreatesDomainData
{
    private int $generatedRoomNumber = 1000;

    protected function makeUser(string $role = User::ROLE_OWNER, bool $active = true, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => Str::uuid().'@hotel.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => $active,
        ], $attributes));
    }

    protected function makeRoomType(float $price = 150000, array $attributes = []): RoomType
    {
        return RoomType::create(array_merge([
            'name' => 'Standard '.Str::random(4),
            'price' => $price,
            'description' => 'Test room type',
        ], $attributes));
    }

    protected function makeRoom(
        ?RoomType $roomType = null,
        ?string $number = null,
        string $status = Room::STATUS_AVAILABLE,
        array $attributes = []
    ): Room {
        return Room::create(array_merge([
            'room_type_id' => ($roomType ?? $this->makeRoomType())->id,
            'room_number' => $number ?? (string) ++$this->generatedRoomNumber,
            'status' => $status,
        ], $attributes));
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Tamu '.Str::random(4),
            'phone' => '0812'.random_int(10000000, 99999999),
            'id_card_number' => (string) random_int(1000000000000000, 9999999999999999),
            'rating_status' => Customer::RATING_REGULAR,
        ], $attributes));
    }

    protected function makeTransaction(array $attributes = []): Transaction
    {
        $room = $attributes['room'] ?? $this->makeRoom();
        $customer = $attributes['customer'] ?? $this->makeCustomer();
        $user = $attributes['user'] ?? $this->makeUser(User::ROLE_RESEPSIONIS);

        unset($attributes['room'], $attributes['customer'], $attributes['user']);

        $checkIn = $attributes['check_in_date'] ?? now()->toDateString();
        $checkOut = $attributes['check_out_date'] ?? now()->addDays(2)->toDateString();
        $days = max((int) Carbon::parse($checkIn)->diffInDays($checkOut), 1);
        $price = (float) $room->roomType->price;
        $total = $days * $price;

        return Transaction::create(array_merge([
            'code' => 'INV-'.strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'total_days' => $days,
            'room_price_per_night' => $price,
            'total_price' => $total,
            'discount_type' => null,
            'discount_amount' => 0,
            'final_price' => $total,
            'down_payment' => 0,
            'remaining_payment' => $total,
            'status' => Transaction::STATUS_CHECKED_IN,
        ], $attributes));
    }

    protected function makePayment(Transaction $transaction, float $amount, string $type = Payment::TYPE_DP, ?User $user = null, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'type' => $type,
            'received_by' => ($user ?? $transaction->user)->id,
            'paid_at' => now(),
        ], $attributes));
    }
}
