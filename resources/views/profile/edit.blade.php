<x-market-layout title="Profil">

    @php
        $roleLabel = match ($user->role) {
            'penjual' => 'Penjual',
            'admin'   => 'Admin',
            default   => 'Pembeli',
        };
        $menu = array_filter([
            $user->role === 'penjual' ? ['Toko Saya', route('dashboard'), '🏬'] : null,
            $user->role === 'pembeli' ? ['Tiket Saya', route('bookings.index'), '🎟️'] : null,
            $user->role === 'admin' ? ['Panel Admin', route('admin.sellers'), '🛡️'] : null,
            ['Chat', route('chat.index'), '💬'],
            ['Request Barang', route('requests.index'), '🔎'],
        ]);
    @endphp

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <!-- Ringkasan akun -->
        <section class="flex items-center gap-4">
            <span class="w-16 h-16 shrink-0 rounded-full bg-emerald-600 text-white text-2xl font-black flex items-center justify-center" aria-hidden="true">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 truncate">{{ $user->name }}</h1>
                <p class="text-sm text-slate-500 truncate">{{ $user->email }}</p>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $roleLabel }}</span>
                    @if($user->role === 'penjual')
                        @if($user->seller_status === 'verified')
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">✓ Terverifikasi</span>
                        @elseif($user->seller_status === 'rejected')
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200">Verifikasi ditolak</span>
                        @else
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">⏳ Menunggu verifikasi</span>
                        @endif
                    @endif
                    @if($user->google_id)
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">Terhubung dengan Google</span>
                    @endif
                </div>
            </div>
        </section>

        <!-- Menu pintas -->
        <nav class="rounded-xl border border-slate-200 divide-y divide-slate-100" aria-label="Menu akun">
            @foreach($menu as [$label, $url, $icon])
                <a href="{{ $url }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="w-6 text-center" aria-hidden="true">{{ $icon }}</span>
                    <span class="flex-1">{{ $label }}</span>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-rose-600 hover:bg-rose-50 text-left">
                    <span class="w-6 text-center" aria-hidden="true">🚪</span>
                    <span class="flex-1">Keluar</span>
                </button>
            </form>
        </nav>

        <section class="rounded-xl border border-slate-200 p-4 sm:p-6">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-xl border border-slate-200 p-4 sm:p-6">
            @include('profile.partials.update-password-form')
        </section>

        <section class="rounded-xl border border-rose-200 p-4 sm:p-6">
            @include('profile.partials.delete-user-form')
        </section>
    </main>
</x-market-layout>
