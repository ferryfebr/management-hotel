<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\RoomType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('room_types', 'name')],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ], [
            'name.unique' => 'Jenis kamar dengan nama ini sudah ada.',
        ]);

        try {
            $roomType = RoomType::create($data);
        } catch (UniqueConstraintViolationException) {
            // Double submit: request kedua kalah balapan. Anggap sukses kalau
            // nama yang sama sudah tersimpan, supaya tidak muncul notif gagal.
            if (RoomType::where('name', $data['name'])->exists()) {
                return back()->with('success', "Jenis kamar {$data['name']} sudah terdaftar.");
            }

            return back()->withInput()->withErrors(['name' => 'Jenis kamar dengan nama ini sudah ada.']);
        }

        ActivityLog::record('Tambah jenis kamar', ActivityLog::CATEGORY_JENIS_KAMAR, $roomType->name);

        return back()->with('success', 'Jenis kamar berhasil ditambahkan.');
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('room_types', 'name')->ignore($roomType->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ], [
            'name.unique' => 'Jenis kamar dengan nama ini sudah ada.',
        ]);

        try {
            $roomType->update($data);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['name' => 'Jenis kamar dengan nama ini sudah ada.']);
        }

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