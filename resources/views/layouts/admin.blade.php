<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title . ' - Panel Admin thriftLO' : 'Panel Admin thriftLO' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">

    <!-- Lapisan gelap di belakang sidebar saat terbuka di HP -->
    <div x-show="sidebar" x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" style="display: none" aria-hidden="true"></div>

    <!-- Sidebar: tetap di desktop, off-canvas di HP -->
    <aside id="admin-sidebar"
           class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col transform transition-transform duration-200 -translate-x-full lg:translate-x-0"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           aria-label="Menu panel admin">
        <div class="h-16 px-5 flex items-center gap-3 border-b border-slate-800 shrink-0">
            <img src="{{ asset('images/logo.jpeg.jpeg') }}" alt="" class="w-9 h-9 rounded-lg object-cover ring-1 ring-slate-700">
            <div class="leading-tight">
                <p class="text-base font-black tracking-tight text-white">thrift<span class="text-emerald-400">LO</span></p>
                <p class="text-[11px] font-bold uppercase tracking-widest text-emerald-400">Panel Admin</p>
            </div>
            <button type="button" @click="sidebar = false" class="ml-auto p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 lg:hidden" aria-label="Tutup menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-sm font-medium" aria-label="Navigasi admin">
            @foreach($menu as [$label, $routeName, $activePattern, $iconPath])
                @php $active = request()->routeIs($activePattern); @endphp
                <a href="{{ route($routeName) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-emerald-600 text-white' : 'hover:bg-slate-800 hover:text-white' }}"
                   @if($active) aria-current="page" @endif>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $iconPath }}"/></svg>
                    <span class="flex-1">{{ $label }}</span>
                    @if($routeName === 'admin.sellers' && $pendingSellers > 0)
                        <span class="min-w-[22px] text-center text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $active ? 'bg-white text-emerald-700' : 'bg-amber-400 text-amber-950' }}" title="{{ $pendingSellers }} penjual menunggu verifikasi">{{ $pendingSellers }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <p class="px-5 py-4 border-t border-slate-800 text-[11px] text-slate-500">Smart Preloved &amp; Thrifting Hub Batam</p>
    </aside>

    <div class="lg:pl-64 min-h-screen flex flex-col">
        <!-- Header atas -->
        <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-6">
            <button type="button" @click="sidebar = true" class="p-2 -ml-2 rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka menu" aria-controls="admin-sidebar" :aria-expanded="sidebar.toString()">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <p class="text-base font-bold text-slate-900 truncate">{{ $title ?? 'Panel Admin' }}</p>

            <div class="ml-auto flex items-center gap-1 sm:gap-2 text-sm font-semibold">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-2 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-emerald-700" title="Kembali ke beranda marketplace">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5 5.5 4h13L20 9.5M4 9.5V20h16V9.5M4 9.5h16M9 20v-5h6v5"/></svg>
                    <span class="hidden sm:inline">Lihat toko</span>
                    <span class="sr-only sm:hidden">Lihat toko</span>
                </a>
                <span class="hidden sm:block w-px h-6 bg-slate-200"></span>
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-slate-100" title="Profil admin">
                    <x-avatar :user="auth()->user()" size="xs" tone="soft" />
                    <span class="hidden md:block max-w-[140px] truncate text-slate-700">{{ auth()->user()->name }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-2.5 sm:px-3 py-2 rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600">Keluar</button>
                </form>
            </div>
        </header>

        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5">
            @if(session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">✅ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">⚠️ {{ session('error') }}</div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
