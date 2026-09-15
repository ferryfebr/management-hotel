@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'block' => false,
])

@php
    $variants = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-700',
        'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'ghost' => 'text-indigo-600 hover:bg-indigo-50',
        'dark' => 'bg-gray-900 text-white hover:bg-gray-800',
        'success' => 'bg-green-600 text-white hover:bg-green-700',
        'warning' => 'bg-yellow-500 text-white hover:bg-yellow-600',
    ];

    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-md font-medium text-sm transition-colors px-3 py-1.5 '
        . ($variants[$variant] ?? $variants['primary'])
        . ($block ? ' w-full' : '');
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif