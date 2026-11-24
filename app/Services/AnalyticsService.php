<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Metrik dashboard analitik admin. Semua angka dari data asli, dihitung dengan query agregat
 * (GROUP BY per hari, lalu digabung ke minggu/bulan di PHP) supaya sama di MySQL dan SQLite.
 *
 * Definisi yang dipakai (mengikuti status yang sudah ada di kode, tanpa status baru):
 * - Transaksi selesai : booking COD status_cod "Completed" (waktu = updated_at, saat QR dipindai)
 *                       + pesanan Kirim yang dananya sudah cair (released_at terisi).
 * - Nilai transaksi   : harga barang (products.price untuk COD, orders.item_price untuk Kirim), tanpa ongkir.
 * - Pendapatan        : biaya booking (fee paid/forfeited, waktu = paid_at) + komisi Kirim
 *                       (biaya_platform saat cair) + langganan (subscription_payments.paid_at).
 * - Pembatalan        : booking COD yang dibuat pada periode lalu berstatus "Cancelled".
 */
class AnalyticsService
{
    public const RANGES = [
        '7'         => '7 hari',
        '30'        => '30 hari',
        '90'        => '90 hari',
        'bulan_ini' => 'Bulan ini',
        'kustom'    => 'Kustom',
    ];

    public const GROUPINGS = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'];

    public const MAX_DAYS = 366;

    public const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const FEE_COUNTED = [Booking::FEE_PAID, Booking::FEE_FORFEITED];

    private Carbon $start;
    private Carbon $end;

    /**
     * Rentang tanggal dari query string. Default 30 hari terakhir (termasuk hari ini).
     * Periode pembanding = rentang dengan panjang sama tepat sebelum periode ini.
     *
     * @return array{range: string, start: Carbon, end: Carbon, prevStart: Carbon, prevEnd: Carbon, days: int, grouping: string, error: ?string}
     */
    public static function period(array $query, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();
        $range = array_key_exists((string) ($query['rentang'] ?? ''), self::RANGES) ? (string) $query['rentang'] : '30';
        $error = null;

        if ($range === 'kustom') {
            $from = self::parseDate($query['dari'] ?? null);
            $to = self::parseDate($query['sampai'] ?? null);

            if (!$from || !$to) {
                $error = 'Isi tanggal mulai dan selesai untuk rentang kustom.';
            } elseif ($from->gt($to)) {
                $error = 'Tanggal mulai tidak boleh setelah tanggal selesai.';
            } elseif ($to->gt($now->copy()->endOfDay())) {
                $error = 'Tanggal selesai tidak boleh melewati hari ini.';
            } elseif ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
                $error = 'Rentang kustom maksimal ' . self::MAX_DAYS . ' hari.';
            }

            if ($error) {
                $range = '30';
            } else {
                [$start, $end] = [$from->startOfDay(), $to->endOfDay()];
            }
        }

        if ($range === 'bulan_ini') {
            [$start, $end] = [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];
        } elseif ($range !== 'kustom') {
            [$start, $end] = [$now->copy()->startOfDay()->subDays((int) $range - 1), $now->copy()->endOfDay()];
        }

        $days = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($days);

        $grouping = array_key_exists((string) ($query['grup'] ?? ''), self::GROUPINGS)
            ? (string) $query['grup']
            : ($days <= 31 ? 'harian' : ($days <= 120 ? 'mingguan' : 'bulanan'));

        return compact('range', 'start', 'end', 'prevStart', 'prevEnd', 'days', 'grouping', 'error');
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Semua data untuk halaman analitik.
     */
    public function build(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd, string $grouping): array
    {
        // Pesanan yang lewat batas konfirmasi dicairkan dulu supaya komisi & transaksi selesai akurat
        Order::releaseOverdue();

        $current = $this->summary($start, $end);
        $previous = $this->summary($prevStart, $prevEnd);

        $this->start = $start;
        $this->end = $end;
        $buckets = $this->buckets($grouping);

        $sales = $this->salesTrend($buckets, $grouping);

        return [
            'kpis'    => $this->kpis($current, $previous),
            'charts'  => [
                'sales'      => $sales,
                'revenue'    => $this->revenueBySource($buckets, $grouping),
                'categories' => $this->salesByCategory(),
                'channels'   => $this->channelMix($sales),
                'sellers'    => $this->topSellers(),
                'funnel'     => $this->funnel($current),
                'users'      => $this->userGrowth($buckets, $grouping, $previous),
            ],
            'heatmap' => $this->heatmap(),
        ];
    }

