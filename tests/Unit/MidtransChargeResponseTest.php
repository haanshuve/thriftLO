<?php

namespace Tests\Unit;

use App\Payments\MidtransGateway;
use App\Payments\PaymentException;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

// Respons charge QRIS sintetis, bentuknya mengikuti respons Core API Midtrans
class MidtransChargeResponseTest extends TestCase
{
    private const QR_URL = 'https://api.sandbox.midtrans.com/v2/qris/00000000-test-0000-0000-000000000000/qr-code';

    private function response(array $overrides = []): object
    {
        return json_decode(json_encode($overrides + [
            'status_code'        => '201',
            'transaction_status' => 'pending',
            'qr_string'          => '00020101021226QRIS-PALSU',
            'actions'            => [['name' => 'generate-qr-code', 'method' => 'GET', 'url' => self::QR_URL]],
        ]));
    }

    public function test_qr_image_url_is_taken_from_generate_qr_code_action(): void
    {
        $charge = MidtransGateway::chargeFromResponse($this->response(), Carbon::now()->addMinutes(15));

        $this->assertSame('00020101021226QRIS-PALSU', $charge->qrString);
        $this->assertSame(self::QR_URL, $charge->qrImageUrl);
        $this->assertSame('pending', $charge->status);
    }

    public function test_missing_or_unsafe_actions_give_no_url_without_failing(): void
    {
        $expires = Carbon::now()->addMinutes(15);

        $this->assertNull(MidtransGateway::chargeFromResponse($this->response(['actions' => []]), $expires)->qrImageUrl);
        $this->assertNull(MidtransGateway::chargeFromResponse($this->response(['actions' => [['name' => 'get-status', 'url' => self::QR_URL]]]), $expires)->qrImageUrl);
        $this->assertNull(MidtransGateway::chargeFromResponse($this->response(['actions' => [['name' => 'generate-qr-code', 'url' => 'javascript:alert(1)']]]), $expires)->qrImageUrl);
    }

    public function test_missing_qr_string_still_fails(): void
    {
        $this->expectException(PaymentException::class);
        MidtransGateway::chargeFromResponse($this->response(['qr_string' => '']), Carbon::now());
    }
}
