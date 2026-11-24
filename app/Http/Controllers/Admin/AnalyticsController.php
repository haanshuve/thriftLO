<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Order;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Dashboard Analitik (Business Intelligence) panel admin. Rute dilindungi middleware auth + admin.
class AnalyticsController extends Controller
{
    public const CSV_COLUMNS = [
        'tanggal', 'jenis', 'id_transaksi', 'status', 'produk', 'kategori', 'penjual',
        'pembeli_email', 'nilai_barang', 'ongkir', 'biaya_booking', 'komisi_platform',
    ];

    public function index(Request $request, AnalyticsService $analytics)
    {
        $period = AnalyticsService::period($request->query());

        return view('admin.analytics', [
            'period'    => $period,
            'ranges'    => AnalyticsService::RANGES,
            'groupings' => AnalyticsService::GROUPINGS,
            ...$analytics->build($period['start'], $period['end'], $period['prevStart'], $period['prevEnd'], $period['grouping']),
        ]);
    }

    /**
     * Ringkasan transaksi (booking COD + pesanan Kirim) yang DIBUAT pada rentang terpilih.
     * Tanpa data sensitif: tidak ada KTP/alamat/token QR, email pembeli disamarkan.
     */
    public function export(Request $request): StreamedResponse
    {
        $period = AnalyticsService::period($request->query());
        [$start, $end] = [$period['start'], $period['end']];
        $categories = collect(Category::options())->map(fn ($c) => $c['label']);

        $filename = 'thriftlo-transaksi-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($start, $end, $categories) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($out, self::CSV_COLUMNS);

            $product = fn ($p) => [
                $p?->title ?? '(barang dihapus)',
                $categories[$p?->kategori] ?? ($p?->kategori ?? '-'),
                $p?->user ? ($p->user->nama_toko ?: $p->user->name) : '-',
            ];

            Booking::with(['product:id,title,kategori,user_id,price', 'product.user:id,name,nama_toko', 'user:id,email'])
                ->whereBetween('created_at', [$start, $end])->orderBy('id')
                ->lazy(500)->each(function (Booking $b) use ($out, $product) {
                    $counted = in_array($b->fee_status, [Booking::FEE_PAID, Booking::FEE_FORFEITED], true);
                    self::row($out, [
                        $b->created_at->format('Y-m-d H:i'), 'COD', 'COD-' . $b->id, $b->statusLabel(),
                        ...$product($b->product), self::maskEmail($b->user?->email),
                        (int) ($b->product?->price ?? 0), 0, $counted ? (int) $b->booking_fee : 0, 0,
                    ]);
                });

            Order::with(['product:id,title,kategori,user_id', 'product.user:id,name,nama_toko', 'user:id,email'])
                ->whereBetween('created_at', [$start, $end])->orderBy('id')
                ->lazy(500)->each(function (Order $o) use ($out, $product) {
                    self::row($out, [
                        $o->created_at->format('Y-m-d H:i'), 'Kirim', 'KRM-' . $o->id, $o->statusLabel(),
                        ...$product($o->product), self::maskEmail($o->user?->email),
                        (int) $o->item_price, (int) $o->shipping_cost, 0, $o->isReleased() ? (int) $o->biaya_platform : 0,
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Teks yang diawali = + - @ dianggap rumus oleh Excel: diberi apostrof supaya tidak dieksekusi
    private static function row($out, array $values): void
    {
        fputcsv($out, array_map(
            fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v,
            $values
        ));
    }

    // "raihan@gmail.com" -> "ra****@gmail.com"
    public static function maskEmail(?string $email): string
    {
        if (!$email || !str_contains($email, '@')) {
            return '-';
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2) . str_repeat('*', max(3, mb_strlen($local) - 2)) . '@' . $domain;
    }
}
