@extends('layouts.app')
@section('title', 'Kelola Akun Pekerja')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Daftar akun --}}
    <div class="lg:col-span-2 space-y-3">
        @forelse($users as $u)
            <details class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3 hover:bg-gray-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-semibold flex-shrink-0">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $u->name }}</p>
                            <p class="text-xs text-gray-500">{{ $u->email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <x-badge status="{{ $u->role }}" />
                        @if($u->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Aktif</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">Nonaktif</span>
                        @endif
                        <span class="text-xs text-gray-400">&raquo;</span>
                    </div>
                </summary>

                <div class="border-t border-gray-100 px-4 py-4 bg-gray-50">
                    <h3 class="font-semibold text-sm mb-3">Edit akun</h3>
                    <form method="POST" action="{{ route('users.update', $u) }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        @csrf @method('PUT')
                        <div>
                            <label class="block mb-1">Nama</label>
                            <input type="text" name="name" value="{{ old('name', $u->name) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block mb-1">Email (login)</label>
                            <input type="email" name="email" value="{{ old('email', $u->email) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block mb-1">Peran</label>
                            <select name="role" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="resepsionis" @selected($u->role === 'resepsionis')>Resepsionis</option>
                                <option value="room_keeper" @selected($u->role === 'room_keeper')>Room Keeper</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-gray-500">Ganti kata sandi (opsional)</label>
                            <input type="password" name="password" placeholder="Kosongkan = tetap" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block mb-1">Konfirmasi kata sandi baru</label>
                            <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>
                        <div>
                            <label class="block mb-1">Status</label>
                            <select name="is_active" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="1" @selected($u->is_active)>Aktif</option>
                                <option value="0" @selected(!$u->is_active)>Nonaktif</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-button variant="primary" type="submit">Simpan Perubahan</x-button>
                        </div>
                    </form>

                    @if($u->is_active)
                    <form method="POST" action="{{ route('users.destroy', $u) }}"
                          onsubmit="return confirm('Nonaktifkan akun {{ $u->name }}?')" class="mt-3 border-t border-gray-200 pt-3">
                        @csrf @method('DELETE')
                        <x-button variant="danger" type="submit">Nonaktifkan Akun</x-button>
                    </form>
                    @endif
                </div>
            </details>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center text-gray-400 text-sm">
                Belum ada akun resepsionis / room keeper.
            </div>
        @endforelse

        <div class="mt-4">{{ $users->links() }}</div>
    </div>

    {{-- Form tambah akun --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 h-fit">
        <h2 class="font-semibold text-sm mb-3">Tambah Akun</h2>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block mb-1">Nama</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Email (login)</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Peran</label>
                <select name="role" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="resepsionis" @selected(old('role') === 'resepsionis')>Resepsionis</option>
                    <option value="room_keeper" @selected(old('role') === 'room_keeper')>Room Keeper</option>
                </select>
            </div>
            <div>
                <label class="block mb-1">Kata sandi</label>
                <input type="password" name="password" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Konfirmasi kata sandi</label>
                <input type="password" name="password_confirmation" required class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <x-button variant="primary" type="submit" block>Tambah Akun</x-button>
        </form>
    </div>
</div>
@endsection