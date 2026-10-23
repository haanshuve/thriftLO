<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // Pesanan yang lewat batas konfirmasi dicairkan dulu supaya angka komisi akurat
        Order::releaseOverdue();

        $usersByRole = User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');

        $pendingSellers = User::where('role', 'penjual')->where('seller_status', 'pending')->oldest()->get();

        $stats = [
            'users'           => (int) $usersByRole->sum(),
            'pembeli'         => (int) ($usersByRole['pembeli'] ?? 0),
            'penjual'         => (int) ($usersByRole['penjual'] ?? 0),
            'admin'           => (int) ($usersByRole['admin'] ?? 0),
            'deactivated'     => User::whereNotNull('deactivated_at')->count(),
            'pendingSellers'  => $pendingSellers->count(),
            'activeProducts'  => Product::active()->count(),
            'takenDown'       => Product::whereNotNull('taken_down_at')->count(),
            'codBookings'     => Booking::count(),
            'kirimOrders'     => Order::count(),
            // Komisi hanya dari pesanan yang dananya sudah cair ke penjual
            'commission'      => (int) Order::whereNotNull('released_at')->sum('biaya_platform'),
            // Biaya booking milik platform, termasuk booking yang dibatalkan; refund pada data lama tidak dihitung
            'bookingFees'     => (int) Booking::whereIn('fee_status', [Booking::FEE_PAID, Booking::FEE_FORFEITED])->sum('booking_fee'),
            'cancellations'   => Booking::where('status_cod', 'Cancelled')->count(),
        ];

        return view('admin.dashboard', [
            'stats'          => $stats,
            'pendingSellers' => $pendingSellers->take(5),
        ]);
    }
}
