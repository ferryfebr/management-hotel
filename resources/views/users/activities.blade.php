@extends('layouts.app')
@section('title', 'Aktivitas Pekerja')

@section('content')
@php
    $categoryMeta = [
        'transaksi' => ['label' => 'Transaksi', 'dot' => 'bg-blue-500', 'badge' => 'bg-blue-100 text-blue-700'],
        'pembayaran' => ['label' => 'Pembayaran', 'dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700'],
        'perpanjangan' => ['label' => 'Perpanjangan', 'dot' => 'bg-purple-500', 'badge' => 'bg-purple-100 text-purple-700'],
        'transfer' => ['label' => 'Pindah Kamar', 'dot' => 'bg-cyan-500', 'badge' => 'bg-cyan-100 text-cyan-700'],
        'kamar' => ['label' => 'Kamar', 'dot' => 'bg-yellow-500', 'badge' => 'bg-yellow-100 text-yellow-700'],
        'jenis_kamar' => ['label' => 'Jenis Kamar', 'dot' => 'bg-orange-500', 'badge' => 'bg-orange-100 text-orange-700'],
        'akun' => ['label' => 'Akun', 'dot' => 'bg-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-700'],
        'pelanggan' => ['label' => 'Pelanggan', 'dot' => 'bg-pink-500', 'badge' => 'bg-pink-100 text-pink-700'],
    ];
    $meta = fn ($type) => $categoryMeta[$type] ?? ['label' => ucfirst(str_replace('_', ' ', $type)), 'dot' => 'bg-gray-400', 'badge' => 'bg-gray-100 text-gray-700'];

    $grouped = $activities->getCollection()->groupBy(function ($a) {
        $time = $a['time'] instanceof \Illuminate\Support\Carbon ? $a['time'] : \Illuminate\Support\Carbon::parse($a['time']);
        return $time->format('Y-m-d');
    });

    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
@endphp

<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
    <p class="text-sm text-gray-500">Riwayat aktivitas seluruh pengguna, termasuk Owner (realtime via refresh otomatis)</p>
    <form method="GET" class="flex items-center gap-2 text-sm">
        <label class="text-gray-600 whitespace-nowrap">Pengguna</label>
        <select name="user" onchange="this.form.submit()" class="border border-gray-300 rounded-md px-3 py-2 w-full sm:w-auto">
            <option value="">Semua</option>
            @foreach($userPool as $u)
                <option value="{{ $u->id }}" @selected((string) $u->id === (string) $userId)>{{ $u->name }} ({{ \App\Models\User::roleLabel($u->role) }})</option>
            @endforeach
        </select>
    </form>
</div>

@forelse($grouped as $date => $rows)
    @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-3">
            <p class="text-sm font-semibold text-gray-700">
                @if($day->isToday())
                    Hari Ini
                @elseif($day->isYesterday())
                    Kemarin
                @else
                    {{ $day->format('d') }} {{ $bulan[(int) $day->format('n')] }} {{ $day->format('Y') }}
                @endif
            </p>
            <span class="text-xs text-gray-400">{{ $rows->count() }} aktivitas</span>
            <div class="flex-1 border-t border-gray-200"></div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100">
            @foreach($rows as $a)
                @php $m = $meta($a['type']); @endphp
                <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50">
                    <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">
                        {{ strtoupper(substr(optional($a['user'])->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="font-medium text-sm">{{ $a['user']?->name ?? 'Akun dihapus' }}</p>
                            <span class="text-xs text-gray-400">{{ $a['user'] ? \App\Models\User::roleLabel($a['user']->role) : '' }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $m['badge'] }}">{{ $m['label'] }}</span>
                        </div>
                        <p class="text-sm text-gray-700 mt-0.5">
                            <span class="inline-flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $m['dot'] }}"></span>
                                {{ $a['action'] }}
                            </span>
                        </p>
                        @if(!empty($a['detail']))
                            <p class="text-xs text-gray-500 mt-0.5">{{ $a['detail'] }}</p>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        {{ ($a['time'] instanceof \Illuminate\Support\Carbon ? $a['time'] : \Illuminate\Support\Carbon::parse($a['time']))->format('H.i') }} WIT
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center text-gray-400 text-sm">
        Belum ada aktivitas tercatat.
    </div>
@endforelse

<div class="mt-4">{{ $activities->links() }}</div>

<script>
setTimeout(function tick(){
    var a = document.activeElement;
    var typing = a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT');
    if (!typing && !document.hidden) location.reload();
    setTimeout(tick, 15000);
}, 15000);
</script>
@endsection