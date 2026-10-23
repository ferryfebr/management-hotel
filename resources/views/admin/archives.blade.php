@extends('layouts.app')
@section('title', 'Arsip & Backup')

@section('content')
@php
    $mb = 1024 * 1024;
    $formatMb = fn ($bytes) => number_format($bytes / $mb, 2, ',', '.');
    $categoryLabel = [
        'transactions' => 'Transaksi',
        'room_logs' => 'Room Log',
        'activity_logs' => 'Activity Log',
        'customers' => 'Customer',
        'customer_export' => 'Export Customer',
        'backup' => 'Backup Database',
    ];
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <div>
        <p class="text-sm text-gray-500">Arsip disimpan sebagai ZIP di storage privat. Unduh lalu simpan di luar hosting.</p>
        <p class="text-xs text-gray-500 mt-1">Customer belum diexport: {{ number_format($pendingCustomerExport, 0, ',', '.') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.backup') }}">
            @csrf
            <x-button variant="secondary" type="submit">Buat Backup Database</x-button>
        </form>
        <form method="POST" action="{{ route('customers.export') }}">
            @csrf
            <x-button variant="warning" type="submit">Export Customer</x-button>
        </form>
        <x-button variant="ghost" href="{{ route('admin.dashboard') }}">Panel Admin</x-button>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">Kategori</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">File</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">Ukuran</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">Periode</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">Dibuat</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($archives as $archive)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $categoryLabel[$archive->category] ?? $archive->category }}</td>
                        <td class="px-4 py-3 break-all max-w-xs">{{ $archive->file_name }}</td>
                        <td class="px-4 py-3">{{ number_format($archive->record_count, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $formatMb($archive->size_bytes) }} MB</td>
                        <td class="px-4 py-3">
                            @if($archive->period_start || $archive->period_end)
                                {{ $archive->period_start?->format('d M Y') ?? '-' }} — {{ $archive->period_end?->format('d M Y') ?? '-' }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $archive->created_at?->format('d M Y H.i') }}</td>
                        <td class="px-4 py-3">
                            @if($archive->isReady())
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Siap</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Dihapus</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($archive->isReady())
                                <x-button variant="ghost" href="{{ route('admin.archives.download', $archive) }}">Download</x-button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Belum ada arsip.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $archives->links() }}</div>
@endsection
