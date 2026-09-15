<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoomKeeperController extends Controller
{
    public function index(): View
    {
        $rooms = Room::with('roomType')
            ->orderByRaw("FIELD(status, 'dirty', 'maintenance', 'occupied', 'available')")
            ->orderBy('room_number')
            ->get();

        return view('room-keeper.index', compact('rooms'));
    }

    /**
     * Update status kebersihan (dirty -> clean/available) dan catat log.
     */
    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'status_reported' => ['required', 'in:dirty,clean,maintenance'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $room) {
            RoomLog::create([
                'room_id' => $room->id,
                'user_id' => auth()->id(),
                'status_reported' => $data['status_reported'],
                'notes' => $data['notes'] ?? null,
            ]);

            // "clean" pada log berarti kamar siap dipakai lagi -> available
            $newRoomStatus = $data['status_reported'] === 'clean'
                ? Room::STATUS_AVAILABLE
                : $data['status_reported'];

            $room->update(['status' => $newRoomStatus]);
        });

        return back()->with('success', 'Status kamar berhasil diperbarui.');
    }
}