@props(['title', 'wide' => false])

{{-- Kerangka halaman Masuk & Daftar: logo dan warna sama dengan navbar market, tanpa menu --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - thriftLO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-emerald-50 via-white to-teal-50 text-slate-800 font-sans antialiased selection:bg-emerald-500 selection:text-white">
    <div class="min-h-[100dvh] flex flex-col">

        <header class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
                <img src="{{ asset('images/logo.jpeg.jpeg') }}" alt="Logo thriftLO" class="w-9 h-9 rounded-lg object-cover border border-slate-200 bg-white">
                <span class="flex flex-col leading-none">
                    <span class="text-lg font-black tracking-tight text-emerald-700">thriftLO</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Batam</span>
                </span>
            </a>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                <span class="hidden sm:inline">Kembali ke</span> katalog
            </a>
        </header>

        <main class="flex-1 flex items-start sm:items-center justify-center px-4 pt-2 pb-8 sm:py-8">
            <div class="w-full {{ $wide ? 'max-w-2xl' : 'max-w-md' }} animate-fade-up">
                {{ $slot }}
            </div>
        </main>

        <footer class="pb-6 px-4 text-center text-xs text-slate-400">
            thriftLO · Marketplace preloved · COD aman di Batam, kirim ke seluruh Indonesia
        </footer>
    </div>
</body>
</html>