    // ------------------------------------------------------------------ Query dasar

    /** Booking COD selesai pada rentang, digabung dengan produknya. */
    private function completedCod(Carbon $from, Carbon $to): Builder
    {
        return Booking::query()
            ->join('products', 'products.id', '=', 'bookings.product_id')
            ->where('bookings.status_cod', 'Completed')
            ->whereBetween('bookings.updated_at', [$from, $to]);
    }

    /** Pesanan Kirim yang dananya cair pada rentang, digabung dengan produknya. */
    private function completedKirim(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->whereNotNull('orders.released_at')
            ->whereBetween('orders.released_at', [$from, $to]);
    }

    private function feeDateExpr(): string
    {
        return 'COALESCE(bookings.paid_at, bookings.created_at)';
    }

    /** Angka ringkasan satu periode (dipakai untuk KPI periode ini dan periode pembanding). */
    private function summary(Carbon $from, Carbon $to): array
    {
        $cod = $this->completedCod($from, $to)->selectRaw('COUNT(*) as n, COALESCE(SUM(products.price), 0) as v')->toBase()->first();
        $kirim = $this->completedKirim($from, $to)->selectRaw('COUNT(*) as n, COALESCE(SUM(orders.item_price), 0) as v')->toBase()->first();

        $fees = (int) Booking::whereIn('fee_status', self::FEE_COUNTED)
            ->whereRaw($this->feeDateExpr() . ' BETWEEN ? AND ?', [$from, $to])->sum('booking_fee');
        $commission = (int) Order::whereNotNull('released_at')->whereBetween('released_at', [$from, $to])->sum('biaya_platform');
        $subscriptions = (int) SubscriptionPayment::whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to])->sum('amount');

        $bookings = Booking::whereBetween('created_at', [$from, $to])->selectRaw(
            "COUNT(*) as dibuat,
             COALESCE(SUM(CASE WHEN paid_at IS NOT NULL OR fee_status IN ('paid', 'forfeited', 'refunded') OR status_cod = 'Completed' THEN 1 ELSE 0 END), 0) as dibayar,
             COALESCE(SUM(CASE WHEN status_cod = 'Completed' THEN 1 ELSE 0 END), 0) as selesai,
             COALESCE(SUM(CASE WHEN status_cod = 'Cancelled' THEN 1 ELSE 0 END), 0) as batal"
        )->toBase()->first();

        $newUsers = User::whereBetween('created_at', [$from, $to])->whereIn('role', ['pembeli', 'penjual'])
            ->selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');

        $completed = (int) $cod->n + (int) $kirim->n;
        $gmv = (int) $cod->v + (int) $kirim->v;
        $created = (int) $bookings->dibuat;

        return [
            'gmv'             => $gmv,
            'completed'       => $completed,
            'codCompleted'    => (int) $cod->n,
            'kirimCompleted'  => (int) $kirim->n,
            'revenue'         => $fees + $commission + $subscriptions,
            'newBuyers'       => (int) ($newUsers['pembeli'] ?? 0),
            'newSellers'      => (int) ($newUsers['penjual'] ?? 0),
            // Tidak ada tanggal verifikasi: penjual yang statusnya terverifikasi & sudah terdaftar di akhir periode
            'verifiedSellers' => User::where('role', 'penjual')->where('seller_status', 'verified')
                ->whereNull('deactivated_at')->where('created_at', '<=', $to)->count(),
            // Produk yang statusnya aktif & sudah diunggah di akhir periode
            'activeProducts'  => Product::active()->where('created_at', '<=', $to)->count(),
            'bookingsCreated' => $created,
            'bookingsPaid'    => (int) $bookings->dibayar,
            'bookingsDone'    => (int) $bookings->selesai,
            'bookingsCancel'  => (int) $bookings->batal,
            'cancelRate'      => $created > 0 ? round((int) $bookings->batal / $created * 100, 1) : null,
            'aov'             => $completed > 0 ? (int) round($gmv / $completed) : null,
        ];
    }

    // ------------------------------------------------------------------ KPI

    private function kpis(array $cur, array $prev): array
    {
        $kpi = fn (string $label, $value, $previous, string $format, bool $higherIsBetter = true, ?string $hint = null) => [
            'label'    => $label,
            'value'    => $value,
            'previous' => $previous,
            'format'   => $format,
            'change'   => self::change($value, $previous, $format === 'percent'),
            'good'     => $higherIsBetter,
            'hint'     => $hint,
        ];

        return [
            'gmv'             => $kpi('Total nilai transaksi', $cur['gmv'], $prev['gmv'], 'rupiah'),
            'completed'       => $kpi('Transaksi selesai', $cur['completed'], $prev['completed'], 'number', true,
                $cur['codCompleted'] . ' COD · ' . $cur['kirimCompleted'] . ' Kirim'),
            'revenue'         => $kpi('Pendapatan platform', $cur['revenue'], $prev['revenue'], 'rupiah'),
            'newUsers'        => $kpi('Pengguna baru', $cur['newBuyers'] + $cur['newSellers'], $prev['newBuyers'] + $prev['newSellers'], 'number', true,
                $cur['newBuyers'] . ' pembeli · ' . $cur['newSellers'] . ' penjual'),
            'verifiedSellers' => $kpi('Penjual terverifikasi', $cur['verifiedSellers'], $prev['verifiedSellers'], 'number', true, 'Total di akhir periode'),
            'activeProducts'  => $kpi('Produk aktif', $cur['activeProducts'], $prev['activeProducts'], 'number', true, 'Total di akhir periode'),
            'cancelRate'      => $kpi('Tingkat pembatalan', $cur['cancelRate'], $prev['cancelRate'], 'percent', false,
                $cur['bookingsCancel'] . ' dari ' . $cur['bookingsCreated'] . ' booking COD'),
            'aov'             => $kpi('Rata-rata nilai transaksi', $cur['aov'], $prev['aov'], 'rupiah'),
        ];
    }

    /**
     * Perubahan dibanding periode lalu. Angka biasa: persen. Persentase (tingkat pembatalan): selisih poin.
     *
     * @return array{value: float, unit: string}|null  null bila tidak bisa dibandingkan (periode lalu nol/kosong)
     */
    public static function change(int|float|null $current, int|float|null $previous, bool $points = false): ?array
    {
        if ($current === null || $previous === null) {
            return null;
        }
        if ($points) {
            return ['value' => round($current - $previous, 1), 'unit' => 'poin'];
        }
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? ['value' => 0.0, 'unit' => '%'] : null;
        }

        return ['value' => round(($current - $previous) / abs($previous) * 100, 1), 'unit' => '%'];
    }

    // ------------------------------------------------------------------ Pengelompokan waktu

    /** Kunci & label tiap titik grafik untuk periode berjalan. */
    private function buckets(string $grouping): array
    {
        $buckets = [];
        for ($day = $this->start->copy()->startOfDay(); $day->lte($this->end); $day->addDay()) {
            $key = $this->bucketKey($day->format('Y-m-d'), $grouping);
            $buckets[$key] ??= match ($grouping) {
                'bulanan'  => $day->locale('id')->translatedFormat('M Y'),
                'mingguan' => 'Mgg ' . $day->locale('id')->translatedFormat('j M'),
                default    => $day->locale('id')->translatedFormat('j M'),
            };
        }

        return $buckets;
    }

    private function bucketKey(string $date, string $grouping): string
    {
        return match ($grouping) {
            'bulanan'  => substr($date, 0, 7),
            'mingguan' => max(Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->format('Y-m-d'), $this->start->format('Y-m-d')),
            default    => $date,
        };
    }

    /**
     * Hasil query per hari (kolom d = tanggal) dijumlahkan ke kunci bucket.
     *
     * @return array<string, array<string, int>>
     */
    private function rollUp(iterable $rows, array $columns, array $buckets, string $grouping): array
    {
        $out = array_fill_keys(array_keys($buckets), array_fill_keys($columns, 0));
        foreach ($rows as $row) {
            $key = $this->bucketKey(substr((string) $row->d, 0, 10), $grouping);
            if (isset($out[$key])) {
                foreach ($columns as $c) {
                    $out[$key][$c] += (int) $row->{$c};
                }
            }
        }

        return $out;
    }

    private function daily(Builder $query, string $dateExpr, string $aggregates): Collection
    {
        return $query->selectRaw("DATE({$dateExpr}) as d, {$aggregates}")->groupByRaw("DATE({$dateExpr})")->toBase()->get();
    }

    private static function column(array $rolled, string $column): array
    {
        return array_values(array_map(fn ($bucket) => $bucket[$column], $rolled));
    }

    // ------------------------------------------------------------------ Grafik

    private function salesTrend(array $buckets, string $grouping): array
    {
        $cod = $this->rollUp($this->daily($this->completedCod($this->start, $this->end), 'bookings.updated_at', 'COUNT(*) as n, SUM(products.price) as v'), ['n', 'v'], $buckets, $grouping);
        $kirim = $this->rollUp($this->daily($this->completedKirim($this->start, $this->end), 'orders.released_at', 'COUNT(*) as n, SUM(orders.item_price) as v'), ['n', 'v'], $buckets, $grouping);

        $total = array_map(fn ($k) => $cod[$k]['v'] + $kirim[$k]['v'], array_keys($buckets));
        $codCount = self::column($cod, 'n');
        $kirimCount = self::column($kirim, 'n');
        $sum = array_sum($total);

        $insight = null;
        if ($sum > 0) {
            $peak = array_keys($total, max($total))[0];
            $insight = 'Total penjualan ' . self::rupiah($sum) . ' dari ' . (array_sum($codCount) + array_sum($kirimCount))
                . ' transaksi selesai; puncaknya pada ' . array_values($buckets)[$peak] . ' (' . self::rupiah($total[$peak]) . ').';
        }

        return [
            'labels'     => array_values($buckets),
            'total'      => $total,
            'codCount'   => $codCount,
            'kirimCount' => $kirimCount,
            'empty'      => $sum === 0,
            'insight'    => $insight,
        ];
    }

    private function revenueBySource(array $buckets, string $grouping): array
    {
        $fees = $this->rollUp($this->daily(
            Booking::whereIn('fee_status', self::FEE_COUNTED)->whereRaw($this->feeDateExpr() . ' BETWEEN ? AND ?', [$this->start, $this->end]),
            $this->feeDateExpr(), 'SUM(booking_fee) as v'
        ), ['v'], $buckets, $grouping);
        $commission = $this->rollUp($this->daily(
            Order::whereNotNull('released_at')->whereBetween('released_at', [$this->start, $this->end]),
            'released_at', 'SUM(biaya_platform) as v'
        ), ['v'], $buckets, $grouping);
        $subs = $this->rollUp($this->daily(
            SubscriptionPayment::whereNotNull('paid_at')->whereBetween('paid_at', [$this->start, $this->end]),
            'paid_at', 'SUM(amount) as v'
        ), ['v'], $buckets, $grouping);

        $series = [
            'Biaya booking' => self::column($fees, 'v'),
            'Komisi Kirim'  => self::column($commission, 'v'),
            'Langganan'     => self::column($subs, 'v'),
        ];
        $totals = array_map('array_sum', $series);
        $sum = array_sum($totals);

        $insight = null;
        if ($sum > 0) {
            $top = array_keys($totals, max($totals))[0];
            $insight = 'Sumber pendapatan terbesar adalah ' . lcfirst($top) . ' (' . self::rupiah($totals[$top]) . ', '
                . self::percent($totals[$top] / $sum * 100) . ' dari total ' . self::rupiah($sum) . ').';
        }

        return ['labels' => array_values($buckets), 'series' => $series, 'totals' => $totals, 'empty' => $sum === 0, 'insight' => $insight];
    }

    private function salesByCategory(): array
    {
        $rows = collect()
            ->concat($this->completedCod($this->start, $this->end)->selectRaw('products.kategori as k, COUNT(*) as n, SUM(products.price) as v')->groupBy('products.kategori')->toBase()->get())
            ->concat($this->completedKirim($this->start, $this->end)->selectRaw('products.kategori as k, COUNT(*) as n, SUM(orders.item_price) as v')->groupBy('products.kategori')->toBase()->get());

        $labels = collect(Category::options())->map(fn ($c) => $c['label']);
        $merged = $rows->groupBy(fn ($r) => (string) ($r->k ?: 'Lainnya'))
            ->map(fn ($g, $k) => ['label' => $labels[$k] ?? $k, 'count' => (int) $g->sum('n'), 'value' => (int) $g->sum('v')])
            ->sortByDesc('value')->values();

        // Maksimal 6 irisan berwarna (palet tervalidasi); sisanya digabung "Lainnya"
        if ($merged->count() > 6) {
            $rest = $merged->slice(5);
            $merged = $merged->take(5)->push(['label' => 'Lainnya', 'count' => $rest->sum('count'), 'value' => $rest->sum('value')]);
        }

        $sum = $merged->sum('value');
        $top = $merged->first();

        return [
            'labels'  => $merged->pluck('label')->all(),
            'values'  => $merged->pluck('value')->all(),
            'counts'  => $merged->pluck('count')->all(),
            'empty'   => $sum === 0,
            'insight' => $sum > 0
                ? 'Kategori terlaris adalah ' . $top['label'] . ' dengan ' . self::rupiah($top['value']) . ' dari ' . $top['count'] . ' transaksi ('
                    . self::percent($top['value'] / $sum * 100) . ' dari nilai penjualan).'
                : null,
        ];
    }

    private function channelMix(array $sales): array
    {
        $cod = array_sum($sales['codCount']);
        $kirim = array_sum($sales['kirimCount']);
        $total = $cod + $kirim;

        $insight = null;
        if ($total > 0) {
            $insight = $cod === $kirim
                ? "COD dan Kirim seimbang, masing-masing {$cod} transaksi selesai."
                : ($cod > $kirim ? 'COD' : 'Kirim') . ' mendominasi dengan ' . self::percent(max($cod, $kirim) / $total * 100)
                    . ' transaksi selesai (' . max($cod, $kirim) . " dari {$total}).";
        }

        return [
            'labels'  => $sales['labels'],
            'cod'     => $sales['codCount'],
            'kirim'   => $sales['kirimCount'],
            'totals'  => ['COD' => $cod, 'Kirim' => $kirim],
            'empty'   => $total === 0,
            'insight' => $insight,
        ];
    }

    private function topSellers(): array
    {
        $rows = collect()
            ->concat($this->completedCod($this->start, $this->end)->selectRaw('products.user_id as s, COUNT(*) as n, SUM(products.price) as v')->groupBy('products.user_id')->toBase()->get())
            ->concat($this->completedKirim($this->start, $this->end)->selectRaw('products.user_id as s, COUNT(*) as n, SUM(orders.item_price) as v')->groupBy('products.user_id')->toBase()->get());

        $top = $rows->groupBy('s')
            ->map(fn ($g, $id) => ['id' => (int) $id, 'count' => (int) $g->sum('n'), 'value' => (int) $g->sum('v')])
            ->sortBy([['value', 'desc'], ['count', 'desc'], ['id', 'asc']])->take(10)->values();

        $ids = $top->pluck('id');
        $names = User::whereIn('id', $ids)->get(['id', 'name', 'nama_toko'])->mapWithKeys(fn ($u) => [$u->id => $u->nama_toko ?: $u->name]);
        // Rating dari fitur ulasan: satu query agregat untuk 10 penjual sekaligus
        $ratings = Review::whereIn('seller_id', $ids)->selectRaw('seller_id, AVG(rating) as avg, COUNT(*) as n')
            ->groupBy('seller_id')->toBase()->get()->keyBy('seller_id');

        $sellers = $top->map(function ($s) use ($names, $ratings) {
            $r = $ratings->get($s['id']);

            return [
                ...$s,
                'name'    => $names[$s['id']] ?? 'Penjual #' . $s['id'],
                'rating'  => $r ? round((float) $r->avg, 1) : null,
                'reviews' => $r ? (int) $r->n : 0,
            ];
        });

        $first = $sellers->first();

        return [
            'labels'  => $sellers->map(fn ($s) => $s['name'] . ($s['rating'] !== null ? ' (★' . self::decimal($s['rating']) . ')' : ''))->all(),
            'values'  => $sellers->pluck('value')->all(),
            'rows'    => $sellers->all(),
            'empty'   => $sellers->isEmpty(),
            'insight' => $first
                ? 'Penjual teratas adalah ' . $first['name'] . ' dengan ' . self::rupiah($first['value']) . ' dari ' . $first['count'] . ' transaksi'
                    . ($first['rating'] !== null ? ', rating ' . self::decimal($first['rating']) . ' dari ' . $first['reviews'] . ' ulasan' : '') . '.'
                : null,
        ];
    }

    private function funnel(array $cur): array
    {
        $created = $cur['bookingsCreated'];
        $steps = ['Booking dibuat' => $created, 'Biaya booking dibayar' => $cur['bookingsPaid'], 'COD selesai' => $cur['bookingsDone']];

        $insight = null;
        if ($created > 0) {
            $insight = "Dari {$created} booking COD, " . self::percent($cur['bookingsPaid'] / $created * 100) . ' dibayar dan '
                . self::percent($cur['bookingsDone'] / $created * 100) . " selesai; {$cur['bookingsCancel']} dibatalkan.";
        }

        return [
            'labels'  => array_keys($steps),
            'values'  => array_values($steps),
            'percent' => array_map(fn ($v) => $created > 0 ? round($v / $created * 100, 1) : 0, array_values($steps)),
            'empty'   => $created === 0,
            'insight' => $insight,
        ];
    }

    /** Transaksi dibuat (booking COD + pesanan Kirim) per hari x jam. */
    private function heatmap(): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach (['bookings' => Booking::query(), 'orders' => Order::query()] as $table => $query) {
            $wd = $this->weekdayExpr("{$table}.created_at");
            $hr = $this->hourExpr("{$table}.created_at");
            $rows = $query->whereBetween("{$table}.created_at", [$this->start, $this->end])
                ->selectRaw("{$wd} as wd, {$hr} as hr, COUNT(*) as n")
                ->groupByRaw("{$wd}, {$hr}")
                ->toBase()->get();
            foreach ($rows as $r) {
                $grid[(int) $r->wd][(int) $r->hr] += (int) $r->n;
            }
        }

        $max = max(array_map('max', $grid));
        $insight = null;
        if ($max > 0) {
            $peakDay = $peakHour = 0;
            foreach ($grid as $wd => $hours) {
                if (($hr = array_search($max, $hours, true)) !== false) {
                    [$peakDay, $peakHour] = [$wd, $hr];
                    break;
                }
            }
            $dayTotals = array_map('array_sum', $grid);
            $busiestDay = array_keys($dayTotals, max($dayTotals))[0];
            $insight = 'Hari tersibuk adalah ' . self::DAYS[$busiestDay] . ' (' . $dayTotals[$busiestDay] . ' transaksi); jam paling ramai '
                . self::DAYS[$peakDay] . ' pukul ' . sprintf('%02d.00–%02d.00', $peakHour, ($peakHour + 1) % 24) . " dengan {$max} transaksi.";
        }

        // Urutan tampilan Senin..Minggu
        return [
            'rows'    => array_map(fn ($d) => ['day' => self::DAYS[$d], 'hours' => $grid[$d]], [1, 2, 3, 4, 5, 6, 0]),
            'max'     => $max,
            'empty'   => $max === 0,
            'insight' => $insight,
        ];
    }

    private function userGrowth(array $buckets, string $grouping, array $previous): array
    {
        $rows = $this->rollUp($this->daily(
            User::whereBetween('created_at', [$this->start, $this->end]),
            'created_at',
            "SUM(CASE WHEN role = 'pembeli' THEN 1 ELSE 0 END) as b, SUM(CASE WHEN role = 'penjual' THEN 1 ELSE 0 END) as s"
        ), ['b', 's'], $buckets, $grouping);

        $buyers = self::column($rows, 'b');
        $sellers = self::column($rows, 's');
        $total = array_sum($buyers) + array_sum($sellers);
        $change = self::change($total, $previous['newBuyers'] + $previous['newSellers']);

        $insight = null;
        if ($total > 0) {
            $insight = 'Ada ' . array_sum($buyers) . ' pembeli baru dan ' . array_sum($sellers) . ' penjual baru'
                . match (true) {
                    $change === null      => ' (periode sebelumnya belum ada pendaftar).',
                    $change['value'] == 0 => ', sama dengan periode sebelumnya.',
                    default               => ', ' . ($change['value'] > 0 ? 'naik ' : 'turun ') . self::percent(abs($change['value'])) . ' dibanding periode sebelumnya.',
                };
        }

        return ['labels' => array_values($buckets), 'buyers' => $buyers, 'sellers' => $sellers, 'empty' => $total === 0, 'insight' => $insight];
    }

    // ------------------------------------------------------------------ Ekspresi tanggal per database

    private function hourExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', {$column}) AS INTEGER)"
            : "HOUR({$column})";
    }

    /** 0 = Minggu ... 6 = Sabtu */
    private function weekdayExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%w', {$column}) AS INTEGER)"
            : "(DAYOFWEEK({$column}) - 1)";
    }

    // ------------------------------------------------------------------ Format Indonesia

    public static function rupiah(int|float $n): string
    {
        return 'Rp' . number_format($n, 0, ',', '.');
    }

    public static function decimal(float $n): string
    {
        return number_format($n, 1, ',', '.');
    }

    public static function percent(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', '.'), '0'), ',') . '%';
    }
}
