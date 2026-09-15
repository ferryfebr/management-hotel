@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
<div class="max-w-xl mx-auto bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center gap-3 mb-5">
        <span class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-lg font-semibold flex-shrink-0">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
        <div>
            <h2 class="font-semibold">Profil Owner</h2>
            <p class="text-xs text-gray-500">Ubah nama/email (username) dan kata sandi Anda.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 text-sm">
        @csrf @method('PUT')

        <div>
            <label class="block mb-1">Nama</label>
            <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
        </div>

        <div>
            <label class="block mb-1">Email (login)</label>
            <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
        </div>

        <div class="border-t border-gray-100 pt-4 mt-4">
            <p class="font-semibold text-sm mb-3">Ganti Kata Sandi</p>
            <div class="space-y-4">
                <div>
                    <label class="block mb-1">Kata sandi saat ini <span class="text-red-500">*</span></label>
                    <input type="password" name="current_password" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    @error('current_password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block mb-1 text-gray-500">Kata sandi baru (isi untuk mengganti)</label>
                    <input type="password" name="password" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div>
                    <label class="block mb-1">Konfirmasi kata sandi baru</label>
                    <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
            </div>
        </div>

        <div class="pt-2">
            <x-button variant="primary" type="submit">Simpan Perubahan</x-button>
        </div>
    </form>
</div>
@endsection