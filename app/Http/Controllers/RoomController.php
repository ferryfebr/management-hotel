<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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

        $room = Room::create($data);

        ActivityLog::record('Tambah kamar', ActivityLog::CATEGORY_KAMAR, "Kamar {$room->room_number}");

        return back()->with('success', 'Kamar berhasil ditambahkan.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20', 'unique:rooms,room_number,' . $room->id],
        ]);

        $room->update($data);

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

        $roomNumber = $room->room_number;
        $room->delete();

        ActivityLog::record('Hapus kamar', ActivityLog::CATEGORY_KAMAR, "Kamar {$roomNumber}");

        return back()->with('success', 'Kamar berhasil dihapus.');
    }
}