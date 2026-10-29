<?php

return [

    /*
    | Cara membayar biaya booking COD.
    | simulation: QRIS contoh + tombol "Saya sudah bayar", tanpa uang sungguhan (default, aman untuk demo).
    | midtrans:   QRIS sungguhan lewat Midtrans Core API, lunas setelah webhook settlement.
    | Langganan penjual dan escrow belum memakai pengaturan ini.
    */
    'driver' => env('PAYMENT_DRIVER', 'simulation'),

];
