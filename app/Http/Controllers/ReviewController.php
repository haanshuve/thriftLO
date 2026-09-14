<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, $bookingId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $booking = Booking::findOrFail($bookingId);

        // Validasi: Hanya pembeli sah yang transaksinya sudah 'Completed' via QR
        if ($booking->user_id !== Auth::id() || $booking->status_cod !== 'Completed') {
            return back()->with('error', 'Ulasan hanya dapat diberikan pada transaksi COD yang sudah selesai!');
        }

        // Cek jika ulasan sudah pernah dikirim
        $existingReview = Review::where('booking_id', $booking->id)->first();
        if ($existingReview) {
            return back()->with('error', 'Kamu sudah memberikan ulasan untuk transaksi ini.');
        }

        Review::create([
            'booking_id' => $booking->id,
            'product_id' => $booking->product_id,
            'seller_id'  => $booking->product->user_id,
            'buyer_id'   => Auth::id(),
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        return back()->with('success', 'Terima kasih! Ulasan bintang kamu berhasil disimpan.');
    }
}
