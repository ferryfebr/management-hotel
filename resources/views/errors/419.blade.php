@extends('layouts.app')
@section('title', 'Sesi Berakhir')

@section('content')
<div class="max-w-lg mx-auto py-16">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
        <div class="text-6xl mb-4 font-bold text-red-500">419</div>
        <h1 class="text-xl font-semibold mb-2">Halaman Kedaluwarsa</h1>
        <p class="text-sm text-gray-500 mb-6">
            Sesi halaman Anda telah kedaluwarsa (biasanya karena terlalu lama tidak aktif).
            Silakan login kembali untuk melanjutkan.
        </p>
        <x-button variant="primary" href="{{ route('login') }}">Login Ulang</x-button>
    </div>
</div>
@endsection