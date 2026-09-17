<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Kharisma Hotel')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="flex min-h-screen">

    {{-- Overlay saat menu terbuka (mobile/tablet) --}}
    <div id="navOverlay" class="hidden fixed inset-0 bg-black/50 z-30 lg:hidden"></div>

    {{-- Ikon heroicons (inline, stroke) untuk menu --}}
    @php
        $icons = [
            'dashboard' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>',
            'charts' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>',
            'tag' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>',
            'door' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-4.5 4.5 4.5m-8.25 0h8.25"/></svg>',
            'calendar' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>',
            'users' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>',
            'checkin' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/></svg>',
            'clipboard' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>',
            'sparkles' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>',
            'userscog' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>',
            'clock' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
            'usercircle' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>',
        ];

        $nav = fn($id, $route, $pattern, $label) => [
            'route' => $route,
            'pattern' => $pattern,
            'label' => $label,
            'icon' => $icons[$id],
        ];

        $ownerNav = [
            $nav('dashboard', 'dashboard.index', 'dashboard.*', 'Dashboard'),
            $nav('charts', 'reports.index', 'reports.*', 'Laporan'),
            $nav('tag', 'room-types.index', 'room-types.*', 'Jenis Kamar'),
            $nav('clock', 'users.activities', 'users.activities', 'Aktivitas'),
        ];
        $ownerAccountNav = [
            $nav('userscog', 'users.index', 'users.index', 'Kelola Akun'),
            $nav('usercircle', 'profile.edit', 'profile.edit', 'Profil'),
        ];
        $staffNav = [
            $nav('door', 'rooms.index', 'rooms.*', 'Kamar'),
            $nav('calendar', 'reservations.index', 'reservations.*', 'Reservasi'),
            $nav('users', 'transactions.active', 'transactions.active*', 'Tamu Aktif'),
            $nav('clock', 'transactions.history', 'transactions.history*', 'Riwayat Transaksi'),
            $nav('checkin', 'transactions.checkin-form', 'transactions.checkin*', 'Check-in Baru'),
            $nav('clipboard', 'customers.index', 'customers.*', 'Pelanggan'),
        ];
        $keeperNav = [
            $nav('sparkles', 'room-keeper.index', 'room-keeper.*', 'Status Kamar'),
        ];
        $activityNav = [
            $nav('clock', 'users.activities', 'users.activities', 'Aktivitas'),
        ];

        $navClass = fn($active) => $active
            ? 'flex items-center gap-3 px-3 py-2 rounded-lg bg-indigo-600 text-white font-medium'
            : 'flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white';
    @endphp

    {{-- Sidebar: off-canvas di layar kecil, statis di desktop (lg+) --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-white flex-shrink-0 -translate-x-full lg:static lg:translate-x-0 transition-transform duration-200 flex flex-col">
        <div class="flex items-center justify-between px-4 py-4 border-b border-slate-800">
            <span class="flex items-center gap-2 font-bold text-lg">
                <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                </span>
                Kharisma Hotel
            </span>
            <button id="navClose" class="text-gray-400 hover:text-white w-8 h-8 flex items-center justify-center rounded lg:hidden" aria-label="Tutup">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 p-4 space-y-1 text-sm overflow-y-auto">
            @auth
                @if(auth()->user()->isOwner())
                    @foreach($ownerNav as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['pattern'])) }}">
                            {!! $item['icon'] !!} {{ $item['label'] }}
                        </a>
                    @endforeach
                    @foreach($staffNav as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['pattern'])) }}">
                            {!! $item['icon'] !!} {{ $item['label'] }}
                        </a>
                    @endforeach
                    <div class="pt-3 mt-3 border-t border-slate-800"></div>
                    @foreach($ownerAccountNav as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['pattern'])) }}">
                            {!! $item['icon'] !!} {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif

                @if(auth()->user()->isResepsionis())
                    @foreach($staffNav as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['pattern'])) }}">
                            {!! $item['icon'] !!} {{ $item['label'] }}
                        </a>
                    @endforeach
                    @foreach($activityNav as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['pattern'])) }}">
                            {!! $item['icon'] !!} {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif

                @if(auth()->user()->isRoomKeeper())
                    @foreach($keeperNav as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['pattern'])) }}">
                            {!! $item['icon'] !!} {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif
            @endauth
        </nav>

        <div class="p-4 border-t border-slate-800">
            @auth
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</p>
                    </div>
                </div>
            @endauth
        </div>
    </aside>

    {{-- Main content --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-gray-200 px-4 md:px-6 py-3 flex justify-between items-center gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <button id="navToggle" class="lg:hidden text-gray-700 hover:text-gray-900 text-2xl leading-none w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100" aria-label="Menu">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>
                <h1 class="text-base md:text-lg font-semibold truncate">@yield('title', 'Hotel Management')</h1>
            </div>
            @auth
                <div class="flex items-center gap-2 md:gap-4 text-sm flex-shrink-0">
                    <div class="hidden sm:flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="leading-tight">
                            <p class="font-medium">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex items-center gap-1.5 text-red-600 hover:bg-red-50 rounded-md px-2 py-1.5 text-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                            Keluar
                        </button>
                    </form>
                </div>
            @endauth
        </header>

        <main class="flex-1 p-4 md:p-6">
            @if(session('success'))
                <div class="mb-4 rounded-lg bg-green-100 text-green-800 px-4 py-2 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-4 py-2 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
(function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('navOverlay');
    var openBtn = document.getElementById('navToggle');
    var closeBtn = document.getElementById('navClose');

    function openNav() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    }
    function closeNav() {
        if (window.matchMedia('(min-width: 1024px)').matches) return;
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }

    if (openBtn) openBtn.addEventListener('click', openNav);
    if (closeBtn) closeBtn.addEventListener('click', closeNav);
    if (overlay) overlay.addEventListener('click', closeNav);
    if (sidebar) sidebar.querySelectorAll('nav a').forEach(function (a) { a.addEventListener('click', closeNav); });
})();
</script>
@if(session('clear_checkin_draft'))
<script>
    try { sessionStorage.removeItem('checkin_id_card_photo'); } catch (e) {}
</script>
@endif
</body>
</html>