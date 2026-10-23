<x-admin-layout title="Ringkasan">

    @php
        $num = fn ($n) => number_format($n, 0, ',', '.');
        $rupiah = fn ($n) => 'Rp' . number_format($n, 0, ',', '.');
    @endphp

    <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Ringkasan</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gambaran cepat kondisi thriftLO hari ini.</p>
    </div>

    <!-- Perlu tindakan -->
    <section aria-labelledby="judulTindakan" class="rounded-xl bg-white border border-slate-200">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between gap-3">
            <h2 id="judulTindakan" class="text-sm font-bold text-slate-900">Perlu tindakan</h2>
            @if($stats['pendingSellers'] > 0)
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ $stats['pendingSellers'] }} menunggu</span>
            @endif
        </div>
        @if($pendingSellers->isEmpty())
            <p class="px-4 py-6 text-sm text-slate-500 text-center">Tidak ada yang perlu ditindaklanjuti. Semua penjual sudah ditinjau 🎉</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($pendingSellers as $seller)
                    <li class="px-4 py-3 flex items-center gap-3">
                        <x-avatar :user="$seller" :name="$seller->nama_toko ?: $seller->name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $seller->nama_toko ?: $seller->name }}</p>
                            <p class="text-xs text-slate-500 truncate">Penjual menunggu verifikasi · daftar {{ $seller->created_at->locale('id')->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('admin.sellers') }}" class="shrink-0 h-9 px-3 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Tinjau</a>
                    </li>
                @endforeach
            </ul>
            @if($stats['pendingSellers'] > $pendingSellers->count())
                <a href="{{ route('admin.sellers') }}" class="block px-4 py-2.5 border-t border-slate-100 text-sm font-semibold text-emerald-700 hover:bg-slate-50">Lihat semua {{ $stats['pendingSellers'] }} penjual menunggu →</a>
            @endif
        @endif
    </section>

    <!-- Pengguna -->
    <section aria-labelledby="judulPengguna" class="space-y-2">
        <h2 id="judulPengguna" class="text-sm font-bold text-slate-700">Pengguna</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-admin.stat label="Total akun" :value="$num($stats['users'])" :hint="$stats['deactivated'] ? $num($stats['deactivated']) . ' nonaktif' : 'Semua aktif'" :href="route('admin.users')" />
            <x-admin.stat label="Pembeli" :value="$num($stats['pembeli'])" :href="route('admin.users', ['role' => 'pembeli'])" />
            <x-admin.stat label="Penjual" :value="$num($stats['penjual'])" :href="route('admin.users', ['role' => 'penjual'])" />
            <x-admin.stat label="Admin" :value="$num($stats['admin'])" :href="route('admin.users', ['role' => 'admin'])" />
        </div>
    </section>

    <!-- Penjual & produk -->
    <section aria-labelledby="judulProduk" class="space-y-2">
        <h2 id="judulProduk" class="text-sm font-bold text-slate-700">Penjual &amp; produk</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-admin.stat label="Menunggu verifikasi" :value="$num($stats['pendingSellers'])" :tone="$stats['pendingSellers'] ? 'amber' : 'slate'" :href="route('admin.sellers')" />
            <x-admin.stat label="Produk aktif" :value="$num($stats['activeProducts'])" hint="Tersedia + di-booking" tone="emerald" :href="route('admin.products')" />
            <x-admin.stat label="Produk diturunkan" :value="$num($stats['takenDown'])" :tone="$stats['takenDown'] ? 'rose' : 'slate'" :href="route('admin.products', ['status' => 'diturunkan'])" />
        </div>
    </section>

    <!-- Transaksi & pendapatan -->
    <section aria-labelledby="judulTransaksi" class="space-y-2">
        <h2 id="judulTransaksi" class="text-sm font-bold text-slate-700">Transaksi &amp; pendapatan</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-admin.stat label="Booking COD" :value="$num($stats['codBookings'])" :href="route('admin.transactions', ['jenis' => 'cod'])" />
            <x-admin.stat label="Pesanan Kirim" :value="$num($stats['kirimOrders'])" :href="route('admin.transactions', ['jenis' => 'kirim'])" />
            <x-admin.stat label="Komisi Kirim" :value="$rupiah($stats['commission'])" hint="Dari pesanan yang dananya sudah cair" tone="emerald" />
            <x-admin.stat label="Biaya booking COD" :value="$rupiah($stats['bookingFees'])" hint="Semua yang dibayar, termasuk booking batal" tone="emerald" />
            <x-admin.stat label="Pembatalan COD" :value="$num($stats['cancellations'])" :tone="$stats['cancellations'] ? 'rose' : 'slate'" :href="route('admin.transactions', ['jenis' => 'cod', 'status' => 'dibatalkan'])" />
        </div>
    </section>
</x-admin-layout>
