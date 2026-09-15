@extends('layouts.app')
@section('title', 'Tamu Aktif')

@section('content')
<form method="GET" class="flex gap-2 mb-4">
    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari nomor transaksi atau nama tamu..."
           class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm">
    <x-button variant="primary" type="submit">Cari</x-button>
    @if(!empty($search))
        <x-button variant="secondary" href="{{ route('transactions.active') }}">Reset</x-button>
    @endif
</form>
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

            <div class="border-t border-gray-200 px-4 py-4 bg-gray-50" x-data="{ activeTab: 'payment' }">

                {{-- Tab pill --}}
                <div class="flex flex-wrap gap-2 mb-4">
                    <button type="button" @click="activeTab = 'payment'"
                            :class="activeTab === 'payment' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors">
                        Tambah Pembayaran
                    </button>
                    <button type="button" @click="activeTab = 'extend'"
                            :class="activeTab === 'extend' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors">
                        Perpanjang
                    </button>
                    <button type="button" @click="activeTab = 'transfer'"
                            :class="activeTab === 'transfer' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors">
                        Pindah Kamar
                    </button>
                    <button type="button" @click="activeTab = 'checkout'"
                            :class="activeTab === 'checkout' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 border border-red-200'"
                            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                        Check-out
                    </button>
                </div>

                {{-- Tambah Pembayaran --}}
                <form method="POST" action="{{ route('transactions.add-payment', $trx) }}" x-show="activeTab === 'payment'" x-data="{ type: 'dp' }"
                      class="space-y-3 bg-white border border-gray-100 rounded-lg p-4">
                    @csrf
                    <div>
                        <label class="block font-medium text-sm text-gray-700 mb-1">Nominal</label>
                        <input type="text" inputmode="numeric" name="amount" value="0" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-gray-700 mb-1">Metode Pembayaran</label>
                        <select name="payment_method" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="cash">Tunai</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="debit">Kartu Debit</option>
                            <option value="kartu_kredit">Kartu Kredit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-gray-700 mb-1">Jenis Pembayaran</label>
                        <select name="type" required x-model="type" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="dp">DP Tambahan</option>
                            <option value="pelunasan">Pelunasan</option>
                            <option value="charge">Charge (Biaya Tambahan)</option>
                        </select>
                    </div>
                    <div x-show="type === 'charge'">
                        <label class="block font-medium text-sm text-gray-700 mb-1">Keterangan (opsional)</label>
                        <textarea name="notes" rows="2" placeholder="mis. tambah jam 3 jam" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
                    </div>
                    <x-button variant="primary" type="submit" block>Catat Pembayaran</x-button>
                </form>

                {{-- Perpanjang --}}
                <form method="POST" action="{{ route('transactions.extend', $trx) }}" x-show="activeTab === 'extend'"
                      class="space-y-3 bg-white border border-gray-100 rounded-lg p-4">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block font-medium text-sm text-gray-700 mb-1">Tanggal Check-out Baru</label>
                        <input type="date" name="new_checkout_date" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                               min="{{ $trx->check_out_date->addDay()->format('Y-m-d') }}">
                    </div>
                    <x-button variant="primary" type="submit" block>Perpanjang</x-button>
                </form>

                {{-- Pindah Kamar --}}
                <form method="POST" action="{{ route('transactions.transfer', $trx) }}" x-show="activeTab === 'transfer'"
                      class="space-y-3 bg-white border border-gray-100 rounded-lg p-4">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block font-medium text-sm text-gray-700 mb-1">Kamar Tujuan</label>
                        <select name="to_room_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Pilih kamar tujuan</option>
                            @foreach($availableRooms as $r)
                                <option value="{{ $r->id }}">{{ $r->room_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-gray-700 mb-1">Alasan Pindah</label>
                        <input type="text" name="reason" placeholder="Alasan pindah" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <x-button variant="primary" type="submit" block>Pindahkan</x-button>
                </form>

                {{-- Check-out --}}
                <form method="POST" action="{{ route('transactions.checkout', $trx) }}" x-show="activeTab === 'checkout'"
                      class="space-y-3 bg-white border border-gray-100 rounded-lg p-4"
                      onsubmit="return confirm('Selesaikan check-out untuk {{ $trx->customer->name }}?')">
                    @csrf @method('PATCH')
                    @php $charges = $trx->payments->where('type', \App\Models\Payment::TYPE_CHARGE); @endphp
                    @if($charges->isNotEmpty())
                        <ul class="text-xs text-gray-500 list-disc list-inside space-y-0.5">
                            @foreach($charges as $charge)
                                <li>Charge Rp {{ number_format($charge->amount, 0, ',', '.') }}{{ $charge->notes ? ' — ' . $charge->notes : '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if($trx->late_fee > 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                            Biaya keterlambatan check-out: Rp {{ number_format($trx->late_fee, 0, ',', '.') }} (1x harga kamar).
                        </div>
                    @elseif($trx->isLateCheckout())
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                            Tamu melewati batas check-out (12:00 + 3 jam). Akan dikenakan biaya keterlambatan
                            Rp {{ number_format($trx->potentialLateFee(), 0, ',', '.') }} (1x harga kamar) saat check-out.
                        </div>
                    @endif
                    @php
                        $pendingLateFee = $trx->late_fee > 0 ? 0 : $trx->potentialLateFee();
                        $effectiveRemaining = max($trx->totalBill() + $pendingLateFee - $trx->totalPaid(), 0);
                    @endphp
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                        Sisa yang harus dibayar tamu: Rp {{ number_format($effectiveRemaining, 0, ',', '.') }}
                    </div>
                    @if($effectiveRemaining > 0)
                        <div>
                            <label class="block font-medium text-sm text-gray-700 mb-1">Metode Pembayaran</label>
                            <select name="payment_method" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="qris">QRIS</option>
                                <option value="debit">Kartu Debit</option>
                                <option value="kartu_kredit">Kartu Kredit</option>
                            </select>
                        </div>
                    @endif
                    <x-button variant="danger" type="submit" block>Selesaikan Check-out</x-button>
                </form>
            </div>
        </details>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center text-gray-400 text-sm">
            @if(!empty($search))
                Tidak ada tamu aktif yang cocok dengan pencarian "{{ $search }}".
            @else
                Belum ada tamu yang sedang menginap.
            @endif
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