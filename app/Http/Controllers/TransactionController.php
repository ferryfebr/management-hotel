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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

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
            'customer_phone' => ['required', 'digits_between:10,12'],
            'id_card_number' => ['required', 'digits:16'],
            'id_card_photo' => ['required', 'image', 'max:4096'], // wajib upload, maks 4MB
            'room_id' => ['required', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,transfer,qris,debit,kartu_kredit'],
        ], [
            'customer_phone.digits_between' => 'No. Telepon harus 10-12 angka.',
            'id_card_number.digits' => 'Nomor KTP harus tepat 16 digit angka.',
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
            // Wajib dikompresi: resize (maks lebar 1000px) + kualitas 70% (prd §5.1, security §2).
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('id_card_photo'));
            $image->scaleDown(1000);
            $encoded = $image->toJpeg(70);
            $idCardPath = 'id-cards/' . uniqid() . '.jpg';
            Storage::disk('private')->put($idCardPath, (string) $encoded);

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
                'transferred_by' => auth()->id(),
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
    public function processCheckOut(Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->status === Transaction::STATUS_CHECKED_IN, 404);

        $remaining = max((float) $transaction->final_price - $transaction->totalPaid(), 0);

        if ($remaining > 0) {
            return back()->withErrors(
                'Transaksi belum lunas. Sisa bayar: Rp ' . number_format($remaining, 0, ',', '.') . '. Lunas kan dulu sebelum check-out.'
            );
        }

        DB::transaction(function () use ($transaction) {
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