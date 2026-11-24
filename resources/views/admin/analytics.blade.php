@use('App\Services\AnalyticsService', 'A')
<x-admin-layout title="Analitik">

    @php
        $fmtValue = fn ($v, string $format) => $v === null ? '–' : match ($format) {
            'rupiah'  => A::rupiah($v),
            'percent' => A::percent($v),
            default   => number_format($v, 0, ',', '.'),
        };
        $dateId = fn ($d) => $d->locale('id')->translatedFormat('j M Y');
        $query = request()->only(['rentang', 'dari', 'sampai', 'grup']);
        $grupUrl = fn (string $g) => route('admin.analytics', [...$query, 'grup' => $g]);
        $kustom = $period['range'] === 'kustom';
    @endphp

    <!-- Judul & filter -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-3">
        <div>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900">Analitik</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $dateId($period['start']) }} – {{ $dateId($period['end']) }}
                <span class="text-slate-400">· dibandingkan dengan {{ $dateId($period['prevStart']) }} – {{ $dateId($period['prevEnd']) }}</span>
            </p>
        </div>
        <a href="{{ route('admin.analytics.export', $query) }}"
           class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
            Ekspor CSV
        </a>
    </div>

    <form method="GET" action="{{ route('admin.analytics') }}" x-data="{ range: @js($period['range']) }"
          class="rounded-xl bg-white border border-slate-200 p-3 sm:p-4 flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-3" aria-label="Filter rentang tanggal">
        <fieldset>
            <legend class="text-xs font-semibold text-slate-600 mb-1">Rentang</legend>
            <div class="flex flex-wrap gap-1.5">
                @foreach($ranges as $key => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="rentang" value="{{ $key }}" x-model="range" class="peer sr-only" @checked($period['range'] === $key)>
                        <span class="inline-flex items-center h-9 px-3 rounded-lg border text-sm font-semibold border-slate-300 text-slate-600 hover:bg-slate-50 peer-checked:bg-emerald-700 peer-checked:border-emerald-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <div class="flex gap-2" x-show="range === 'kustom'" @unless($kustom) style="display: none" @endunless>
            <label class="text-xs font-semibold text-slate-600">Dari
                <input type="date" name="dari" value="{{ $kustom ? $period['start']->format('Y-m-d') : request('dari') }}" max="{{ now()->format('Y-m-d') }}"
                       class="mt-1 block h-9 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" :disabled="range !== 'kustom'">
            </label>
            <label class="text-xs font-semibold text-slate-600">Sampai
                <input type="date" name="sampai" value="{{ $kustom ? $period['end']->format('Y-m-d') : request('sampai') }}" max="{{ now()->format('Y-m-d') }}"
                       class="mt-1 block h-9 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" :disabled="range !== 'kustom'">
            </label>
        </div>
        @if(request('grup'))
            <input type="hidden" name="grup" value="{{ request('grup') }}">
        @endif
        <button type="submit" class="h-9 px-4 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold">Terapkan</button>
    </form>

    @if($period['error'])
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ $period['error'] }} Menampilkan 30 hari terakhir.
        </div>
    @endif

    <!-- KPI -->
    <section aria-label="Indikator utama" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($kpis as $key => $kpi)
            @php
                $change = $kpi['change'];
                $up = $change && $change['value'] > 0;
                $down = $change && $change['value'] < 0;
                $isGood = ($up && $kpi['good']) || ($down && !$kpi['good']);
                $tone = !$change || $change['value'] == 0 ? 'bg-slate-100 text-slate-600' : ($isGood ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
                $amount = $change ? number_format(abs($change['value']), abs($change['value']) == floor(abs($change['value'])) ? 0 : 1, ',', '.') : null;
            @endphp
            <div class="rounded-xl bg-white border border-slate-200 p-3 sm:p-4" data-kpi="{{ $key }}">
                <p class="text-[11px] sm:text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</p>
                <p class="mt-1 text-lg sm:text-2xl font-bold tabular-nums text-slate-900 break-words">{{ $fmtValue($kpi['value'], $kpi['format']) }}</p>
                <p class="mt-1 flex flex-wrap items-center gap-1 text-xs">
                    @if($change)
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md font-semibold tabular-nums {{ $tone }}" data-change="{{ $up ? 'naik' : ($down ? 'turun' : 'tetap') }}">
                            <span aria-hidden="true">{{ $up ? '▲' : ($down ? '▼' : '■') }}</span>
                            <span class="sr-only">{{ $up ? 'naik' : ($down ? 'turun' : 'tetap') }}</span>
                            {{ $amount }}{{ $change['unit'] === '%' ? '%' : ' poin' }}
                        </span>
                        <span class="text-slate-400">vs periode lalu</span>
                    @else
                        <span class="text-slate-400">Belum ada pembanding</span>
                    @endif
                </p>
                @if($kpi['hint'])
                    <p class="mt-0.5 text-[11px] text-slate-500 truncate">{{ $kpi['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </section>

    <!-- Grafik -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- (a) Tren penjualan --}}
        <section class="lg:col-span-2 rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulSales">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="judulSales" class="font-semibold text-slate-900">Tren total penjualan</h2>
                <nav class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-semibold" aria-label="Kelompokkan per">
                    @foreach($groupings as $g => $label)
                        <a href="{{ $grupUrl($g) }}" class="px-2.5 py-1 rounded-md {{ $period['grouping'] === $g ? 'bg-white text-emerald-800 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                           @if($period['grouping'] === $g) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            @include('admin.analytics.chart', ['id' => 'sales', 'chart' => $charts['sales'], 'height' => 'h-64 sm:h-72', 'label' => 'Grafik garis total nilai penjualan per periode'])
            @include('admin.analytics.table', ['chart' => $charts['sales'], 'headers' => ['Periode', 'Nilai penjualan', 'COD selesai', 'Kirim selesai'],
                'rows' => collect($charts['sales']['labels'])->map(fn ($l, $i) => [$l, A::rupiah($charts['sales']['total'][$i]), $charts['sales']['codCount'][$i], $charts['sales']['kirimCount'][$i]])])
        </section>

        {{-- (b) Pendapatan per sumber --}}
        <section class="rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulRevenue">
            <h2 id="judulRevenue" class="font-semibold text-slate-900">Pendapatan platform per sumber</h2>
            @include('admin.analytics.chart', ['id' => 'revenue', 'chart' => $charts['revenue'], 'label' => 'Grafik batang bertumpuk pendapatan dari biaya booking, komisi Kirim, dan langganan'])
            @include('admin.analytics.table', ['chart' => $charts['revenue'], 'headers' => ['Sumber', 'Total'],
                'rows' => collect($charts['revenue']['totals'])->map(fn ($v, $k) => [$k, A::rupiah($v)])->values()])
        </section>

        {{-- (c) Penjualan per kategori --}}
        <section class="rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulKategori">
            <h2 id="judulKategori" class="font-semibold text-slate-900">Penjualan per kategori</h2>
            @include('admin.analytics.chart', ['id' => 'categories', 'chart' => $charts['categories'], 'label' => 'Grafik donat nilai penjualan per kategori'])
            @include('admin.analytics.table', ['chart' => $charts['categories'], 'headers' => ['Kategori', 'Nilai', 'Transaksi'],
                'rows' => collect($charts['categories']['labels'])->map(fn ($l, $i) => [$l, A::rupiah($charts['categories']['values'][$i]), $charts['categories']['counts'][$i]])])
        </section>

        {{-- (d) COD vs Kirim --}}
        <section class="rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulChannel">
            <h2 id="judulChannel" class="font-semibold text-slate-900">COD vs Kirim</h2>
            @unless($charts['channels']['empty'])
                @php $channelTotal = max(1, array_sum($charts['channels']['totals'])); @endphp
                <p class="mt-1 text-sm text-slate-600">
                    @foreach($charts['channels']['totals'] as $name => $n)
                        <span class="inline-flex items-center gap-1.5 mr-3"><span class="w-2.5 h-2.5 rounded-sm {{ $loop->first ? 'bg-[#047857]' : 'bg-[#d97706]' }}" aria-hidden="true"></span>{{ $name }}
                            <strong class="tabular-nums text-slate-900">{{ $n }}</strong> ({{ A::percent($n / $channelTotal * 100) }})</span>
                    @endforeach
                </p>
            @endunless
            @include('admin.analytics.chart', ['id' => 'channels', 'chart' => $charts['channels'], 'label' => 'Grafik garis jumlah transaksi selesai COD dan Kirim per periode'])
            @include('admin.analytics.table', ['chart' => $charts['channels'], 'headers' => ['Periode', 'COD', 'Kirim'],
                'rows' => collect($charts['channels']['labels'])->map(fn ($l, $i) => [$l, $charts['channels']['cod'][$i], $charts['channels']['kirim'][$i]])])
        </section>

        {{-- (e) 10 penjual teratas --}}
        <section class="rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulSeller">
            <h2 id="judulSeller" class="font-semibold text-slate-900">10 penjual teratas</h2>
            <p class="text-xs text-slate-500">Berdasarkan nilai transaksi selesai; rating dari ulasan pembeli.</p>
            @include('admin.analytics.chart', ['id' => 'sellers', 'chart' => $charts['sellers'], 'height' => 'h-80', 'label' => 'Grafik batang horizontal nilai penjualan 10 penjual teratas'])
            @include('admin.analytics.table', ['chart' => $charts['sellers'], 'headers' => ['Penjual', 'Nilai', 'Transaksi', 'Rating'],
                'rows' => collect($charts['sellers']['rows'])->map(fn ($s) => [$s['name'], A::rupiah($s['value']), $s['count'], $s['rating'] !== null ? '★ ' . A::decimal($s['rating']) . ' (' . $s['reviews'] . ' ulasan)' : '–'])])
        </section>

        {{-- (f) Funnel --}}
        <section class="rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulFunnel">
            <h2 id="judulFunnel" class="font-semibold text-slate-900">Funnel booking COD</h2>
            <p class="text-xs text-slate-500">Data "dilihat" belum direkam, jadi funnel dimulai dari booking yang dibuat pada periode ini.</p>
            @unless($charts['funnel']['empty'])
                <ol class="mt-3 space-y-2.5" data-funnel>
                    @foreach($charts['funnel']['labels'] as $i => $label)
                        <li>
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="text-slate-700">{{ $label }}</span>
                                <span class="tabular-nums text-slate-900 font-semibold">{{ number_format($charts['funnel']['values'][$i], 0, ',', '.') }}
                                    <span class="font-normal text-slate-500">({{ A::percent($charts['funnel']['percent'][$i]) }})</span></span>
                            </div>
                            <div class="mt-1 h-3 rounded bg-slate-100" aria-hidden="true">
                                <div class="h-3 rounded {{ ['bg-emerald-300', 'bg-emerald-500', 'bg-emerald-700'][$i] }}" style="width: {{ max(1, min(100, $charts['funnel']['percent'][$i])) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endunless
            @include('admin.analytics.insight', ['text' => $charts['funnel']['insight']])
        </section>

        {{-- (h) Pertumbuhan pengguna --}}
        <section class="lg:col-span-2 rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulUsers">
            <h2 id="judulUsers" class="font-semibold text-slate-900">Pertumbuhan pengguna: pembeli vs penjual</h2>
            @include('admin.analytics.chart', ['id' => 'users', 'chart' => $charts['users'], 'label' => 'Grafik garis pendaftar baru pembeli dan penjual per periode'])
            @include('admin.analytics.table', ['chart' => $charts['users'], 'headers' => ['Periode', 'Pembeli baru', 'Penjual baru'],
                'rows' => collect($charts['users']['labels'])->map(fn ($l, $i) => [$l, $charts['users']['buyers'][$i], $charts['users']['sellers'][$i]])])
        </section>

        {{-- (g) Heatmap hari x jam --}}
        <section class="lg:col-span-2 rounded-xl bg-white border border-slate-200 p-4" aria-labelledby="judulHeatmap">
            <h2 id="judulHeatmap" class="font-semibold text-slate-900">Waktu transaksi (hari × jam)</h2>
            <p class="text-xs text-slate-500">Booking COD dan pesanan Kirim yang dibuat, menurut waktu pembuatannya (WIB).</p>
            @unless($heatmap['empty'])
                @php
                    $shade = function (int $n) use ($heatmap) {
                        if ($n === 0) {
                            return 'bg-slate-100 text-transparent';
                        }
                        $r = $n / $heatmap['max'];

                        return match (true) {
                            $r > 0.8 => 'bg-emerald-800 text-white',
                            $r > 0.6 => 'bg-emerald-600 text-white',
                            $r > 0.4 => 'bg-emerald-400 text-emerald-950',
                            $r > 0.2 => 'bg-emerald-200 text-emerald-950',
                            default  => 'bg-emerald-100 text-emerald-900',
                        };
                    };
                @endphp
                <div class="relative mt-3 overflow-x-auto">
                    <table class="border-separate border-spacing-0.5 text-[10px] sm:text-xs tabular-nums min-w-[640px] w-full" data-heatmap>
                        <caption class="sr-only">Jumlah transaksi per hari dan jam</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-14"><span class="sr-only">Hari</span></th>
                                @for($h = 0; $h < 24; $h++)
                                    <th scope="col" class="font-normal text-slate-400"><span @if($h % 3) class="sr-only" @endif>{{ sprintf('%02d', $h) }}</span></th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($heatmap['rows'] as $row)
                                <tr>
                                    <th scope="row" class="pr-1 text-left font-semibold text-slate-600">{{ $row['day'] }}</th>
                                    @foreach($row['hours'] as $h => $n)
                                        <td class="h-6 sm:h-7 rounded text-center {{ $shade($n) }}" title="{{ $row['day'] }} {{ sprintf('%02d.00', $h) }}: {{ $n }} transaksi">{{ $n }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 flex items-center gap-1.5 text-[11px] text-slate-500" aria-hidden="true">
                    Sedikit
                    @foreach(['bg-emerald-100', 'bg-emerald-200', 'bg-emerald-400', 'bg-emerald-600', 'bg-emerald-800'] as $c)
                        <span class="w-4 h-3 rounded-sm {{ $c }}"></span>
                    @endforeach
                    Banyak
                </p>
            @endunless
            @include('admin.analytics.insight', ['text' => $heatmap['insight']])
        </section>
    </div>

    <p class="text-xs text-slate-500">
        Transaksi selesai = booking COD yang token QR-nya sudah dipindai + pesanan Kirim yang dananya sudah cair. Nilai transaksi = harga barang tanpa ongkir.
        Pendapatan = biaya booking (dibayar/hangus) + komisi Kirim saat dana cair + pembayaran langganan. Penjual terverifikasi dan produk aktif memakai status saat ini.
    </p>

    <script type="application/json" id="analytics-data">@json($charts)</script>
    @vite('resources/js/analytics.js')
</x-admin-layout>
