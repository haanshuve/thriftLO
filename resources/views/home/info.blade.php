{{-- "Cara kerja thriftLO" + banner ajakan jadi penjual (angka paket dari config). Butuh: $sellerCta, $viewer --}}
@php
    [$sellerUrl, $sellerLabel] = $sellerCta;
    $plan = config('thriftlo.subscription');
    $rupiah = fn ($n) => 'Rp' . number_format((int) $n, 0, ',', '.');
@endphp

<section aria-labelledby="judulCaraKerja">
    <h2 id="judulCaraKerja" class="text-base sm:text-lg font-bold text-slate-900 mb-3">Cara kerja thriftLO</h2>
    <ol class="grid sm:grid-cols-3 gap-3">
        @foreach([
            ['Cari dan chat', 'Temukan barang lewat pencarian, kategori, atau merek. Tanya kondisi barang langsung ke penjual lewat chat.', 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z'],
            ['Booking atau Checkout', 'Di Batam? Booking COD lalu ketemuan. Di luar Batam? Checkout dan pilih kurir, uangmu ditahan aman lewat escrow.', 'M5 4h14v16l-3-2-2 2-2-2-2 2-2-2-3 2V4Zm4 5h6m-6 4h6'],
            ['Terima barang dan konfirmasi', 'Saat COD, tunjukkan token QR ke penjual. Untuk pesanan Kirim, konfirmasi barang diterima supaya dana diteruskan.', 'M20 7 9 18l-5-5'],
        ] as $i => [$title, $text, $icon])
            <li class="relative rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm shadow-slate-200/70">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 shrink-0 rounded-full bg-emerald-600 text-white flex items-center justify-center" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                    </span>
                    <h3 class="font-semibold text-slate-900"><span class="text-emerald-700">{{ $i + 1 }}.</span> {{ $title }}</h3>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
            </li>
        @endforeach
    </ol>
</section>

<section id="jadi-penjual" aria-labelledby="judulJadiPenjual" class="scroll-mt-20 relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 via-amber-500 to-amber-400 text-white p-5 sm:p-8 shadow-lg shadow-orange-900/10">
    <svg class="absolute -right-6 -bottom-10 w-48 h-48 sm:w-64 sm:h-64 text-white/15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9.5 4.5 4h15L21 9.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5Zm6 11.5v-6h6v6"/></svg>
    <div class="relative max-w-2xl">
        <h2 id="judulJadiPenjual" class="text-xl sm:text-2xl font-black leading-tight">Ubah lemari penuh jadi cuan</h2>
        <p class="mt-2 text-sm sm:text-base text-white/95">Daftar sebagai penjual, verifikasi KTP, lalu tayangkan barangmu dengan Video Proof. Pembeli di Batam bisa COD, pembeli di luar Batam bisa checkout dengan escrow.</p>
        <ul class="mt-3 flex flex-wrap gap-2 text-xs sm:text-sm font-semibold">
            <li class="rounded-full bg-white/20 px-3 py-1">Gratis {{ (int) $plan['free_product_limit'] }} produk tayang</li>
            <li class="rounded-full bg-white/20 px-3 py-1">Langganan {{ $rupiah($plan['price']) }} per {{ (int) $plan['days'] }} hari untuk produk tanpa batas</li>
        </ul>
        @if($sellerUrl)
            <a href="{{ $sellerUrl }}" class="mt-5 inline-flex items-center gap-1.5 h-11 px-5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold shadow-sm transition active:scale-95 motion-reduce:transform-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                {{ $sellerLabel }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        @else
            <p class="mt-4 text-sm font-semibold text-white/95">Untuk berjualan, daftarkan akun penjual dengan email terpisah dari akunmu sekarang.</p>
        @endif
    </div>
</section>
