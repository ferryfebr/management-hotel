@extends('layouts.app')
@section('title', 'Aktivitas Pekerja')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
    <p class="text-sm text-gray-500">Riwayat aktivitas seluruh resepsionis & room keeper (realtime via refresh otomatis)</p>
    <form method="GET" class="flex items-center gap-2 text-sm">
        <label class="text-gray-600 whitespace-nowrap">Pekerja</label>
        <select name="user" onchange="this.form.submit()" class="border border-gray-300 rounded-md px-3 py-2 w-full sm:w-auto">
            <option value="">Semua</option>
            @foreach($userPool as $u)
                <option value="{{ $u->id }}" @selected((string) $u->id === (string) $userId)>{{ $u->name }} ({{ \App\Models\User::roleLabel($u->role) }})</option>
            @endforeach
        </select>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[640px]">
        <thead class="bg-gray-50 text-left">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Pekerja</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Aktivitas</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Detail</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Waktu</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activities as $a)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold flex-shrink-0">{{ strtoupper(substr(optional($a['user'])->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <p class="font-medium truncate">{{ $a['user']?->name ?? 'Akun dihapus' }}</p>
                                <p class="text-xs text-gray-400">{{ $a['user'] ? \App\Models\User::roleLabel($a['user']->role) : '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $a['type'] === 'transaksi' ? 'bg-blue-400' : ($a['type'] === 'pembayaran' ? 'bg-emerald-400' : ($a['type'] === 'kamar' ? 'bg-yellow-400' : 'bg-gray-400')) }}"></span>
                            {{ $a['action'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $a['detail'] }}</td>
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $a['time'] instanceof \Illuminate\Support\Carbon ? $a['time']->format('d M Y H:i') : \Illuminate\Support\Carbon::parse($a['time'])->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada aktivitas tercatat.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

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