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

];
