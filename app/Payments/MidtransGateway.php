<?php

namespace App\Payments;

use App\Models\Booking;
use Carbon\CarbonInterface;
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

        self::configureSdk($serverKey);

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

        return self::chargeFromResponse($response, $expiresAt);
    }

    /*
     * Tanpa timeout, SDK menunggu Midtrans tanpa batas sampai PHP menghentikan request, dan booking
     * tertinggal tanpa QR. Dengan timeout, kegagalan menjadi exception dan booking langsung dilepas.
     * CURLOPT_HTTPHEADER wajib ada: SDK membaca indeks itu begitu curlOptions tidak kosong.
     */
    public static function configureSdk(string $serverKey): void
    {
        Config::$serverKey = $serverKey;
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$curlOptions = [
            CURLOPT_CONNECTTIMEOUT => (int) config('midtrans.connect_timeout'),
            CURLOPT_TIMEOUT        => (int) config('midtrans.timeout'),
            CURLOPT_HTTPHEADER     => [],
        ];
    }

    // Respons charge QRIS → QrisCharge. Terpisah supaya bisa diuji tanpa memanggil Midtrans.
    public static function chargeFromResponse(object $response, CarbonInterface $expiresAt): QrisCharge
    {
        $qrString = (string) ($response->qr_string ?? '');
        if ($qrString === '') {
            throw new PaymentException('Respons Midtrans tidak berisi qr_string.');
        }

        // URL gambar QR ada di actions[name=generate-qr-code]; tidak wajib, QR tetap dirender dari qr_string
        $qrImageUrl = null;
        foreach ((array) ($response->actions ?? []) as $action) {
            $action = (object) $action;
            if (($action->name ?? null) === 'generate-qr-code' && is_string($action->url ?? null)
                && str_starts_with($action->url, 'https://') && strlen($action->url) <= 255) {
                $qrImageUrl = $action->url;
                break;
            }
        }

        return new QrisCharge($qrString, $expiresAt, (string) ($response->transaction_status ?? 'pending'), $qrImageUrl);
    }
}
