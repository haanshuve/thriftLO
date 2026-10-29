<?php

namespace App\Payments;

use App\Models\Booking;
use LogicException;

// Mode demo: pembeli menekan "Saya sudah bayar" dan booking langsung lunas, tanpa tagihan sungguhan
class SimulationGateway implements BookingFeeGateway
{
    public function isSimulation(): bool
    {
        return true;
    }

    public function createQrisCharge(Booking $booking): QrisCharge
    {
        throw new LogicException('Mode simulasi tidak membuat tagihan QRIS.');
    }
}
