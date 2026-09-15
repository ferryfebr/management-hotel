@extends('layouts.app')
@section('title', 'Pelanggan')

@section('content')
<form method="GET" class="mb-4">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, telepon, atau no. KTP..."
           class="border border-gray-300 rounded-md px-3 py-2 text-sm w-full max-w-md">
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[520px]">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Telepon</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kunjungan</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($customers as $customer)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                            {{ $customer->name }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $customer->phone }}</td>
                    <td class="px-4 py-3">{{ $customer->visit_count }}x</td>
                    <td class="px-4 py-3"><x-badge status="{{ $customer->rating_status }}" /></td>
                    <td class="px-4 py-3 text-right">
                        <x-button variant="ghost" href="{{ route('customers.show', $customer) }}">Detail</x-button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>

<div class="mt-4">{{ $customers->links() }}</div>
@endsection