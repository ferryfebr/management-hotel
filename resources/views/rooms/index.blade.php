@extends('layouts.app')
@section('title', 'Kamar')

@section('content')
@php
$accentColor = [
    'available' => 'border-green-300',
    'occupied' => 'border-blue-300',
    'dirty' => 'border-yellow-300',
    'maintenance' => 'border-red-300',
];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($rooms as $room)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 {{ $accentColor[$room->status] ?? 'border-gray-200' }} p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="font-semibold">Kamar {{ $room->room_number }}</p>
                        <p class="text-xs text-gray-500">{{ $room->roomType->name }}</p>
                    </div>
                    <x-badge status="{{ $room->status }}" />
                </div>
                <form method="POST" action="{{ route('rooms.update-status', $room) }}" class="flex gap-2 mt-2">
                    @csrf @method('PATCH')
                    <select name="status" class="flex-1 border border-gray-300 rounded-md text-xs px-2 py-1.5">
                        @foreach(['available','occupied','dirty','maintenance'] as $s)
                            <option value="{{ $s }}" @selected($room->status === $s)>{{ \App\Models\Room::statusLabel($s) }}</option>
                        @endforeach
                    </select>
                    <x-button variant="dark" type="submit">Ubah</x-button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 h-fit">
        <h2 class="font-semibold text-sm mb-3">Tambah Kamar</h2>
        <form method="POST" action="{{ route('rooms.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block mb-1">Jenis Kamar</label>
                <select name="room_type_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    @foreach($roomTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1">Nomor Kamar</label>
                <input type="text" name="room_number" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <x-button variant="primary" type="submit" block>Simpan</x-button>
        </form>
    </div>
</div>
@endsection