<?php

namespace App\Payments;

use App\Models\Booking;

/*
 * Cara membayar biaya booking COD, dipilih lewat config('payment.driver').
 * Simulasi: booking langsung lunas saat dibuat. Midtrans: booking menunggu webhook settlement.
 */
interface BookingFeeGateway
{
    public function isSimulation(): bool;

    /**
     * Buat tagihan QRIS untuk booking yang payment_order_id dan booking_fee-nya sudah terisi.
     *
     * @throws PaymentException kalau gateway menolak atau tidak bisa dihubungi
     */
    public function createQrisCharge(Booking $booking): QrisCharge;
}
