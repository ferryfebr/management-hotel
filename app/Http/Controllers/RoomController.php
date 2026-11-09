<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $request->merge(['room_number' => trim((string) $request->input('room_number'))]);

        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20', 'unique:rooms,room_number'],
        ], [
            'room_number.unique' => 'Nomor kamar sudah digunakan.',
        ]);

        $data['status'] = Room::STATUS_AVAILABLE;

        try {
            $room = Room::create($data);
        } catch (UniqueConstraintViolationException) {
            // Double submit: request kedua kalah balapan dengan request pertama.
            // Kalau nomor + jenisnya sama, anggap sukses (idempoten) supaya
            // user tidak melihat notif gagal padahal kamar sudah terbuat.
            $existing = Room::where('room_number', $data['room_number'])->first();

            if ($existing && (int) $existing->room_type_id === (int) $data['room_type_id']) {
                return back()->with('success', "Kamar {$data['room_number']} sudah terdaftar.");
            }

            return back()->withInput()->withErrors(['room_number' => 'Nomor kamar sudah digunakan.']);
        }

        ActivityLog::record('Tambah kamar', ActivityLog::CATEGORY_KAMAR, "Kamar {$room->room_number}");

        return back()->with('success', 'Kamar berhasil ditambahkan.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $request->merge(['room_number' => trim((string) $request->input('room_number'))]);

        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20', 'unique:rooms,room_number,'.$room->id],
        ], [
            'room_number.unique' => 'Nomor kamar sudah digunakan.',
        ]);

        try {
            $room->update($data);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['room_number' => 'Nomor kamar sudah digunakan.']);
        }

        ActivityLog::record('Ubah data kamar', ActivityLog::CATEGORY_KAMAR, "Kamar {$room->room_number}");

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

        $oldStatus = $room->status;
        $room->update($data);

        ActivityLog::record(
            'Ubah status kamar',
            ActivityLog::CATEGORY_KAMAR,
            "Kamar {$room->room_number}: {$oldStatus} → {$data['status']}"
        );

        return back()->with('success', 'Status kamar berhasil diperbarui.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->transactions()->exists()) {
            return back()->withErrors('Kamar tidak bisa dihapus karena memiliki histori transaksi.');
        }

        // room_logs (cascade) menghapus lognya, tapi room_transfers memakai
        // restrictOnDelete — cek dulu agar tidak berujung error FK 500.
        if ($room->transfersFrom()->exists() || $room->transfersTo()->exists()) {
            return back()->withErrors('Kamar tidak bisa dihapus karena memiliki histori pindah kamar.');
        }

        $roomNumber = $room->room_number;
        $room->delete();

        ActivityLog::record('Hapus kamar', ActivityLog::CATEGORY_KAMAR, "Kamar {$roomNumber}");

        return back()->with('success', 'Kamar berhasil dihapus.');
    }
}