@extends('layouts.app')
@section('title', 'Riwayat Transaksi')

@section('content')
<form method="GET" class="flex flex-wrap gap-3 items-end mb-4 text-sm">
    <div>
        <label class="block mb-1 text-xs text-gray-500">Dari Tanggal</label>
        <input type="date" name="start_date" value="{{ $startDate?->format('Y-m-d') }}"
               class="border border-gray-300 rounded-md px-3 py-2">
    </div>
    <div>
        <label class="block mb-1 text-xs text-gray-500">Sampai Tanggal</label>
        <input type="date" name="end_date" value="{{ $endDate?->format('Y-m-d') }}"
               class="border border-gray-300 rounded-md px-3 py-2">
    </div>
    <x-button variant="primary" type="submit">Filter</x-button>
    @if($startDate || $endDate)
        <x-button variant="secondary" href="{{ route('transactions.history') }}">Reset</x-button>
    @endif
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[720px]">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama Tamu</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kamar</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-in</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-out</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $trx)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $trx->code }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">{{ strtoupper(substr($trx->customer->name, 0, 1)) }}</span>
                            {{ $trx->customer->name }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $trx->room->room_number }} &middot; {{ $trx->room->roomType->name }}</td>
                    <td class="px-4 py-3">{{ $trx->check_in_date->format('d M Y') }}</td>
                    <td class="px-4 py-3">{{ $trx->check_out_date->format('d M Y') }}</td>
                    <td class="px-4 py-3"><x-badge status="{{ $trx->status }}" /></td>
                    <td class="px-4 py-3 text-right">
                        <x-button variant="ghost" href="{{ route('transactions.history.detail', $trx) }}">Detail</x-button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">
                        @if($startDate || $endDate)
                            Tidak ada transaksi selesai pada rentang tanggal ini.
                        @else
                            Belum ada transaksi yang selesai.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-4">{{ $transactions->links() }}</div>
@endsection