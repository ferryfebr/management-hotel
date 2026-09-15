@props([
    'status' => '',
    'color' => null, // optional override
])

@php
    $map = [
        // hijau
        'available' => 'bg-green-100 text-green-700',
        'checked_out' => 'bg-green-100 text-green-700',
        'regular' => 'bg-green-100 text-green-700',
        'pelunasan' => 'bg-green-100 text-green-700',
        // biru
        'occupied' => 'bg-blue-100 text-blue-700',
        'checked_in' => 'bg-blue-100 text-blue-700',
        'reserved' => 'bg-blue-100 text-blue-700',
        'dp' => 'bg-blue-100 text-blue-700',
        // kuning
        'dirty' => 'bg-yellow-100 text-yellow-700',
        'pending' => 'bg-yellow-100 text-yellow-700',
        'warning' => 'bg-yellow-100 text-yellow-700',
        // merah
        'maintenance' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'no_show' => 'bg-red-100 text-red-700',
        'blacklisted' => 'bg-red-100 text-red-700',
        // ungu
        'vip' => 'bg-purple-100 text-purple-700',
    ];

    $classes = $color ?? ($map[strtolower($status)] ?? 'bg-gray-100 text-gray-700');

    $labels = [
        'available' => 'Tersedia',
        'occupied' => 'Terisi',
        'dirty' => 'Kotor',
        'maintenance' => 'Maintenance',
        'reserved' => 'Reservasi',
        'checked_in' => 'Check-in',
        'checked_out' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'no_show' => 'Tidak datang',
        'regular' => 'Regular',
        'vip' => 'VIP',
        'warning' => 'Warning',
        'blacklisted' => 'Blacklist',
        'pending' => 'Pending',
        'dp' => 'DP',
        'pelunasan' => 'Pelunasan',
    ];
    $label = $labels[strtolower($status)] ?? ucfirst(str_replace('_', ' ', strtolower($status)));
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $classes }}">
    {{ $slot->isEmpty() ? $label : $slot }}
</span>