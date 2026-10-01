<x-market-layout title="Langganan Penjual">

    @php
        $limit = (int) $plan['free_product_limit'];
        $price = 'Rp' . number_format($plan['price'], 0, ',', '.');
        $isSubscribed = $user->hasActiveSubscription();
        $daysLeft = $user->subscriptionDaysLeft();
        $hiddenCount = $isSubscribed ? 0 : max(0, $activeCount - $limit);
        $wasSubscribed = !$isSubscribed && $user->subscription_expires_at !== null;
        $usedPercent = min(100, $limit > 0 ? round($activeCount / $limit * 100) : 100);
    @endphp

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Kembali ke Toko Saya
        </a>

        <div>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900">Langganan penjual</h1>
            <p class="text-sm text-slate-500 mt-0.5">Gratis untuk {{ $limit }} produk aktif. Butuh lebih? Cukup {{ $price }}/bulan untuk produk tanpa batas.</p>
        </div>

        @if(session('success'))
            <div class="animate-fade-up rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">✅ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">⚠️ {{ session('error') }}</div>
        @endif
        @if(session('limit_reached'))
            <div class="animate-fade-up rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                <p class="font-semibold">Kuota {{ $limit }} produk gratis kamu sudah penuh</p>
                <p class="mt-0.5">Barang ke-{{ $limit + 1 }} dan seterusnya bisa ditayangkan setelah berlangganan. Slot juga kosong lagi saat barangmu terjual lewat verifikasi QR, atau kalau kamu menghapus barang yang tidak dijual lagi.</p>
            </div>
        @endif

        <!-- Status paket saat ini -->
        @if($isSubscribed)
            <section class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Paket aktif</p>
                <div class="mt-1 flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">Unlimited</h2>
                    <p class="text-sm font-semibold text-emerald-800 tabular-nums">Sisa {{ $daysLeft }} hari</p>
                </div>
                <p class="text-sm text-slate-600 mt-1">
                    Aktif sampai {{ $user->subscription_expires_at->locale('id')->translatedFormat('j F Y, H:i') }} WIB · {{ $activeCount }} produk aktif
                </p>
            </section>
        @else
            <section class="rounded-xl border border-slate-200 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paket aktif</p>
                <div class="mt-1 flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">Gratis</h2>
                    <p class="text-sm font-semibold text-slate-700 tabular-nums">{{ $activeCount }}/{{ $limit }} produk aktif</p>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow="{{ $activeCount }}" aria-valuemin="0" aria-valuemax="{{ $limit }}" aria-label="Kuota produk gratis">
                    <div class="h-full rounded-full {{ $activeCount >= $limit ? 'bg-amber-500' : 'bg-emerald-600' }}" style="width: {{ $usedPercent }}%"></div>
                </div>
                @if($wasSubscribed)
                    <p class="text-sm text-slate-600 mt-2">Langganan terakhirmu berakhir {{ $user->subscription_expires_at->locale('id')->translatedFormat('j F Y') }}.</p>
                @endif
            </section>

            @if($hiddenCount > 0)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                    <p class="font-semibold">{{ $hiddenCount }} produk sedang disembunyikan dari katalog</p>
                    <p class="mt-0.5">Produkmu tidak dihapus. Hanya {{ $limit }} produk pertama yang tampil ke pembeli sampai kamu berlangganan lagi.</p>
                </div>
            @endif
        @endif

        <!-- Perbandingan paket -->
        <section class="grid sm:grid-cols-2 gap-3" aria-label="Pilihan paket">
            <div class="rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-900">Gratis</h3>
                <p class="text-2xl font-black text-slate-900 mt-1">Rp0</p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li>✓ Maksimal {{ $limit }} produk aktif</li>
                    <li>✓ Chat & booking COD dengan token QR</li>
                    <li>✓ Badge penjual terverifikasi</li>
                </ul>
            </div>
            <div class="rounded-xl border-2 border-emerald-500 p-4 relative">
                <span class="absolute -top-2.5 right-3 text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-600 text-white">Paling hemat</span>
                <h3 class="font-semibold text-slate-900">Unlimited</h3>
                <p class="text-2xl font-black text-emerald-700 mt-1">{{ $price }}<span class="text-sm font-semibold text-slate-500">/{{ $plan['days'] }} hari</span></p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li>✓ Produk aktif <strong class="text-slate-900">tanpa batas</strong></li>
                    <li>✓ Semua fitur paket Gratis</li>
                    <li>✓ Perpanjang kapan saja, sisa hari tidak hangus</li>
                </ul>

                @if($user->seller_status === 'verified')
                    <form action="{{ route('subscription.pay') }}" method="POST" class="mt-4"
                          onsubmit="this.querySelector('button').disabled = true">
                        @csrf
                        <button type="submit" class="w-full h-11 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-[0.98] disabled:opacity-70 disabled:cursor-wait motion-reduce:transform-none">
                            {{ $isSubscribed ? 'Perpanjang ' . $plan['days'] . ' hari' : 'Bayar Sekarang' }} · {{ $price }}
                        </button>
                    </form>
                    @if($plan['simulate_payment'])
                        <p class="mt-2 text-[11px] text-slate-400 text-center">Mode simulasi: pembayaran langsung berhasil tanpa memotong saldo.</p>
                    @endif
                @else
                    <p class="mt-4 text-sm text-slate-500 text-center">Bisa berlangganan setelah verifikasi KYC disetujui admin.</p>
                @endif
            </div>
        </section>
    </main>
</x-market-layout>
