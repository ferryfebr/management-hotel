@extends('layouts.app')
@section('title', 'Laporan')

@section('content')
<form method="GET" class="flex flex-wrap gap-4 items-end mb-6 text-sm">
    <div class="flex flex-col sm:flex-row gap-4 flex-wrap">
        <div>
            <label class="block mb-1">Dari Tanggal</label>
            <input type="date" name="start_date" value="{{ \Illuminate\Support\Carbon::parse($startDate)->format('Y-m-d') }}"
                   class="border border-gray-300 rounded-md px-3 py-2">
        </div>
        <div>
            <label class="block mb-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="{{ \Illuminate\Support\Carbon::parse($endDate)->format('Y-m-d') }}"
                   class="border border-gray-300 rounded-md px-3 py-2">
        </div>
    </div>
    <x-button variant="primary" type="submit">Filter</x-button>
</form>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </span>
        <p class="text-2xl font-bold">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total Pendapatan</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $checkOuts }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Jumlah Check-out</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m0-18 3 3m-3-3-3 3M3 12h18m0 0-3-3m3 3-3 3"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $totalRoomNights }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total Malam Terjual</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm">Rincian Pembayaran</div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[720px]">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tanggal</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode Transaksi</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tamu</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kamar</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Jenis</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Metode</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $payment->paid_at->format('d M Y H.i') }} WIT</td>
                    <td class="px-4 py-3">{{ $payment->transaction?->code ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">{{ strtoupper(substr($payment->transaction?->customer?->name ?? '-', 0, 1)) }}</span>
                            {{ $payment->transaction?->customer?->name ?? 'Pelanggan dihapus' }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $payment->transaction?->room?->room_number ?? '-' }}</td>
                    <td class="px-4 py-3"><x-badge status="{{ $payment->type }}" /></td>
                    <td class="px-4 py-3 uppercase text-xs">{{ $payment->payment_method }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Tidak ada data pembayaran pada rentang ini.</td></tr>
            @endforelse
        </tbody>
        @if($payments->isNotEmpty())
            <tfoot>
                <tr class="border-t border-gray-200 font-semibold bg-gray-50">
                    <td colspan="6" class="px-4 py-3 text-right">Total</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
    </div>
</div>
@endsection