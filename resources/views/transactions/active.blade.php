@extends('layouts.app')
@section('title', 'Tamu Aktif')

@section('content')
<div class="space-y-4">
    @forelse($transactions as $trx)
        <details class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <summary class="cursor-pointer list-none px-4 py-3 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center hover:bg-gray-50">
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-semibold flex-shrink-0">{{ strtoupper(substr($trx->customer->name, 0, 1)) }}</span>
                    <div>
                        <p class="font-semibold">{{ $trx->customer->name }}</p>
                        <p class="text-xs text-gray-500">{{ $trx->code }} &middot; <x-badge status="checked_in" /></p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6 text-sm">
                    <div>
                        <p class="text-xs text-gray-500">Kamar</p>
                        <p>{{ $trx->room->room_number }} &middot; {{ $trx->room->roomType->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Check-in</p>
                        <p>{{ $trx->check_in_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Check-out</p>
                        <p>{{ $trx->check_out_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Sisa Bayar</p>
                        <p @class(['font-semibold', 'text-red-600' => $trx->remaining_payment > 0, 'text-green-600' => $trx->remaining_payment <= 0])>
                            Rp {{ number_format($trx->remaining_payment, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
                <span class="text-xs text-blue-600 whitespace-nowrap">Kelola &raquo;</span>
            </summary>

            <div class="border-t border-gray-200 px-4 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm bg-gray-50">

                {{-- Perpanjang --}}
                <form method="POST" action="{{ route('transactions.extend', $trx) }}" class="space-y-2 bg-white border border-gray-100 rounded-lg p-3">
                    @csrf @method('PATCH')
                    <p class="font-semibold text-xs text-gray-500">Perpanjang Menginap</p>
                    <input type="date" name="new_checkout_date" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs"
                           min="{{ $trx->check_out_date->addDay()->format('Y-m-d') }}">
                    <x-button variant="primary" type="submit" block>Perpanjang</x-button>
                </form>

                {{-- Pindah Kamar --}}
                <form method="POST" action="{{ route('transactions.transfer', $trx) }}" class="space-y-2 bg-white border border-gray-100 rounded-lg p-3">
                    @csrf @method('PATCH')
                    <p class="font-semibold text-xs text-gray-500">Pindah Kamar</p>
                    <select name="to_room_id" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs">
                        <option value="">Pilih kamar tujuan</option>
                        @foreach($availableRooms as $r)
                            <option value="{{ $r->id }}">{{ $r->room_number }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="reason" placeholder="Alasan pindah" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs">
                    <x-button variant="primary" type="submit" block>Pindahkan</x-button>
                </form>

                {{-- Tambah Pembayaran --}}
                <form method="POST" action="{{ route('transactions.add-payment', $trx) }}" class="space-y-2 bg-white border border-gray-100 rounded-lg p-3">
                    @csrf
                    <p class="font-semibold text-xs text-gray-500">Tambah Pembayaran</p>
                    <input type="number" name="amount" min="1" value="{{ $trx->remaining_payment }}" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs">
                    <select name="payment_method" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs">
                        <option value="cash">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                        <option value="debit">Kartu Debit</option>
                        <option value="kartu_kredit">Kartu Kredit</option>
                    </select>
                    <select name="type" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs">
                        <option value="dp">DP Tambahan</option>
                        <option value="pelunasan">Pelunasan</option>
                    </select>
                    <x-button variant="primary" type="submit" block>Catat Pembayaran</x-button>
                </form>

                {{-- Check-out --}}
                <form method="POST" action="{{ route('transactions.checkout', $trx) }}" class="space-y-2 bg-white border border-gray-100 rounded-lg p-3"
                      onsubmit="return confirm('Selesaikan check-out untuk {{ $trx->customer->name }}?')">
                    @csrf @method('PATCH')
                    <p class="font-semibold text-xs text-gray-500">Check-out</p>
                    <p class="text-xs text-gray-500">
                        Total: Rp {{ number_format($trx->final_price, 0, ',', '.') }}<br>
                        Sudah dibayar: Rp {{ number_format($trx->totalPaid(), 0, ',', '.') }}
                    </p>
                    @if($trx->remaining_payment > 0)
                        <select name="payment_method" required class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs">
                            <option value="cash">Tunai</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="debit">Kartu Debit</option>
                            <option value="kartu_kredit">Kartu Kredit</option>
                        </select>
                    @endif
                    <x-button variant="danger" type="submit" block>Selesaikan Check-out</x-button>
                </form>
            </div>
        </details>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center text-gray-400 text-sm">
            Belum ada tamu yang sedang menginap.
        </div>
    @endforelse
</div>

<script>
(function () {
    function formatRupiah(n) {
        return String(n).replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    var amtInputs = document.querySelectorAll('input[name="amount"]');
    amtInputs.forEach(function (el) {
        el.value = formatRupiah(el.value);
        el.addEventListener('input', function () { el.value = formatRupiah(el.value); });
        var form = el.closest('form');
        if (form) form.addEventListener('submit', function () { el.value = el.value.replace(/\./g, ''); });
    });

    // ==== Realtime polling (refresh otomatis tiap 15 dtk bila idle) ====
    setTimeout(function tick() {
        var a = document.activeElement;
        var typing = a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT');
        if (!typing && !document.hidden) location.reload();
        setTimeout(tick, 15000);
    }, 15000);
})();
</script>
@endsection