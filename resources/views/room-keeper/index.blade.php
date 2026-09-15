@extends('layouts.app')
@section('title', 'Status Kamar')

@section('content')
@php
$accentColor = [
    'available' => 'border-green-300',
    'occupied' => 'border-blue-300',
    'dirty' => 'border-yellow-300',
    'maintenance' => 'border-red-300',
];
@endphp

<div class="max-w-2xl mb-4">
    <input type="text" id="roomSearch" placeholder="Cari nomor kamar..." autocomplete="off"
           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
    @foreach($rooms as $room)
        <div data-room-number="{{ $room->room_number }}" class="room-card bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 {{ $accentColor[$room->status] ?? 'border-gray-200' }} p-4">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-semibold text-lg">Kamar {{ $room->room_number }}</p>
                    <p class="text-xs text-gray-500">{{ $room->roomType->name }}</p>
                </div>
                <x-badge status="{{ $room->status }}" />
            </div>

            <form method="POST" action="{{ route('room-keeper.update-status', $room) }}" class="space-y-2">
                @csrf @method('PATCH')
                <div class="grid grid-cols-3 gap-2">
                    <x-button variant="success" type="submit" name="status_reported" value="clean">✓ Bersih</x-button>
                    <x-button variant="warning" type="submit" name="status_reported" value="dirty">Kotor</x-button>
                    <x-button variant="danger" type="submit" name="status_reported" value="maintenance">Rusak</x-button>
                </div>
                <textarea name="notes" rows="2" placeholder="Catatan kerusakan (opsional)"
                          class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs"></textarea>
            </form>
        </div>
    @endforeach
</div>
<script>
(function () {
    var search = document.getElementById('roomSearch');
    var cards = document.querySelectorAll('.room-card');
    if (search && cards.length) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            cards.forEach(function (card) {
                var no = (card.getAttribute('data-room-number') || '').toLowerCase();
                card.style.display = (!q || no.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
})();
setTimeout(function tick(){ var a=document.activeElement,typing=a&&(a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.tagName==='SELECT'); if(!typing&&!document.hidden) location.reload(); setTimeout(tick,15000); },15000);
</script>
@endsection