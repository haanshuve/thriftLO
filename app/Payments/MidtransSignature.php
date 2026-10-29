<?php

namespace App\Payments;

// signature_key notifikasi Midtrans = SHA512(order_id + status_code + gross_amount + server key)
final class MidtransSignature
{
    public static function make(string $orderId, string $statusCode, string $grossAmount, string $serverKey): string
    {
        return hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    }

    public static function isValid(array $payload, ?string $serverKey): bool
    {
        if ($serverKey === null || $serverKey === '') {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || $payload[$field] === '') {
                return false;
            }
        }

        $expected = self::make($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $serverKey);

        return hash_equals($expected, $payload['signature_key']);
    }
}
