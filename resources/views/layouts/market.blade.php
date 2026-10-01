<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - thriftLO' : 'thriftLO - Smart Preloved & Thrifting Hub Batam' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-800 font-sans antialiased sm:pb-0 selection:bg-emerald-500 selection:text-white {{ $mobileBottomNav ? 'pb-20' : '' }} {{ $fullHeight ? 'h-[100dvh] flex flex-col overflow-hidden' : '' }}">

    @php
        $onTransaksi = request()->routeIs('dashboard', 'bookings.index', 'admin.*');
        $role = auth()->user()?->role;
        $transaksiLabel = match ($role) {
            'penjual' => 'Toko Saya',
            'admin'   => 'Panel Admin',
            default   => 'Transaksi',
        };
        $transaksiShortLabel = match ($role) {
            'penjual' => 'Toko',
            'admin'   => 'Admin',
            default   => 'Transaksi',
        };
        $onHome = request()->routeIs('home');
    @endphp

    <!-- Header / Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-3 sm:gap-6">

            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
                <img src="{{ asset('images/logo.jpeg.jpeg') }}" alt="Logo thriftLO" class="w-9 h-9 rounded-lg object-cover border border-slate-200">
                <span class="hidden sm:flex flex-col leading-none">
                    <span class="text-lg font-black tracking-tight text-emerald-700">thriftLO</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Batam</span>
                </span>
            </a>

            <!-- Search (selalu mencari di katalog) -->
            <form action="{{ route('home') }}" method="GET" class="flex-1 relative" role="search">
                @if($onHome && $activeKategori !== 'all')
                    <input type="hidden" name="kategori" value="{{ $activeKategori }}">
                @endif
                <label for="search" class="sr-only">Cari barang preloved</label>
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="search" name="search" value="{{ $onHome ? request('search') : '' }}" placeholder="Cari jaket, sepatu, kamera..."
                       class="w-full bg-slate-100 border border-transparent rounded-lg pl-9 pr-3 py-2 text-sm placeholder-slate-400 focus:bg-white focus:border-emerald-500 focus:ring-emerald-500">
            </form>

            <!-- Aksi kanan -->
            <nav class="flex items-center gap-1 sm:gap-2 shrink-0 text-sm font-semibold" aria-label="Menu utama">
                <a href="{{ route('chat.index') }}" class="relative p-2 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-emerald-700" aria-label="Chat{{ $unreadCount ? ", $unreadCount pesan belum dibaca" : '' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                    @if($unreadCount > 0)
                        <span class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </a>

                @auth
                    <a href="{{ $transaksiUrl }}" class="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-lg hover:bg-slate-100 {{ $onTransaksi ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 hover:text-emerald-700' }}"
                       @if($onTransaksi) aria-current="page" @endif>
                        {{ $transaksiLabel }}
                    </a>
                    <span class="hidden sm:block w-px h-6 bg-slate-200"></span>
                    <a href="{{ route('profile.edit') }}" class="hidden sm:flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-slate-100">
                        <span class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 font-bold text-xs flex items-center justify-center">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="max-w-[110px] truncate text-slate-700">{{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="px-3 py-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-rose-600">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:block px-4 py-2 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50">Masuk</a>
                    <a href="{{ route('register') }}" class="px-3 sm:px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Daftar</a>
                @endauth
            </nav>
        </div>

        @if($showCategories)
            <!-- Kategori (chip horizontal) -->
            <div id="kategori" class="max-w-7xl mx-auto px-4 sm:px-6 pb-3 scroll-mt-20">
                <div class="flex gap-2 overflow-x-auto no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
                    <a href="{{ route('home', array_filter(['search' => request('search')])) }}"
                       class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border text-sm font-semibold transition {{ $activeKategori === 'all' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-200 text-slate-600 hover:border-emerald-500 hover:text-emerald-700' }}"
                       @if($activeKategori === 'all') aria-current="page" @endif>
                        🛍️ Semua
                    </a>
                    @foreach($categories as $value => $cat)
                        <a href="{{ route('home', array_filter(['kategori' => $value, 'search' => request('search')])) }}"
                           class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border text-sm font-semibold transition {{ $activeKategori === $value ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-200 text-slate-600 hover:border-emerald-500 hover:text-emerald-700' }}"
                           @if($activeKategori === $value) aria-current="page" @endif>
                            <span aria-hidden="true">{{ $cat['icon'] }}</span> {{ $cat['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </header>

    @if($fullHeight)
        <div class="flex-1 min-h-0 flex flex-col">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif

    @if($mobileBottomNav)
    <!-- Bottom navigation (mobile) -->
    <nav class="sm:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 pb-[env(safe-area-inset-bottom)]" aria-label="Navigasi bawah">
        <div class="grid grid-cols-5 text-[11px] font-semibold">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-0.5 py-2 {{ $onHome && $activeKategori === 'all' ? 'text-emerald-700' : 'text-slate-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-8.5Z"/></svg>
                Beranda
            </a>
            <a href="{{ $onHome ? '#kategori' : route('home') . '#kategori' }}" class="flex flex-col items-center gap-0.5 py-2 {{ $onHome && $activeKategori !== 'all' ? 'text-emerald-700' : 'text-slate-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>
                Kategori
            </a>
            <a href="{{ route('chat.index') }}" class="relative flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('chat.*') ? 'text-emerald-700' : 'text-slate-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                @if($unreadCount > 0)
                    <span class="absolute top-1 left-1/2 ml-1 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
                Chat
            </a>
            <a href="{{ $transaksiUrl }}" class="flex flex-col items-center gap-0.5 py-2 {{ $onTransaksi ? 'text-emerald-700' : 'text-slate-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16l-3-2-2 2-2-2-2 2-2-2-3 2V4Z"/><path d="M9 9h6M9 13h6"/></svg>
                {{ $transaksiShortLabel }}
            </a>
            <a href="{{ auth()->check() ? route('profile.edit') : route('login') }}" class="flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs('profile.*') ? 'text-emerald-700' : 'text-slate-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                {{ auth()->check() ? 'Profil' : 'Masuk' }}
            </a>
        </div>
    </nav>
    @endif

    @if($floatingChat)
        <!-- Floating chat hanya di desktop; di mobile sudah ada tab Chat di bottom navigation -->
        <div class="hidden sm:block">
            @include('components.floating-chat')
        </div>
    @endif
</body>
</html>
