@extends('layouts.app')
@section('title', 'Akses Ditolak')

@section('content')
<div class="max-w-lg mx-auto py-16">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
        <div class="text-6xl mb-4 font-bold text-red-500">403</div>
        <h1 class="text-xl font-semibold mb-2">Akses Ditolak</h1>
        <p class="text-sm text-gray-500 mb-6">
            Anda tidak memiliki izin untuk mengakses halaman ini.
            Halaman ini hanya untuk peran tertentu (mis. Owner).
        </p>
        @auth
            <div class="flex flex-wrap items-center justify-center gap-3">
                <x-button variant="primary" href="{{ route(auth()->user()->defaultRouteName()) }}">Kembali ke Halaman Saya</x-button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="ghost" type="submit">Keluar</x-button>
                </form>
            </div>
        @endauth
    </div>
</div>
@endsection