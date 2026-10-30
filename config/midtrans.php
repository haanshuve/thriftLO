<?php

return [

    /*
    | Kunci Midtrans dibaca dari env, jangan pernah ditulis di kode. Dipakai hanya kalau
    | PAYMENT_DRIVER=midtrans (lihat config/payment.php). Sandbox: is_production=false.
    */
    'server_key'    => env('MIDTRANS_SERVER_KEY'),
    'client_key'    => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),

    /*
    | Core API QRIS. qris_acquirer: penerbit QRIS di Midtrans (gopay atau airpay shopee).
    | expiry_minutes: masa berlaku QR; selama itu barang ditahan untuk pembeli.
    */
    'qris_acquirer'  => env('MIDTRANS_QRIS_ACQUIRER', 'gopay'),
    'expiry_minutes' => (int) env('MIDTRANS_EXPIRY_MINUTES', 15),

    /*
    | Batas tunggu panggilan ke Midtrans, dalam detik. Totalnya harus jauh di bawah
    | max_execution_time PHP dan di bawah Booking::QR_CREATION_GRACE_SECONDS.
    */
    'connect_timeout' => 5,
    'timeout'         => 20,

    // Simulator pembayaran QRIS Midtrans, hanya ditawarkan di halaman bayar saat sandbox
    'sandbox_simulator_url' => 'https://simulator.sandbox.midtrans.com/v2/qris/index',

];
