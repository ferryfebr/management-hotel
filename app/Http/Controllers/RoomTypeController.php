<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::withCount('rooms')->orderBy('name')->get();

        return view('room-types.index', compact('roomTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $roomType = RoomType::create($data);

        ActivityLog::record('Tambah jenis kamar', ActivityLog::CATEGORY_JENIS_KAMAR, $roomType->name);

        return back()->with('success', 'Jenis kamar berhasil ditambahkan.');
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $roomType->update($data);

        ActivityLog::record('Ubah jenis kamar', ActivityLog::CATEGORY_JENIS_KAMAR, $roomType->name);

        return back()->with('success', 'Jenis kamar berhasil diperbarui.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        if ($roomType->rooms()->exists()) {
            return back()->withErrors('Jenis kamar tidak bisa dihapus karena masih memiliki kamar.');
        }

        $name = $roomType->name;
        $roomType->delete();

        ActivityLog::record('Hapus jenis kamar', ActivityLog::CATEGORY_JENIS_KAMAR, $name);

        return back()->with('success', 'Jenis kamar berhasil dihapus.');
    }
}