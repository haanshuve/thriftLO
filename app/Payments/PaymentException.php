<?php

namespace App\Payments;

use RuntimeException;

// Tagihan gagal dibuat (kunci belum diisi, ditolak gateway, atau gateway tidak bisa dihubungi)
class PaymentException extends RuntimeException
{
}
