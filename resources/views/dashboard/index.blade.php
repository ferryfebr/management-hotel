@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $roomStats['total'] }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total Kamar</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $roomStats['available'] }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Tersedia</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $roomStats['occupied'] }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Terisi</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $roomStats['dirty'] }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Kotor</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $roomStats['maintenance'] }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Maintenance</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $todayCheckIns }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Check-in Hari Ini</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
        </span>
        <p class="text-2xl font-bold">{{ $todayCheckOuts }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Check-out Hari Ini</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <span class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </span>
        <p class="text-2xl font-bold">Rp {{ number_format($revenueThisMonth, 0, ',', '.') }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Pendapatan Bulan Ini</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm">Transaksi Terbaru</div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[600px]">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tamu</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kamar</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentTransactions as $trx)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $trx->code }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">{{ strtoupper(substr($trx->customer->name, 0, 1)) }}</span>
                            {{ $trx->customer->name }}
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $trx->room->room_number }}</td>
                    <td class="px-4 py-3"><x-badge status="{{ $trx->status }}" /></td>
                    <td class="px-4 py-3">Rp {{ number_format($trx->final_price, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
<script>
setTimeout(function tick(){ var a=document.activeElement,typing=a&&(a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.tagName==='SELECT'); if(!typing&&!document.hidden) location.reload(); setTimeout(tick,15000); },15000);
</script>
@endsection