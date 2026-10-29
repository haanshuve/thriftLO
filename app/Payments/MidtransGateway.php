<?php

namespace App\Payments;

use App\Models\Booking;
use Midtrans\Config;
use Midtrans\CoreApi;
use Throwable;

// QRIS lewat Midtrans Core API. Lunas atau tidaknya ditentukan webhook, bukan respons charge ini.
class MidtransGateway implements BookingFeeGateway
{
    public function isSimulation(): bool
    {
        return false;
    }

    public function createQrisCharge(Booking $booking): QrisCharge
    {
        $serverKey = (string) config('midtrans.server_key');
        if ($serverKey === '') {
            throw new PaymentException('MIDTRANS_SERVER_KEY belum diisi, padahal PAYMENT_DRIVER=midtrans.');
        }

        Config::$serverKey = $serverKey;
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = true;

        $minutes = max(1, (int) config('midtrans.expiry_minutes'));
        $expiresAt = now()->addMinutes($minutes);

        try {
            $response = CoreApi::charge([
                'payment_type'        => 'qris',
                'transaction_details' => [
                    'order_id'     => $booking->payment_order_id,
                    'gross_amount' => $booking->booking_fee,
                ],
                'item_details' => [[
                    'id'       => 'booking-fee',
                    'price'    => $booking->booking_fee,
                    'quantity' => 1,
                    'name'     => 'Biaya booking COD thriftLO',
                ]],
                'customer_details' => [
                    'first_name' => $booking->user?->name,
                    'email'      => $booking->user?->email,
                ],
                'qris'          => ['acquirer' => config('midtrans.qris_acquirer')],
                'custom_expiry' => ['expiry_duration' => $minutes, 'unit' => 'minute'],
            ]);
        } catch (Throwable $e) {
            // Pesan SDK bisa memuat isi respons; jangan sampai kunci ikut tercatat
            throw new PaymentException('Midtrans menolak tagihan QRIS: ' . str_replace($serverKey, '***', $e->getMessage()), 0, $e);
        }

        $qrString = (string) ($response->qr_string ?? '');
        if ($qrString === '') {
            throw new PaymentException('Respons Midtrans tidak berisi qr_string.');
        }

        return new QrisCharge($qrString, $expiresAt, (string) ($response->transaction_status ?? 'pending'));
    }
}
