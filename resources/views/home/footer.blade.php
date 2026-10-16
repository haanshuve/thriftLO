{{-- Footer beranda: tentang, bantuan singkat (angka dari config), tautan, hak cipta. Butuh: $viewer, $sellerCta --}}
@php
    $bookingFee = 'Rp' . number_format((int) config('thriftlo.booking.fee'), 0, ',', '.');
    $releaseDays = (int) config('thriftlo.escrow.auto_release_days');
    [$sellerUrl] = $sellerCta;
@endphp

<footer class="mt-4 border-t border-slate-200 pt-8 pb-4 text-sm text-slate-600">
    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="flex items-center gap-2">
                <img src="{{ asset('images/logo.jpeg.jpeg') }}" alt="" class="w-8 h-8 rounded-lg object-cover border border-slate-200">
                <span class="text-lg font-black tracking-tight text-emerald-700">thriftLO</span>
            </p>
            <p class="mt-2 leading-relaxed">Marketplace preloved untuk Batam dan sekitarnya. Penjual diverifikasi KTP, kondisi barang ditunjukkan lewat Video Proof, dan pembayaran dibuat aman lewat COD Smart QR atau escrow.</p>
        </div>

        <div class="lg:col-span-2">
            <h2 class="font-bold text-slate-900">Bantuan singkat</h2>
            <dl class="mt-2 space-y-2">
                <div>
                    <dt class="font-semibold text-slate-800">Cara booking COD</dt>
                    <dd>Pilih barang dari penjual di Batam, tekan Booking COD, lalu bayar biaya booking {{ $bookingFee }} via QRIS untuk mengunci barang. Saat ketemuan, cek barangnya lalu tunjukkan token QR ke penjual.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-800">Kirim dan escrow</dt>
                    <dd>Untuk penjual di luar Batam, pilih kurir lalu checkout. Uangmu ditahan thriftLO dan diteruskan ke penjual setelah kamu konfirmasi barang diterima, atau otomatis {{ $releaseDays }} hari setelah barang dikirim.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-800">Biaya booking</dt>
                    <dd>Biaya booking tidak dapat dikembalikan bila kamu membatalkan booking. Bila penjual yang membatalkan, biaya booking dikembalikan.</dd>
                </div>
            </dl>
        </div>

        <nav aria-label="Tautan footer">
            <h2 class="font-bold text-slate-900">Tautan</h2>
            <ul class="mt-2 space-y-1.5">
                <li><a href="{{ route('home', ['koleksi' => 'baru']) }}" class="hover:text-emerald-700 hover:underline">Semua barang</a></li>
                <li><a href="{{ route('brands.index') }}" class="hover:text-emerald-700 hover:underline">Semua merek</a></li>
                <li><a href="{{ route('requests.index') }}" class="hover:text-emerald-700 hover:underline">Request barang</a></li>
                <li><a href="{{ $sellerUrl ?? '#jadi-penjual' }}" class="hover:text-emerald-700 hover:underline">Jadi penjual</a></li>
                @guest
                    <li><a href="{{ route('login') }}" class="hover:text-emerald-700 hover:underline">Masuk</a></li>
                @endguest
            </ul>
        </nav>
    </div>

    <p class="mt-8 pt-4 border-t border-slate-100 text-center text-xs text-slate-400">© {{ now()->year }} thriftLO · Marketplace preloved Batam. Merek yang tampil diisi oleh penjual dan bukan afiliasi resmi.</p>
</footer>
