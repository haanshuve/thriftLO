<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Payments\MidtransSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/*
 * Webhook notifikasi Midtrans untuk biaya booking COD. Rute publik tanpa CSRF; keasliannya dijamin
 * signature_key. Idempotent: Midtrans bisa mengirim notifikasi yang sama berkali-kali, dan transisi
 * status di Booking hanya terjadi sekali (baris dikunci, status awal dicek ulang).
 * Respons 2xx = notifikasi diterima (Midtrans berhenti mengirim ulang).
 */
class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();
        $orderId = is_string($payload['order_id'] ?? null) ? $payload['order_id'] : null;

        if (!MidtransSignature::isValid($payload, config('midtrans.server_key'))) {
            Log::warning('Notifikasi Midtrans ditolak: signature tidak valid', ['order_id' => $orderId]);

            return response()->json(['message' => 'invalid signature'], 403);
        }

        $booking = Booking::where('payment_order_id', $orderId)->first();
        if (!$booking) {
            // Mis. tombol "test notification" di dashboard Midtrans
            Log::info('Notifikasi Midtrans untuk order yang tidak dikenal', ['order_id' => $orderId]);

            return response()->json(['status' => 'ignored']);
        }

        // Nominal harus persis biaya booking yang ditagihkan
        if ((int) round((float) $payload['gross_amount']) !== (int) $booking->booking_fee) {
            Log::error('Notifikasi Midtrans: nominal tidak sesuai biaya booking', [
                'order_id' => $orderId, 'gross_amount' => $payload['gross_amount'], 'booking_fee' => $booking->booking_fee,
            ]);

            return response()->json(['message' => 'amount mismatch'], 422);
        }

        $status = (string) ($payload['transaction_status'] ?? '');
        $result = match (true) {
            $status === 'settlement',
            $status === 'capture' && ($payload['fraud_status'] ?? 'accept') === 'accept' => $booking->markFeePaid(),
            in_array($status, ['expire', 'cancel', 'deny'], true)                       => $booking->failPayment($status) ? 'released' : 'ignored',
            $status === 'failure'                                                        => $booking->failPayment('deny') ? 'released' : 'ignored',
            // pending: QR baru dibuat, belum ada yang berubah. refund: biaya booking tidak pernah dikembalikan.
            default                                                                      => 'ignored',
        };

        if ($result === 'late_forfeited') {
            Log::warning('Settlement Midtrans datang setelah booking dilepas; biaya booking dicatat milik platform', [
                'order_id' => $orderId, 'booking_id' => $booking->id,
            ]);
        }

        return response()->json(['status' => $result]);
    }
}
