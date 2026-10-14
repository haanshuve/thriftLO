<x-admin-layout title="Laporan">

    @php
        $num = fn ($n) => number_format($n, 0, ',', '.');
        $rupiah = fn ($n) => 'Rp' . number_format($n, 0, ',', '.');
        $columns = [
            'newBuyers'     => ['Pembeli baru', $num],
            'newSellers'    => ['Penjual baru', $num],
            'newProducts'   => ['Produk baru', $num],
            'codBookings'   => ['Booking COD', $num],
            'cancellations' => ['Batal COD', $num],
            'kirimOrders'   => ['Pesanan Kirim', $num],
            'bookingFees'   => ['Biaya booking', $rupiah],
            'commission'    => ['Komisi Kirim', $rupiah],
        ];
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900">Laporan</h1>
            <p class="text-sm text-slate-500 mt-0.5">Aktivitas pengguna dan transaksi per periode. Komisi dihitung saat dana Kirim cair; biaya booking tidak termasuk yang dikembalikan.</p>
        </div>
        <nav class="inline-flex rounded-lg bg-white border border-slate-200 p-1 shrink-0" aria-label="Pilih periode">
            @foreach($periods as $key => [$label])
                <a href="{{ route('admin.reports', ['periode' => $key]) }}"
                   class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $period === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}"
                   @if($period === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <!-- Total periode -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-admin.stat label="Pengguna baru" :value="$num($totals['newBuyers'] + $totals['newSellers'])" :hint="$num($totals['newBuyers']) . ' pembeli · ' . $num($totals['newSellers']) . ' penjual'" />
        <x-admin.stat label="Transaksi" :value="$num($totals['codBookings'] + $totals['kirimOrders'])" :hint="$num($totals['codBookings']) . ' COD · ' . $num($totals['kirimOrders']) . ' Kirim'" />
        <x-admin.stat label="Biaya booking COD" :value="$rupiah($totals['bookingFees'])" tone="emerald" />
        <x-admin.stat label="Komisi Kirim" :value="$rupiah($totals['commission'])" tone="emerald" />
    </div>

    <section aria-labelledby="judulTabel" class="rounded-xl bg-white border border-slate-200 overflow-hidden">
        <h2 id="judulTabel" class="px-4 py-3 border-b border-slate-200 text-sm font-bold text-slate-900">Rincian {{ $period === 'bulanan' ? 'per bulan' : 'per hari' }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-left">{{ $period === 'bulanan' ? 'Bulan' : 'Tanggal' }}</th>
                        @foreach($columns as [$label])
                            <th scope="col" class="px-4 py-2.5 text-right">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 tabular-nums">
                    @foreach($rows as $row)
                        <tr class="{{ collect($row)->except('label')->sum() === 0 ? 'text-slate-400' : 'text-slate-800' }}">
                            <th scope="row" class="px-4 py-2 text-left font-medium whitespace-nowrap">{{ $row['label'] }}</th>
                            @foreach($columns as $key => [, $format])
                                <td class="px-4 py-2 text-right">{{ $format($row[$key]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 font-bold text-slate-900 tabular-nums border-t border-slate-200">
                    <tr>
                        <th scope="row" class="px-4 py-2.5 text-left">Total</th>
                        @foreach($columns as $key => [, $format])
                            <td class="px-4 py-2.5 text-right">{{ $format($totals[$key]) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</x-admin-layout>
