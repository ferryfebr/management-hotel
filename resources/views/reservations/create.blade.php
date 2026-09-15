@extends('layouts.app')
@section('title', 'Reservasi Baru')

@section('content')
<div class="bg-white rounded-lg shadow p-6 max-w-xl">
    <form method="POST" action="{{ route('reservations.store') }}" class="space-y-4 text-sm">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="relative">
                <label class="block mb-1">Nama Tamu</label>
                <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" placeholder="Mulai ketik nama..." autocomplete="off" required class="w-full border rounded px-3 py-2">
                <ul id="customer_results" class="hidden absolute z-20 w-full bg-white border rounded shadow-lg mt-1 text-xs max-h-48 overflow-y-auto"></ul>
            </div>
            <div>
                <label class="block mb-1">No. Telepon</label>
                <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1">Kamar</label>
            <select name="room_id" required class="w-full border rounded px-3 py-2">
                @foreach($roomTypes as $type)
                    <optgroup label="{{ $type->name }} - Rp {{ number_format($type->price, 0, ',', '.') }}">
                        @foreach($type->rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>Kamar {{ $room->room_number }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Tanggal Check-in</label>
                <input type="date" name="check_in_date" value="{{ old('check_in_date') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Tanggal Check-out</label>
                <input type="date" name="check_out_date" value="{{ old('check_out_date') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <x-button variant="primary" type="submit" block>Buat Reservasi</x-button>
    </form>
</div>

<script>
(function () {
    var nameInput = document.getElementById('customer_name');
    var phoneInput = document.getElementById('customer_phone');
    var results = document.getElementById('customer_results');
    var searchUrl = '{{ route("customers.search") }}';
    var timer = null;

    function hide() { results.classList.add('hidden'); results.innerHTML = ''; }

    function render(items) {
        results.innerHTML = '';
        if (!items.length) return hide();
        items.forEach(function (c) {
            var li = document.createElement('li');
            li.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer border-b last:border-b-0';
            li.innerHTML = '<div class="font-medium">' + c.name + ' <span class="text-gray-400">(' + c.visit_count + 'x)</span></div>' +
                '<div class="text-gray-500">' + (c.phone || '-') + '</div>';
            li.addEventListener('click', function () {
                nameInput.value = c.name;
                phoneInput.value = c.phone || '';
                hide();
            });
            results.appendChild(li);
        });
        results.classList.remove('hidden');
    }

    nameInput.addEventListener('input', function () {
        clearTimeout(timer);
        var v = nameInput.value.trim();
        if (v.length < 2) { hide(); return; }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { hide(); });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!nameInput.contains(e.target) && !results.contains(e.target)) hide();
    });
})();
</script>
@endsection