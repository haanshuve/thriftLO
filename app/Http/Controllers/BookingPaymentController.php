<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

// Halaman bayar QRIS biaya booking (mode Midtrans). Lunas atau tidaknya ditentukan webhook.
class BookingPaymentController extends Controller
{
    public function show(Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) Auth::id(), 403);
        Booking::releaseExpiredPayments();
        $booking->refresh()->load('product');

        if ($booking->isAwaitingPayment()) {
            return view('booking-pay', ['booking' => $booking]);
        }

        if ($booking->fee_status === Booking::FEE_PAID) {
            return redirect()->route('bookings.index')->with('success', 'Pembayaran biaya booking Rp' . number_format($booking->booking_fee, 0, ',', '.') . ' berhasil. Barangnya udah dikunci buat kamu. Tinggal ketemuan sama penjualnya dan tunjukin token ' . $booking->qr_token . ' di bawah ini.');
        }

        return redirect()->route('product.show', $booking->product_id)
            ->with('error', ($booking->cancelReasonLabel() ?? 'Pembayaran tidak selesai') . '. Barangnya tidak dikunci dan kamu tidak dikenai biaya. Silakan booking ulang kalau masih tersedia.');
    }

    // Dipanggil berkala oleh halaman bayar; halaman dimuat ulang begitu status berubah
    public function status(Booking $booking): JsonResponse
    {
        abort_unless((int) $booking->user_id === (int) Auth::id(), 403);
        Booking::releaseExpiredPayments();
        $booking->refresh();

        return response()->json([
            'status' => match (true) {
                $booking->isAwaitingPayment()                => 'awaiting',
                $booking->fee_status === Booking::FEE_PAID => 'paid',
                default                                      => 'failed',
            },
        ]);
    }
}
