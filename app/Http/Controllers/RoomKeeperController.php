<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class RoomKeeperController extends Controller
{
    public function index(): View
    {
        $rooms = Room::with(['roomType', 'roomLogs' => fn ($q) => $q->whereNotNull('proof_photo')->latest()->limit(1)])
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
            'proof_photo' => [
                'image',
                'max:4096',
                // Wajib untuk clean & maintenance, opsional untuk dirty.
                in_array($request->input('status_reported'), ['clean', 'maintenance'], true) ? 'required' : 'nullable',
            ],
        ], [
            'proof_photo.required' => 'Foto bukti wajib diupload untuk status ini.',
            'proof_photo.image' => 'Foto bukti harus berupa gambar.',
            'proof_photo.max' => 'Ukuran foto bukti maksimal 4MB.',
        ]);

        DB::transaction(function () use ($data, $room, $request) {
            $proofPath = null;

            if ($request->hasFile('proof_photo')) {
                // Kompresi agresif: maks lebar 800px, kualitas 60%.
                // Foto bukti kerja, bukan dokumen identitas (hemat storage 1GB).
                $manager = new ImageManager(new Driver());
                $image = $manager->read($request->file('proof_photo'));
                $image->scaleDown(800);
                $encoded = $image->toJpeg(60);
                $proofPath = 'room-proofs/' . uniqid() . '.jpg';
                Storage::disk('private')->put($proofPath, (string) $encoded);
            }

            RoomLog::create([
                'room_id' => $room->id,
                'user_id' => auth()->id(),
                'status_reported' => $data['status_reported'],
                'notes' => $data['notes'] ?? null,
                'proof_photo' => $proofPath,
            ]);

            // "clean" pada log berarti kamar siap dipakai lagi -> available
            $newRoomStatus = $data['status_reported'] === 'clean'
                ? Room::STATUS_AVAILABLE
                : $data['status_reported'];

            $room->update(['status' => $newRoomStatus]);
        });

        return back()->with('success', 'Status kamar berhasil diperbarui.');
    }

    /**
     * Streaming foto bukti kerja dari disk private (bukan URL publik).
     * Bisa diakses owner, resepsionis, dan room keeper.
     */
    public function proofPhoto(RoomLog $roomLog): StreamedResponse
    {
        abort_unless($roomLog->proof_photo, 404);

        return Storage::disk('private')->response($roomLog->proof_photo);
    }
}