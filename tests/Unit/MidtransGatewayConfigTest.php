<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Payments\MidtransGateway;
use Midtrans\Config;
use Tests\TestCase;

// Pengaturan SDK Midtrans sebelum charge, tanpa memanggil Midtrans. Server key hanya string palsu.
class MidtransGatewayConfigTest extends TestCase
{
    public function test_sdk_calls_have_connect_and_total_timeout(): void
    {
        config(['midtrans.is_production' => false]);

        MidtransGateway::configureSdk('test-server-key');

        $this->assertSame(5, Config::$curlOptions[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(20, Config::$curlOptions[CURLOPT_TIMEOUT]);
        // SDK membaca indeks ini begitu curlOptions tidak kosong; tanpa itu muncul warning → exception
        $this->assertSame([], Config::$curlOptions[CURLOPT_HTTPHEADER]);
        $this->assertSame('test-server-key', Config::$serverKey);
        $this->assertFalse(Config::$isProduction);
    }

    public function test_timeouts_finish_before_stuck_bookings_are_released(): void
    {
        $this->assertLessThan(
            Booking::QR_CREATION_GRACE_SECONDS,
            config('midtrans.connect_timeout') + config('midtrans.timeout'),
        );
    }
}
