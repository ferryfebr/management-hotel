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
            'customer_phone' => ['required', 'digits_between:10,12'],
            'room_id' => ['required', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after_or_equal:check_in_date'],
        ], [
            'customer_phone.digits_between' => 'No. Telepon harus 10-12 angka.',
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
            $totalDays = max($totalDays, 1);
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

        return back()->with('success', 'Reservasi ditandai sebagai tidak datang.');
    }
}