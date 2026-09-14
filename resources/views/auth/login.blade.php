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
