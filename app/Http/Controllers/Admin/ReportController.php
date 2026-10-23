<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    // [label, jumlah periode, format kunci, format tampilan]
    public const PERIODS = [
        'harian'  => ['Harian (30 hari)', 30, 'Y-m-d', 'd M Y'],
        'bulanan' => ['Bulanan (12 bulan)', 12, 'Y-m', 'F Y'],
    ];

    public function index(Request $request)
    {
        $period = array_key_exists($request->query('periode'), self::PERIODS) ? $request->query('periode') : 'harian';
        [, $length, $keyFormat, $labelFormat] = self::PERIODS[$period];

        $monthly = $period === 'bulanan';
        $start = $monthly ? now()->startOfMonth()->subMonths($length - 1) : now()->startOfDay()->subDays($length - 1);

        Order::releaseOverdue();

        // Data per periode dikelompokkan di PHP supaya sama di MySQL (Railway) dan SQLite (test)
        $bucket = fn (Collection $rows, string $column = 'created_at') => $rows
            ->groupBy(fn ($row) => Carbon::parse($row->{$column})->format($keyFormat));

        $users = $bucket(User::where('created_at', '>=', $start)->get(['role', 'created_at']));
        $products = $bucket(Product::where('created_at', '>=', $start)->get(['created_at']));
        $bookings = $bucket(Booking::where('created_at', '>=', $start)->get(['status_cod', 'booking_fee', 'fee_status', 'created_at']));
        $orders = $bucket(Order::where('created_at', '>=', $start)->get(['created_at']));
        $released = $bucket(Order::where('released_at', '>=', $start)->get(['biaya_platform', 'released_at']), 'released_at');

        $rows = collect();
        for ($i = 0; $i < $length; $i++) {
            $date = $monthly ? $start->copy()->addMonths($i) : $start->copy()->addDays($i);
            $key = $date->format($keyFormat);
            $b = $bookings->get($key, collect());
            $u = $users->get($key, collect());

            $rows->push([
                'label'         => $date->locale('id')->translatedFormat($labelFormat),
                'newBuyers'     => $u->where('role', 'pembeli')->count(),
                'newSellers'    => $u->where('role', 'penjual')->count(),
                'newProducts'   => $products->get($key, collect())->count(),
                'codBookings'   => $b->count(),
                'cancellations' => $b->where('status_cod', 'Cancelled')->count(),
                'kirimOrders'   => $orders->get($key, collect())->count(),
                'bookingFees'   => (int) $b->whereNotNull('fee_status')->sum('booking_fee'),
                'commission'    => (int) $released->get($key, collect())->sum('biaya_platform'),
            ]);
        }

        // Terbaru di atas
        $rows = $rows->reverse()->values();

        $totals = collect(array_keys($rows->first()))
            ->reject(fn ($k) => $k === 'label')
            ->mapWithKeys(fn ($k) => [$k => $rows->sum($k)])
            ->all();

        return view('admin.reports', [
            'period'  => $period,
            'periods' => self::PERIODS,
            'rows'    => $rows,
            'totals'  => $totals,
        ]);
    }
}
