<?php

namespace Tests\Unit;

use App\Payments\MidtransSignature;
use PHPUnit\Framework\TestCase;

class MidtransSignatureTest extends TestCase
{
    // Kunci palsu khusus test, bukan kunci Midtrans sungguhan
    private const KEY = 'test-server-key';

    private function payload(array $overrides = []): array
    {
        $payload = ['order_id' => 'TL-BKG-1-ABC123', 'status_code' => '200', 'gross_amount' => '2000.00'] + $overrides;
        $payload['signature_key'] ??= hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . self::KEY);

        return $payload;
    }

    public function test_valid_signature_is_accepted(): void
    {
        $this->assertTrue(MidtransSignature::isValid($this->payload(), self::KEY));
    }

    public function test_signature_from_another_key_is_rejected(): void
    {
        $this->assertFalse(MidtransSignature::isValid($this->payload(), 'kunci-lain'));
    }

    public function test_tampered_amount_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['gross_amount'] = '1.00';

        $this->assertFalse(MidtransSignature::isValid($payload, self::KEY));
    }

    public function test_missing_fields_or_key_are_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['signature_key']);

        $this->assertFalse(MidtransSignature::isValid($payload, self::KEY));
        $this->assertFalse(MidtransSignature::isValid($this->payload(), null));
        $this->assertFalse(MidtransSignature::isValid($this->payload(), ''));
    }
}
