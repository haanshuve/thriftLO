<x-auth-shell title="Masuk">

    @php
        // Satu pesan error di atas form (salah sandi, terlalu banyak percobaan, gagal Google)
        $alert = session('error') ?? $errors->first();
        $emailInvalid = $errors->has('email');
        $input = 'w-full h-11 rounded-lg border bg-white px-3 text-sm placeholder-slate-400 transition focus:border-emerald-500 focus:ring-emerald-500';
    @endphp

    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xl shadow-emerald-900/5 p-6 sm:p-8">

        <div class="text-center">
            <p class="text-3xl" aria-hidden="true">👋</p>
            <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Selamat datang kembali di thriftLO</h1>
            <p class="mt-1 text-sm text-slate-500">Masuk untuk lanjut berburu barang preloved favoritmu.</p>
        </div>

        @if(session('status'))
            <div class="mt-5 flex items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800" role="status">
                <span aria-hidden="true">✅</span>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if($alert)
            <div class="animate-fade-up mt-5 flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-800" role="alert">
                <span class="w-5 h-5 shrink-0 rounded-full bg-rose-500 text-white text-xs font-bold flex items-center justify-center" aria-hidden="true">!</span>
                <p>{{ $alert }}</p>
            </div>
        @endif

        <form id="loginForm" method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required @unless($alert) autofocus @endunless autocomplete="username"
                       placeholder="nama@email.com" @if($emailInvalid) aria-invalid="true" @endif
                       class="{{ $input }} {{ $emailInvalid ? 'border-rose-400' : 'border-slate-300' }}">
            </div>

            <div>
                <div class="flex items-baseline justify-between mb-1">
                    <label for="password" class="block text-sm font-semibold text-slate-700">Kata sandi</label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-semibold text-emerald-700 hover:underline">Lupa kata sandi?</a>
                    @endif
                </div>
                <div class="relative">
                    <input id="password" type="password" name="password" required @if($alert) autofocus @endif autocomplete="current-password"
                           placeholder="Masukkan kata sandi" @if($emailInvalid) aria-invalid="true" @endif
                           class="{{ $input }} pr-11 {{ $emailInvalid ? 'border-rose-400' : 'border-slate-300' }}">
                    <button type="button" id="togglePassword" aria-label="Tampilkan kata sandi" aria-pressed="false"
                            class="absolute inset-y-0 right-0 w-11 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-r-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer select-none">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                <span class="text-sm text-slate-600">Ingat saya di perangkat ini</span>
            </label>

            <button type="submit" id="loginSubmit"
                    class="w-full h-11 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold shadow-sm transition hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-600/30 active:scale-[0.98] disabled:opacity-70 disabled:cursor-wait motion-reduce:transform-none">
                Masuk
            </button>
        </form>

        <!-- Pemisah -->
        <div class="flex items-center gap-3 my-5" aria-hidden="true">
            <span class="flex-1 h-px bg-slate-200"></span>
            <span class="text-xs text-slate-400">atau</span>
            <span class="flex-1 h-px bg-slate-200"></span>
        </div>

        <a href="{{ route('auth.google.redirect') }}"
           class="w-full h-11 inline-flex items-center justify-center gap-2.5 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-400 hover:shadow active:scale-[0.98] motion-reduce:transform-none">
            <svg class="w-5 h-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
            </svg>
            Masuk dengan Google
        </a>
        <p class="mt-2 text-xs text-slate-400 text-center">Pertama kali masuk dengan Google? Akun pembelimu langsung dibuatkan.</p>

        <p class="mt-6 pt-5 border-t border-slate-100 text-center text-sm text-slate-600">
            Baru di thriftLO?
            <a href="{{ route('register') }}" class="font-semibold text-emerald-700 hover:underline">Daftar gratis</a>
        </p>
    </section>

    <!-- Alasan percaya -->
    <ul class="mt-5 grid grid-cols-3 gap-2 text-center text-[11px] sm:text-xs text-slate-500">
        <li><span class="block text-lg" aria-hidden="true">🔐</span>COD aman pakai token QR</li>
        <li><span class="block text-lg" aria-hidden="true">🪪</span>Penjual dicek KTP-nya</li>
        <li><span class="block text-lg" aria-hidden="true">🌱</span>Hemat & ramah bumi</li>
    </ul>

    <script>
        // Tampilkan / sembunyikan kata sandi
        document.getElementById('togglePassword').addEventListener('click', function () {
            const field = document.getElementById('password');
            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            this.setAttribute('aria-pressed', show);
            this.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            this.classList.toggle('text-emerald-600', show);
        });

        // Loading state supaya form tidak terkirim dua kali
        document.getElementById('loginForm').addEventListener('submit', function () {
            const button = document.getElementById('loginSubmit');
            button.disabled = true;
            button.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Sedang masuk...';
        });
    </script>
</x-auth-shell>
