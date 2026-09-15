@extends('layouts.app')
@section('title', 'Jenis Kamar')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[480px]">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Harga/malam</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Jumlah Kamar</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($roomTypes as $type)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $type->name }}</td>
                        <td class="px-4 py-3">Rp {{ number_format($type->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $type->rooms_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('room-types.destroy', $type) }}"
                                  onsubmit="return confirm('Hapus jenis kamar ini?')">
                                @csrf @method('DELETE')
                                <x-button variant="danger" type="submit">Hapus</x-button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 h-fit">
        <h2 class="font-semibold text-sm mb-3">Tambah Jenis Kamar</h2>
        <form method="POST" action="{{ route('room-types.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block mb-1">Nama</label>
                <input type="text" name="name" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Nominal Harga / malam (Rp)</label>
                <input type="text" name="price" id="roomtype_price" inputmode="numeric" placeholder="0" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Deskripsi</label>
                <textarea name="description" class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
            </div>
            <x-button variant="primary" type="submit" block>Simpan</x-button>
        </form>
    </div>
</div>

<script>
(function () {
    var price = document.getElementById('roomtype_price');
    if (!price) return;

    function format(n) {
        return String(n).replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    price.addEventListener('input', function () { price.value = format(price.value); });
    var form = price.closest('form');
    if (form) form.addEventListener('submit', function () { price.value = price.value.replace(/\./g, ''); });
})();
</script>
@endsection