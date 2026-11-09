@extends('layouts.app')
@section('title', 'Panel Admin')

@section('content')
@php
    $mb = 1024 * 1024;
    $dbThresholds = config('retention.thresholds');
    $formatMb = fn ($bytes) => number_format($bytes / $mb, 2, ',', '.');
    $statusLabel = [
        'ok' => 'Aman',
        'warn' => 'Perhatian',
        'critical' => 'Kritis',
        'lock' => 'Darurat',
        'archive' => 'Arsip otomatis',
        'force' => 'Arsip paksa',
    ][$snapshot['status']] ?? $snapshot['status'];
    $dbStatusLabel = [
        'ok' => 'Aman',
        'warn' => 'Perhatian',
        'archive' => 'Arsip otomatis',
        'force' => 'Arsip paksa',
        'lock' => 'Darurat',
    ][$snapshot['db_status']] ?? $snapshot['db_status'];
@endphp

@if($emergency)
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Mode darurat database aktif.</p>
        <p class="text-xs mt-0.5">Penulisan data operasional diblokir sampai retensi berhasil menurunkan pemakaian. Jalankan retensi sekarang.</p>
    </div>
@endif

@if($archiveError || $backupError)
    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-semibold">Fitur arsip/backup belum siap.</p>
        @if($archiveError)
            <p class="text-xs mt-1">Arsip: {{ $archiveError }}</p>
        @endif
        @if($backupError)
            <p class="text-xs mt-1">Backup: {{ $backupError }}</p>
        @endif
    </div>
@endif

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <div>
        <p class="text-sm text-gray-500">Estimasi pemakaian aplikasi terhadap kuota hosting. Bukan pembacaan resmi cPanel.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <x-button variant="secondary" href="{{ route('admin.dashboard', ['refresh' => 1]) }}">Refresh Pemakaian</x-button>
        <form method="POST" action="{{ route('admin.retention.run') }}">
            @csrf
            <x-button variant="primary" type="submit">Jalankan Retensi</x-button>
        </form>
        <x-button variant="secondary" href="{{ route('admin.archives') }}">Arsip &amp; Backup</x-button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="md:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Kuota Hosting</p>
        <div class="mt-2 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
            <div>
                <p class="text-3xl font-bold">{{ number_format($snapshot['used_mb'], 2, ',', '.') }} MB</p>
                <p class="text-xs text-gray-500">dari {{ number_format($snapshot['quota_mb'], 0, ',', '.') }} MB total hosting</p>
            </div>
            <div class="sm:text-right">
                <p class="text-lg font-semibold">{{ number_format($snapshot['percent'], 2, ',', '.') }}%</p>
                <p class="text-xs text-gray-500">{{ $statusLabel }}</p>
            </div>
        </div>
        <div class="mt-3 h-2.5 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full {{ $snapshot['percent'] >= 85 ? 'bg-red-500' : ($snapshot['percent'] >= 75 ? 'bg-yellow-500' : 'bg-green-500') }}"
                 style="width: {{ min($snapshot['percent'], 100) }}%"></div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Sisa kuota: {{ number_format($snapshot['free_mb'], 2, ',', '.') }} MB</p>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Database Aktif</p>
                <p class="text-xs font-medium text-gray-700">{{ number_format($snapshot['db_used_mb'], 2, ',', '.') }} / {{ number_format($snapshot['db_budget_mb'], 0, ',', '.') }} MB</p>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full {{ $snapshot['db_percent'] >= 70 ? 'bg-red-500' : ($snapshot['db_percent'] >= 60 ? 'bg-yellow-500' : 'bg-green-500') }}"
                     style="width: {{ min($snapshot['db_percent'], 100) }}%"></div>
            </div>
            <div class="mt-1.5 flex flex-wrap justify-between gap-2 text-xs text-gray-500">
                <span>{{ number_format($snapshot['db_percent'], 2, ',', '.') }}% dari budget · {{ $dbStatusLabel }}</span>
                <span>Hard guard 75% = {{ number_format($snapshot['db_budget_mb'] * 0.75, 0, ',', '.') }} MB</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Customer Belum Diexport</p>
        <p class="mt-2 text-2xl font-bold">{{ number_format($pendingCustomerExport, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-500">Pengingat tiap {{ number_format(config('retention.customer_export.reminder'), 0, ',', '.') }} customer baru.</p>
        <form method="POST" action="{{ route('customers.export') }}" class="mt-3">
            @csrf
            <x-button variant="warning" type="submit" block>Export CSV + Foto KTP</x-button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="font-semibold text-sm mb-3">Rincian Pemakaian</h2>
        <div class="space-y-2 text-sm">
            @foreach($snapshot['categories'] as $category)
                <div class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2">
                    <span class="text-gray-600">{{ $category['label'] }}</span>
                    <span class="font-medium">{{ $formatMb($category['bytes']) }} MB</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="font-semibold text-sm mb-3">Batas Data Aktif</h2>
        <div class="space-y-2 text-sm">
            @foreach([
                'customers' => 'Customer',
                'transactions' => 'Transaksi',
                'room_logs' => 'Room log',
                'activity_logs' => 'Activity log',
            ] as $key => $label)
                <div class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2">
                    <span class="text-gray-600">{{ $label }}</span>
                    <span class="font-medium">{{ number_format($counts[$key], 0, ',', '.') }} / {{ number_format($limits[$key], 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-gray-500">
            Ambang database: {{ $dbThresholds['warn'] * 100 }}% peringatan,
            {{ $dbThresholds['archive'] * 100 }}% archive,
            {{ $dbThresholds['force'] * 100 }}% paksa,
            {{ $dbThresholds['lock'] * 100 }}% darurat.
        </p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-sm">Arsip Terbaru</h2>
        <x-button variant="ghost" href="{{ route('admin.archives') }}">Lihat Semua</x-button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-3 py-2 text-xs text-gray-500 uppercase">Kategori</th>
                    <th class="px-3 py-2 text-xs text-gray-500 uppercase">File</th>
                    <th class="px-3 py-2 text-xs text-gray-500 uppercase">Data</th>
                    <th class="px-3 py-2 text-xs text-gray-500 uppercase">Ukuran</th>
                    <th class="px-3 py-2 text-xs text-gray-500 uppercase">Dibuat</th>
                </tr>
            </thead>
            <tbody>
                @forelse($archives as $archive)
                    <tr class="border-b border-gray-50">
                        <td class="px-3 py-2">{{ $archive->category }}</td>
                        <td class="px-3 py-2 break-all">{{ $archive->file_name }}</td>
                        <td class="px-3 py-2">{{ number_format($archive->record_count, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ $formatMb($archive->size_bytes) }} MB</td>
                        <td class="px-3 py-2">{{ $archive->created_at?->format('d M Y H.i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">Belum ada arsip.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
