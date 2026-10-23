<x-admin-layout title="Transaksi">

    @php
        $rupiah = fn ($n) => 'Rp' . number_format((int) $n, 0, ',', '.');
        $shop = fn ($product) => $product?->user ? ($product->user->nama_toko ?: $product->user->name) : '-';
        $codBadge = [
            'Pending'   => 'bg-amber-100 text-amber-800',
            'Confirmed' => 'bg-sky-100 text-sky-800',
            'Completed' => 'bg-emerald-100 text-emerald-800',
            'Cancelled' => 'bg-rose-100 text-rose-800',
        ];
        $feeBadge = [
            \App\Models\Booking::FEE_PAID      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            \App\Models\Booking::FEE_FORFEITED => 'bg-slate-100 text-slate-700 border-slate-200',
            // Data lama sebelum biaya booking selalu menjadi milik platform
            \App\Models\Booking::FEE_REFUNDED  => 'bg-sky-50 text-sky-700 border-sky-200',
        ];
        $orderBadge = [
            \App\Models\Order::AWAITING_SHIPMENT => 'bg-amber-100 text-amber-800',
            \App\Models\Order::SHIPPED           => 'bg-sky-100 text-sky-800',
            \App\Models\Order::COMPLETED         => 'bg-emerald-100 text-emerald-800',
        ];
    @endphp

    <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Transaksi</h1>
        <p class="text-sm text-slate-500 mt-0.5">Booking COD (ketemu langsung di Batam) dan pesanan Kirim (escrow) dari semua penjual.</p>
    </div>

    <!-- Filter -->
    <form method="GET" action="{{ route('admin.transactions') }}" class="rounded-xl bg-white border border-slate-200 p-3 sm:p-4 grid grid-cols-2 sm:grid-cols-[1fr_auto_auto_auto] gap-2 sm:gap-3 items-end" role="search">
        <div class="col-span-2 sm:col-span-1">
            <label for="q" class="block text-xs font-semibold text-slate-600 mb-1">Cari</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Barang, pembeli, penjual, atau token QR"
                   class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>
        <div>
            <label for="jenis" class="block text-xs font-semibold text-slate-600 mb-1">Jenis</label>
            <select id="jenis" name="jenis" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
            <select id="status" name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-2 sm:col-span-1 flex gap-2">
            <button type="submit" class="flex-1 sm:flex-none h-[38px] px-4 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-semibold">Terapkan</button>
            @if($search !== '' || $type !== 'semua' || $status !== 'semua')
                <a href="{{ route('admin.transactions') }}" class="h-[38px] px-3 inline-flex items-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-semibold">Reset</a>
            @endif
        </div>
    </form>

    @if($bookings)
        <section aria-labelledby="judulCod" class="rounded-xl bg-white border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                <h2 id="judulCod" class="text-sm font-bold text-slate-900">Booking COD</h2>
                <span class="text-xs text-slate-500">{{ $bookings->total() }} transaksi</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2.5">Barang</th>
                            <th scope="col" class="px-4 py-2.5">Pembeli</th>
                            <th scope="col" class="px-4 py-2.5">Penjual</th>
                            <th scope="col" class="px-4 py-2.5">Status</th>
                            <th scope="col" class="px-4 py-2.5">Biaya booking</th>
                            <th scope="col" class="px-4 py-2.5">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bookings as $booking)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $booking->product->title ?? 'Barang dihapus' }}</p>
                                    <p class="text-xs text-slate-500 font-mono">{{ $booking->qr_token }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-800">{{ $booking->user->name ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $booking->user->email ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ $shop($booking->product) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full {{ $codBadge[$booking->status_cod] ?? 'bg-slate-100 text-slate-700' }}">{{ $booking->statusLabel() }}</span>
                                    @if($booking->cancelReasonLabel())
                                        <p class="mt-1 text-xs text-slate-500">{{ $booking->cancelReasonLabel() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($booking->booking_fee > 0)
                                        <p class="tabular-nums text-slate-800">{{ $rupiah($booking->booking_fee) }}</p>
                                        @if($booking->feeStatusLabel())
                                            <span class="mt-1 inline-block text-[11px] font-semibold px-1.5 py-0.5 rounded border {{ $feeBadge[$booking->fee_status] ?? '' }}">{{ $booking->feeStatusLabel() }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">Tanpa biaya</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $booking->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Tidak ada booking COD yang cocok dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($bookings->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">{{ $bookings->links() }}</div>
            @endif
        </section>
    @endif

    @if($orders)
        <section aria-labelledby="judulKirim" class="rounded-xl bg-white border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                <h2 id="judulKirim" class="text-sm font-bold text-slate-900">Pesanan Kirim</h2>
                <span class="text-xs text-slate-500">{{ $orders->total() }} transaksi</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2.5">Barang</th>
                            <th scope="col" class="px-4 py-2.5">Pembeli</th>
                            <th scope="col" class="px-4 py-2.5">Penjual</th>
                            <th scope="col" class="px-4 py-2.5">Status</th>
                            <th scope="col" class="px-4 py-2.5">Total</th>
                            <th scope="col" class="px-4 py-2.5">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($orders as $order)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $order->product->title ?? 'Barang dihapus' }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->courier }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-800">{{ $order->user->name ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->user->email ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ $shop($order->product) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full {{ $orderBadge[$order->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $order->statusLabel() }}</span>
                                    @if($order->isReleased())
                                        <p class="mt-1 text-xs text-slate-500">Dana cair · komisi {{ $rupiah($order->biaya_platform) }}</p>
                                    @elseif($order->status === \App\Models\Order::SHIPPED)
                                        <p class="mt-1 text-xs text-slate-500">Dana ditahan (escrow)</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums text-slate-800">{{ $rupiah($order->total_price) }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $order->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                {{ $status === 'dibatalkan' ? 'Pesanan Kirim belum punya fitur pembatalan.' : 'Tidak ada pesanan Kirim yang cocok dengan filter.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">{{ $orders->links() }}</div>
            @endif
        </section>
    @endif
</x-admin-layout>
