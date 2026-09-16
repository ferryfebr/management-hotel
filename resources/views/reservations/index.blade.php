@extends('layouts.app')
@section('title', 'Reservasi')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
    <p class="text-sm text-gray-500">Daftar reservasi yang akan datang</p>
    <x-button variant="primary" href="{{ route('reservations.create') }}">+ Reservasi Baru</x-button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[640px]">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tamu</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kamar</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-in</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-out</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($reservations as $r)
    @php $isLate = $r->check_in_date->isPast(); @endphp
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $r->code }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">{{ strtoupper(substr($r->customer?->name ?? '-', 0, 1)) }}</span>
                            {{ $r->customer?->name ?? 'Pelanggan dihapus' }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $r->room?->room_number ?? '-' }}</td>
                    <td class="px-4 py-3">
                        {{ $r->check_in_date->format('d M Y') }}
                        @if($isLate)
                            <span class="block mt-1 text-[11px] text-red-600 bg-red-50 border border-red-200 rounded-full px-1.5 py-0.5 inline-block">
                                Tanggal reservasi sudah terlewat
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $r->check_out_date->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                            <x-button variant="primary" href="{{ route('reservations.edit', $r) }}">Check-in</x-button>
                            <form method="POST" action="{{ route('reservations.no-show', $r) }}" onsubmit="return confirm('Tandai sebagai tidak datang?')">
                                @csrf @method('PATCH')
                                <x-button variant="warning" type="submit">Tidak datang</x-button>
                            </form>
                            <form method="POST" action="{{ route('reservations.cancel', $r) }}" onsubmit="return confirm('Batalkan reservasi ini?')">
                                @csrf @method('PATCH')
                                <x-button variant="danger" type="submit">Batalkan</x-button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada reservasi.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-4">{{ $reservations->links() }}</div>
@endsection