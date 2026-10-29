<?php

namespace App\Payments;

use Carbon\CarbonInterface;

// Tagihan QRIS yang berhasil dibuat: isi QR untuk dirender dan batas waktu bayarnya
final class QrisCharge
{
    public function __construct(
        public readonly string $qrString,
        public readonly CarbonInterface $expiresAt,
        public readonly string $status = 'pending',
    ) {
    }
}
