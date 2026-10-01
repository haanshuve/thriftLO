<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk / Login - thriftLO Batam</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased flex items-center justify-center min-h-screen p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 my-6">

        <!-- Header Card Login -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-white/10 backdrop-blur-md rounded-2xl mb-2 text-2xl shadow-inner border border-white/20">
                🌱
            </div>
            <h2 class="text-2xl font-black tracking-wide">thriftLO <span class="text-xs bg-emerald-900/60 px-2 py-0.5 rounded font-mono">Batam</span></h2>
            <p class="text-emerald-100 text-xs mt-1 font-medium">Portal Masuk Pembeli & Penjual</p>
        </div>

        <div class="p-6 sm:p-8">

            <!-- Alert Session Notifikasi Error / Success -->
            @if(session('error'))
                <div class="bg-rose-500 text-white p-3 rounded-xl mb-4 text-xs font-bold">
                    ⚠️ {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-xs font-extrabold text-gray-700 mb-1">Alamat Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        placeholder="nama@email.com"
                        class="w-full border-gray-200 rounded-xl p-3 text-xs focus:ring-emerald-500 focus:border-emerald-500 shadow-sm border">
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="password" class="block text-xs font-extrabold text-gray-700">Kata Sandi</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-[11px] text-emerald-600 font-bold hover:underline">Lupa Sandi?</a>
                        @endif
                    </div>
                    <input id="password" type="password" name="password" required
                        placeholder="Masukkan kata sandi..."
                        class="w-full border-gray-200 rounded-xl p-3 text-xs focus:ring-emerald-500 focus:border-emerald-500 shadow-sm border">
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input id="remember_me" type="checkbox" name="remember" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                    <label for="remember_me" class="ml-2 text-xs font-semibold text-gray-600">Ingat Saya di Perangkat Ini</label>
                </div>

                <!-- Tombol Submit -->
                <div class="pt-2">
                    <button type="submit" class="w-full bg-emerald-600 text-white font-extrabold py-3 rounded-xl hover:bg-emerald-700 transition shadow-lg hover:shadow-emerald-200 text-xs tracking-wider uppercase">
                        🔑 Masuk Akun
                    </button>
                </div>
            </form>

            <!-- Pemisah -->
            <div class="flex items-center gap-3 my-5" aria-hidden="true">
                <span class="flex-1 h-px bg-gray-200"></span>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">atau</span>
                <span class="flex-1 h-px bg-gray-200"></span>
            </div>

            <!-- Masuk dengan Google -->
            <a href="{{ route('auth.google.redirect') }}"
               class="w-full flex items-center justify-center gap-2.5 bg-white border border-gray-200 text-gray-700 font-extrabold py-3 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition shadow-sm text-xs tracking-wider uppercase">
                <svg class="w-4 h-4" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                    <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                    <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
                </svg>
                Masuk dengan Google
            </a>
            <p class="text-[11px] text-gray-400 text-center mt-2">Belum punya akun? Akun pembeli dibuat otomatis saat pertama kali masuk dengan Google.</p>

            <form action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data">
    @csrf <!-- INI WAJIB ADA AGAR TIDAK ERROR 419 -->

    <!-- inputan form lainnya -->
</form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-500">Belum punya akun?
                    <a href="{{ route('register') }}" class="text-emerald-600 font-bold hover:underline">Daftar Akun Baru</a>
                </p>
                <a href="{{ route('home') }}" class="text-xs text-gray-400 hover:text-gray-600 block mt-3 font-semibold">
                    ← Kembali ke Katalog Utama
                </a>
            </div>

        </div>
    </div>

</body>
</html>
