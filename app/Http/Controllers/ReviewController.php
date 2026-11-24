<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ulasan pembeli untuk penjual: bintang 1-5 + komentar opsional. Hanya dari transaksi milik
 * pembeli sendiri yang sudah selesai, satu ulasan per transaksi (dijaga juga oleh index unik).
 */
class ReviewController extends Controller
{
    // Tiket COD yang sudah selesai (token QR dipindai penjual)
    public function store(Request $request, $bookingId)
    {
        $this->validateReview($request);

        $booking = Booking::with('product')->findOrFail($bookingId);

        if ((int) $booking->user_id !== (int) Auth::id() || $booking->status_cod !== 'Completed') {
            return back()->with('error', 'Ulasan hanya dapat diberikan pada transaksi COD yang sudah selesai!');
        }

        return $this->saveReview($request, ['booking_id' => $booking->id], $booking->product);
    }

    // Pesanan kirim yang sudah selesai (dana escrow sudah diteruskan ke penjual)
    public function storeForOrder(Request $request, Order $order)
    {
        $this->validateReview($request);

        $order->loadMissing('product');

        if ((int) $order->user_id !== (int) Auth::id() || !$order->isReleased()) {
            return back()->with('error', 'Ulasan hanya dapat diberikan setelah pesanan selesai.');
        }

        return $this->saveReview($request, ['order_id' => $order->id], $order->product);
    }

    // Bag "review" + booking_id/order_id di form, agar error tampil di kartu transaksi yang benar
    private function validateReview(Request $request): void
    {
        $request->validateWithBag('review', [
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ], [
            'rating.required' => 'Pilih jumlah bintang terlebih dahulu.',
            'rating.integer'  => 'Pilih bintang antara 1 sampai 5.',
            'rating.min'      => 'Pilih bintang antara 1 sampai 5.',
            'rating.max'      => 'Pilih bintang antara 1 sampai 5.',
            'comment.max'     => 'Ulasan maksimal 500 karakter.',
        ]);
    }

    private function saveReview(Request $request, array $transaction, $product)
    {
        $alreadyReviewed = fn () => back()->with('error', 'Kamu sudah memberikan ulasan untuk transaksi ini.');

        if (Review::where($transaction)->exists()) {
            return $alreadyReviewed();
        }

        try {
            Review::create([
                ...$transaction,
                'product_id' => $product->id,
                'seller_id'  => $product->user_id,
                'buyer_id'   => Auth::id(),
                'rating'     => (int) $request->rating,
                'comment'    => trim((string) $request->comment) ?: null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua kiriman bersamaan (klik ganda / dua tab): yang kedua ditolak index unik
            return $alreadyReviewed();
        }

        return back()->with('success', 'Terima kasih! Ulasan bintang kamu berhasil disimpan.');
    }
}
