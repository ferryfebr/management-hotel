@extends('layouts.app')
@section('title', 'Detail Riwayat Transaksi')

@section('content')
@php
    $categoryMeta = [
        'transaksi' => ['label' => 'Transaksi', 'dot' => 'bg-blue-500', 'badge' => 'bg-blue-100 text-blue-700'],
        'pembayaran' => ['label' => 'Pembayaran', 'dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700'],
        'perpanjangan' => ['label' => 'Perpanjangan', 'dot' => 'bg-purple-500', 'badge' => 'bg-purple-100 text-purple-700'],
        'transfer' => ['label' => 'Pindah Kamar', 'dot' => 'bg-cyan-500', 'badge' => 'bg-cyan-100 text-cyan-700'],
        'kamar' => ['label' => 'Kamar', 'dot' => 'bg-yellow-500', 'badge' => 'bg-yellow-100 text-yellow-700'],
    ];
    $meta = fn ($type) => $categoryMeta[$type] ?? ['label' => ucfirst(str_replace('_', '', $type)), 'dot' => 'bg-gray-400', 'badge' => 'bg-gray-100 text-gray-700'];
@endphp

<div class="mb-4">
    <x-button variant="secondary" href="{{ route('transactions.history') }}">&larr; Kembali ke Riwayat</x-button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div class="flex items-center gap-3">
            <span class="w-14 h-14 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl font-semibold flex-shrink-0">{{ strtoupper(substr($transaction->customer?->name ?? '-', 0, 1)) }}</span>
            <div>
                <h2 class="font-semibold text-lg">{{ $transaction->customer?->name ?? 'Pelanggan dihapus' }}</h2>
                <p class="text-xs text-gray-500">{{ $transaction->code }} &middot; <x-badge status="{{ $transaction->status }}" /></p>
            </div>
        </div>
        @if($transaction->customer?->id_card_photo)
            <a href="{{ $transaction->customer->id_card_photo_url }}" target="_blank" class="text-xs text-indigo-600 hover:underline">Lihat Foto KTP</a>
        @else
            <span class="text-xs text-gray-400">Foto KTP sudah tidak tersedia</span>
        @endif
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mt-5">
        <div>
            <p class="text-xs text-gray-500">Kamar</p>
            <p class="font-medium">{{ $transaction->room?->room_number ?? '-' }} &middot; {{ $transaction->room?->roomType?->name ?? '-' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Check-in</p>
            <p class="font-medium">{{ $transaction->check_in_date->format('d M Y') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Check-out</p>
            <p class="font-medium">{{ $transaction->check_out_date->format('d M Y') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Jumlah Malam</p>
            <p class="font-medium">{{ $transaction->total_days }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm mt-5 border-t border-gray-100 pt-4">
        <div>
            <p class="text-xs text-gray-500">Total Tagihan</p>
            <p class="font-medium">Rp {{ number_format($transaction->totalBill(), 0, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Sudah Dibayar</p>
            <p class="font-medium text-green-600">Rp {{ number_format($transaction->totalPaid(), 0, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Charge</p>
            <p class="font-medium">Rp {{ number_format($transaction->totalCharge(), 0, ',', '.') }}</p>
        </div>
    </div>
</div>

<div class="mb-3">
    <p class="text-sm font-semibold text-gray-700">Aktivitas Transaksi</p>
    <p class="text-xs text-gray-500">Semua aksi owner, resepsionis &amp; room keeper yang tercatat pada transaksi ini.</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100">
    @forelse($timeline as $a)
        @php $m = $meta($a['type']); @endphp
        <div class="flex items-start gap-3 px-4 py-3">
            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">
                {{ strtoupper(substr(optional($a['user'])->name, 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="font-medium text-sm">{{ $a['user']?->name ?? 'Sistem' }}</p>
                    <span class="text-xs text-gray-400">{{ $a['user'] ? \App\Models\User::roleLabel($a['user']->role) : '' }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $m['badge'] }}">{{ $m['label'] }}</span>
                </div>
                <p class="text-sm text-gray-700 mt-0.5">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ $m['dot'] }}"></span>
                        {{ $a['action'] }}
                    </span>
                </p>
                @if(!empty($a['detail']))
                    <p class="text-xs text-gray-500 mt-0.5">{{ $a['detail'] }}</p>
                @endif
            </div>
            <span class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                {{ ($a['time'] instanceof \Illuminate\Support\Carbon ? $a['time'] : \Illuminate\Support\Carbon::parse($a['time']))->format('d M Y H.i') }} WIT
            </span>
        </div>
    @empty
        <div class="px-4 py-6 text-center text-gray-400 text-sm">Belum ada aktivitas tercatat.</div>
    @endforelse
</div>
@endsection