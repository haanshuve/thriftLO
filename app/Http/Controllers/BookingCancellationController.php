<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BookingCancellationController extends Controller
{
    // Pembeli membatalkan booking COD-nya sendiri: biaya booking menjadi milik platform
    public function cancelByBuyer(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) Auth::id(), 403);

        if (!$booking->isActive()) {
            return back()->with('error', 'Booking ini sudah selesai atau sudah dibatalkan, jadi tidak bisa dibatalkan lagi.');
        }

        // Persetujuan konsekuensi wajib dicek di server, bukan hanya lewat tombol yang dikunci di tampilan
        $request->validateWithBag('cancel', [
            'setuju_konsekuensi' => 'accepted',
            'alasan'             => ['nullable', Rule::in(array_keys(Booking::BUYER_CANCEL_REASONS))],
        ], [
            'setuju_konsekuensi.accepted' => 'Centang persetujuan bahwa biaya booking tidak dapat dikembalikan dan menjadi milik platform untuk membatalkan booking.',
            'alasan.in'                   => 'Pilih alasan pembatalan dari daftar yang tersedia.',
        ]);

        if (!$booking->cancelByBuyer($request->input('alasan'))) {
            return back()->with('error', 'Booking ini sudah selesai atau sudah dibatalkan, jadi tidak bisa dibatalkan lagi.');
        }

        $fee = $booking->booking_fee > 0 ? ' Biaya booking Rp' . number_format($booking->booking_fee, 0, ',', '.') . ' tidak dapat dikembalikan dan menjadi milik platform.' : '';

        return redirect()->route('bookings.index', ['tab' => 'riwayat'])
            ->with('success', 'Booking "' . $booking->product->title . '" sudah dibatalkan dan token QR-nya tidak berlaku lagi.' . $fee);
    }

    // Penjual membatalkan booking untuk barangnya: biaya booking tetap menjadi milik platform
    public function cancelBySeller(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless((int) $booking->product?->user_id === (int) Auth::id(), 403);

        if (!$booking->isActive()) {
            return back()->with('error', 'Booking ini sudah selesai atau sudah dibatalkan, jadi tidak bisa dibatalkan lagi.');
        }

        $request->validateWithBag('sellerCancel', [
            'alasan'          => ['required', Rule::in(array_keys(Booking::SELLER_CANCEL_REASONS))],
            'tindakan_produk' => ['required', Rule::in(['tayangkan', 'nonaktifkan'])],
        ], [
            'alasan.required'          => 'Pilih alasan pembatalan.',
            'alasan.in'                => 'Pilih alasan pembatalan dari daftar yang tersedia.',
            'tindakan_produk.required' => 'Pilih apakah barang ditayangkan lagi atau diturunkan dari katalog.',
            'tindakan_produk.in'       => 'Pilih apakah barang ditayangkan lagi atau diturunkan dari katalog.',
        ]);

        $relist = $request->input('tindakan_produk') === 'tayangkan';

        if (!$booking->cancelBySeller($request->input('alasan'), $relist)) {
            return back()->with('error', 'Booking ini sudah selesai atau sudah dibatalkan, jadi tidak bisa dibatalkan lagi.');
        }

        $fee = $booking->booking_fee > 0 ? ' Biaya booking Rp' . number_format($booking->booking_fee, 0, ',', '.') . ' yang dibayar pembeli tidak dapat dikembalikan dan menjadi milik platform.' : '';

        return redirect()->route('dashboard')->with('success',
            'Booking "' . $booking->product->title . '" dibatalkan.' . $fee . ($relist ? ' Barang tayang lagi di katalog.' : ' Barang diturunkan dari katalog.'));
    }
}
