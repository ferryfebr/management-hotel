<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLog;
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
    public function active(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $transactions = Transaction::with(['customer', 'room.roomType'])
            ->active()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('check_out_date')
            ->get();

        $availableRooms = Room::available()->orderBy('room_number')->get();

        return view('transactions.active', compact('transactions', 'availableRooms', 'search'));
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
            // Diisi hanya saat konversi reservasi -> check-in; dipakai untuk
            // mengecualikan reservasi itu sendiri dari cek bentrok tanggal.
            'reservation_id' => ['nullable', 'exists:transactions,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after_or_equal:check_in_date'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,transfer,qris,debit,kartu_kredit'],
        ], [
            'customer_name.required' => 'Nama tamu wajib diisi.',
            'customer_phone.required' => 'No. telepon wajib diisi.',
            'customer_phone.digits_between' => 'No. Telepon harus 10-12 angka.',
            'id_card_number.required' => 'Nomor KTP wajib diisi.',
            'id_card_number.digits' => 'Nomor KTP harus tepat 16 digit angka.',
            'id_card_photo.required' => 'Foto KTP wajib diunggah.',
            'id_card_photo.image' => 'Foto KTP harus berupa gambar.',
            'id_card_photo.max' => 'Ukuran foto KTP maksimal 4MB.',
            'room_id.required' => 'Kamar wajib dipilih.',
            'room_id.exists' => 'Kamar yang dipilih tidak valid.',
            'check_in_date.required' => 'Tanggal check-in wajib diisi.',
            'check_in_date.date' => 'Tanggal check-in tidak valid.',
            'check_out_date.required' => 'Tanggal check-out wajib diisi.',
            'check_out_date.date' => 'Tanggal check-out tidak valid.',
            'check_out_date.after_or_equal' => 'Tanggal check-out tidak boleh sebelum tanggal check-in.',
            'discount_type.in' => 'Jenis diskon tidak valid.',
            'discount_amount.numeric' => 'Nilai diskon harus berupa angka.',
            'discount_amount.min' => 'Nilai diskon tidak boleh negatif.',
            'down_payment.required' => 'Uang muka / DP wajib diisi.',
            'down_payment.numeric' => 'Uang muka / DP harus berupa angka.',
            'down_payment.min' => 'Uang muka / DP tidak boleh negatif.',
            'payment_method.required' => 'Metode pembayaran DP wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran DP tidak valid.',
        ]);

        $room = Room::with('roomType')->findOrFail($data['room_id']);

        if ($room->status !== Room::STATUS_AVAILABLE) {
            return back()->withInput()->withErrors('Kamar tidak tersedia untuk check-in.');
        }

        // Cek bentrok tanggal (security.md §6 / PRD FR-1): kamar tidak boleh
        // punya transaksi reserved/checked_in lain yang overlap. Reservasi yang
        // sedang dikonversi (reservation_id) dikecualikan.
        $conflict = Room::query()
            ->whereKey($room->id)
            ->availableBetween($data['check_in_date'], $data['check_out_date'])
            ->exists();

        if (! $conflict) {
            $hasOtherBooking = \App\Models\Transaction::query()
                ->where('room_id', $room->id)
                ->whereIn('status', [Transaction::STATUS_RESERVED, Transaction::STATUS_CHECKED_IN])
                ->where('check_in_date', '<', $data['check_out_date'])
                ->where('check_out_date', '>', $data['check_in_date'])
                ->when(! empty($data['reservation_id']), fn ($q) => $q->whereKeyNot($data['reservation_id']))
                ->exists();

            if ($hasOtherBooking) {
                return back()->withInput()->withErrors(
                    'Kamar sudah dipesan pada rentang tanggal tersebut. Pilih kamar atau tanggal lain.'
                );
            }
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
            ->with('success', "Check-in berhasil. Kode transaksi: {$transaction->code}")
            ->with('clear_checkin_draft', true);
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
                'remaining_payment' => max($newFinalPrice + $transaction->totalCharge() - $transaction->totalPaid(), 0),
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
            'type' => ['required', 'in:dp,pelunasan,charge'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $transaction) {
            Payment::create([
                'transaction_id' => $transaction->id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'type' => $data['type'],
                'notes' => $data['type'] === Payment::TYPE_CHARGE ? ($data['notes'] ?? null) : null,
                'received_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $transaction->update([
                'remaining_payment' => max($transaction->totalBill() - $transaction->totalPaid(), 0),
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

        // Late checkout fee: 1x harga kamar bila lewat >3 jam dari jadwal.
        // Dicatat sekali saja (late_fee == 0 guard), tanpa mengubah status transaksi,
        // supaya sisa tagihan bisa dilunasi dulu sebelum check-out benar-benar selesai.
        DB::transaction(function () use ($transaction) {
            if ($transaction->late_fee == 0 && $transaction->isLateCheckout()) {
                $lateFee = (float) $transaction->room_price_per_night;

                $transaction->update([
                    'late_fee' => $lateFee,
                    'final_price' => (float) $transaction->final_price + $lateFee,
                ]);

                $transaction->update([
                    'remaining_payment' => max($transaction->totalBill() - $transaction->totalPaid(), 0),
                ]);
            }
        });

        $transaction->refresh();

        $remaining = max($transaction->totalBill() - $transaction->totalPaid(), 0);

        if ($remaining > 0) {
            $message = 'Transaksi belum lunas. Sisa bayar: Rp ' . number_format($remaining, 0, ',', '.') . '.';

            if ($transaction->late_fee > 0) {
                $message = 'Tamu terlambat check-out, dikenakan biaya tambahan Rp '
                    . number_format($transaction->late_fee, 0, ',', '.') . '. ' . $message;
            }

            return back()->withErrors($message . ' Lunaskan dulu sebelum check-out.');
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

    /**
     * Riwayat transaksi yang sudah selesai (checked_out).
     * Filter opsional berdasarkan rentang tanggal check-out; default semua data terbaru.
     */
    public function history(Request $request): View
    {
        $startDate = $request->date('start_date');
        $endDate = $request->date('end_date');

        $transactions = Transaction::with(['customer:id,name', 'room:id,room_number,room_type_id', 'room.roomType:id,name'])
            ->where('status', Transaction::STATUS_CHECKED_OUT)
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('check_out_date', [$startDate, $endDate]))
            ->orderByDesc('check_out_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.history', compact('transactions', 'startDate', 'endDate'));
    }

    /**
     * Detail satu transaksi selesai + timeline aktivitas yang menyentuhnya.
     */
    public function historyDetail(Transaction $transaction): View
    {
        abort_unless($transaction->status === Transaction::STATUS_CHECKED_OUT, 404);

        $transaction->load([
            'customer',
            'room.roomType',
            'payments.receiver:id,name,role',
            'stayExtensions.extendedBy:id,name,role',
            'roomTransfers.transferredBy:id,name,role',
            'roomTransfers.fromRoom:id,room_number',
            'roomTransfers.toRoom:id,room_number',
            'roomTransfers.transaction:id,code,customer_id',
            'roomTransfers.transaction.customer:id,name',
        ]);

        // Log kebersihan (room keeper) untuk kamar yang sama, dalam rentang menginap (opsi b).
        $roomLogs = RoomLog::with(['user:id,name,role', 'room:id,room_number'])
            ->where('room_id', $transaction->room_id)
            ->whereBetween('created_at', [
                $transaction->check_in_date->copy()->startOfDay(),
                $transaction->check_out_date->copy()->endOfDay(),
            ])
            ->orderBy('created_at')
            ->get();

        $timeline = $this->buildHistoryTimeline($transaction, $roomLogs);

        return view('transactions.history-detail', compact('transaction', 'timeline'));
    }

    /**
     * Gabungkan aktivitas transaksi + log kamar jadi satu timeline urut waktu.
     */
    private function buildHistoryTimeline(Transaction $transaction, $roomLogs): \Illuminate\Support\Collection
    {
        $items = collect();

        $push = function (?object $user, string $action, string $detail, $time, string $type) use (&$items) {
            $items->push([
                'user' => $user,
                'action' => $action,
                'detail' => $detail,
                'time' => $time,
                'type' => $type,
            ]);
        };

        // 1) Transaksi: check-in & check-out
        $push(
            $transaction->user,
            'Check-in tamu',
            ($transaction->customer?->name ?? '-') . ($transaction->room?->room_number ? " — Kamar {$transaction->room->room_number}" : ''),
            $transaction->created_at,
            'transaksi'
        );

        if ($transaction->status === Transaction::STATUS_CHECKED_OUT) {
            $push(
                $transaction->user,
                'Check-out tamu',
                ($transaction->customer?->name ?? '-') . ($transaction->room?->room_number ? " — Kamar {$transaction->room->room_number}" : ''),
                $transaction->updated_at,
                'transaksi'
            );
        }

        // 2) Pembayaran
        foreach ($transaction->payments as $p) {
            $label = match ($p->type) {
                'pelunasan' => 'Menerima pelunasan',
                'refund' => 'Memproses refund',
                'charge' => 'Menambah charge',
                default => 'Mencatat pembayaran (DP)',
            };

            $push(
                $p->receiver,
                $label,
                'Rp ' . number_format((float) $p->amount, 0, ',', '.') . ($p->notes ? ' — ' . $p->notes : ''),
                $p->paid_at,
                'pembayaran'
            );
        }

        // 3) Perpanjangan masa inap
        foreach ($transaction->stayExtensions as $e) {
            $push(
                $e->extendedBy,
                'Perpanjang masa inap',
                "+{$e->additional_days} malam (" . \Illuminate\Support\Carbon::parse($e->old_checkout_date)->format('d M Y') . ' → ' . \Illuminate\Support\Carbon::parse($e->new_checkout_date)->format('d M Y') . ')',
                $e->created_at,
                'perpanjangan'
            );
        }

        // 4) Pindah kamar
        foreach ($transaction->roomTransfers as $r) {
            $push(
                $r->transferredBy,
                'Pindah kamar',
                "{$r->fromRoom?->room_number} → {$r->toRoom?->room_number}" . ($r->reason ? " — {$r->reason}" : ''),
                $r->transferred_at,
                'transfer'
            );
        }

        // 5) Log kebersihan kamar (room keeper) — berdasarkan kamar & tanggal menginap
        foreach ($roomLogs as $log) {
            $label = match ($log->status_reported) {
                'clean' => 'Lapor kamar bersih',
                'dirty' => 'Lapor kamar kotor',
                'maintenance' => 'Lapor kamar rusak',
                default => 'Update status kamar',
            };

            $push(
                $log->user,
                $label,
                "Kamar {$log->room?->room_number}" . ($log->notes ? ' — ' . $log->notes : ''),
                $log->created_at,
                'kamar'
            );
        }

        return $items->sortBy('time')->values();
    }
}