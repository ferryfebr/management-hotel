@extends('layouts.app')
@section('title', 'Detail Pelanggan')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center gap-3 mb-3">
            <span class="w-14 h-14 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl font-semibold flex-shrink-0">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h2 class="font-semibold truncate">{{ $customer->name }}</h2>
                <p class="text-sm text-gray-500">{{ $customer->phone }} &middot; {{ $customer->visit_count }}x kunjungan</p>
            </div>
        </div>

        @if($customer->id_card_photo)
            <x-button variant="ghost" href="{{ $customer->id_card_photo_url }}" target="_blank">Lihat Foto KTP</x-button>
        @endif

        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-3 text-sm mt-4">
            @csrf @method('PUT')
            <div>
                <label class="block mb-1">Status</label>
                <select name="rating_status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="regular" @selected($customer->rating_status === 'regular')>Regular</option>
                    <option value="warning" @selected($customer->rating_status === 'warning')>Warning</option>
                    <option value="vip" @selected($customer->rating_status === 'vip')>VIP</option>
                    <option value="blacklisted" @selected($customer->rating_status === 'blacklisted')>Blacklisted</option>
                </select>
            </div>
            <div>
                <label class="block mb-1">Catatan</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2">{{ $customer->notes }}</textarea>
            </div>
            <x-button variant="primary" type="submit" block>Simpan</x-button>
        </form>

        @if(auth()->user()->isOwner())
            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="mt-3"
                  onsubmit="return confirm('Hapus data pelanggan {{ $customer->name }}? Data masih tersimpan untuk audit.')">
                @csrf @method('DELETE')
                <x-button variant="danger" type="submit" block>Hapus Pelanggan</x-button>
            </form>
        @endif
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm">Histori Menginap</div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kamar</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-in</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-out</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customer->transactions as $trx)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $trx->code }}</td>
                        <td class="px-4 py-3">{{ $trx->room?->room_number ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $trx->check_in_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $trx->check_out_date->format('d M Y') }}</td>
                        <td class="px-4 py-3"><x-badge status="{{ $trx->status }}" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Belum ada histori.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection