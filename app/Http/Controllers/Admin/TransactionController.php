<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public const TYPES = ['semua' => 'Semua', 'cod' => 'COD', 'kirim' => 'Kirim'];

    // Status disamakan untuk COD dan Kirim supaya satu filter berlaku untuk keduanya
    public const STATUSES = ['semua' => 'Semua status', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    private const PER_PAGE = 15;

    public function index(Request $request)
    {
        $type = array_key_exists($request->query('jenis'), self::TYPES) ? $request->query('jenis') : 'semua';
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : 'semua';
        $search = trim((string) $request->query('q', ''));

        Order::releaseOverdue();

        $bookings = $type === 'kirim' ? null : $this->filtered(Booking::query(), $search)
            ->with(['product.user', 'user'])
            ->when($status === 'berjalan', fn ($q) => $q->whereIn('status_cod', Booking::ACTIVE_STATUSES))
            ->when($status === 'selesai', fn ($q) => $q->where('status_cod', 'Completed'))
            ->when($status === 'dibatalkan', fn ($q) => $q->where('status_cod', 'Cancelled'))
            ->latest()
            ->paginate(self::PER_PAGE, ['*'], 'hal_cod')
            ->withQueryString();

        $orders = $type === 'cod' ? null : $this->filtered(Order::query(), $search)
            ->with(['product.user', 'user'])
            ->when($status === 'berjalan', fn ($q) => $q->whereIn('status', [Order::AWAITING_SHIPMENT, Order::SHIPPED]))
            ->when($status === 'selesai', fn ($q) => $q->where('status', Order::COMPLETED))
            // Pesanan Kirim belum punya alur pembatalan
            ->when($status === 'dibatalkan', fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->paginate(self::PER_PAGE, ['*'], 'hal_kirim')
            ->withQueryString();

        return view('admin.transactions', [
            'type'     => $type,
            'status'   => $status,
            'search'   => $search,
            'bookings' => $bookings,
            'orders'   => $orders,
            'types'    => self::TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    // Cari berdasarkan nama barang, pembeli (nama/email), penjual (nama/toko), atau token QR COD
    private function filtered(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $like = '%' . $search . '%';

        return $query->where(function (Builder $q) use ($like, $query) {
            $q->whereHas('product', fn ($p) => $p->where('title', 'like', $like)
                    ->orWhereHas('user', fn ($s) => $s->where('name', 'like', $like)->orWhere('nama_toko', 'like', $like)))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));

            if ($query->getModel() instanceof Booking) {
                $q->orWhere('qr_token', 'like', $like);
            }
        });
    }
}
