{{-- Statistik asli (angka nol tidak ditampilkan) + strip keunggulan thriftLO. Butuh: $stats --}}
@if(!empty($stats))
    <div class="flex flex-wrap justify-center gap-x-6 gap-y-1 text-sm text-slate-600" aria-label="Statistik thriftLO">
        @foreach($stats as $label => $value)
            <p><span class="font-black text-emerald-700 tabular-nums">{{ number_format($value, 0, ',', '.') }}</span> {{ $label }}</p>
        @endforeach
    </div>
@endif

<section aria-labelledby="judulKeunggulan" class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-sm shadow-slate-200/70">
    <h2 id="judulKeunggulan" class="sr-only">Kenapa belanja di thriftLO</h2>
    <ul class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        @foreach([
            ['Penjual terverifikasi KTP', 'Setiap penjual dicek KTP dan selfie-nya oleh admin sebelum boleh berjualan.', 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4'],
            ['Video Proof', 'Penjual merekam kondisi asli barang, jadi kamu tahu apa yang kamu beli.', 'M4 6h11a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm12 4 5-3v10l-5-3'],
            ['Smart QR saat COD', 'Transaksi COD baru selesai setelah penjual memindai token QR dari HP-mu.', 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h2v2h-2v-2Zm4 0h2v6h-4v-2h2v-4Zm-4 4h2v2h-2v-2'],
            ['Dana aman dengan Escrow', 'Untuk pesanan Kirim, uangmu ditahan dulu sampai barang kamu terima.', 'M5 11V8a7 7 0 0 1 14 0v3M4 11h16v10H4V11Zm8 4v2'],
        ] as [$title, $text, $icon])
            <li class="flex items-start gap-3">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-0.5 text-xs text-slate-600 leading-relaxed">{{ $text }}</p>
                </div>
            </li>
        @endforeach
    </ul>
</section>
