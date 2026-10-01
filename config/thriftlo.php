<?php

return [

    /*
    | Kategori produk. Key = nilai yang disimpan di kolom products.kategori,
    | jadi 'Fashion' dan 'Vintage Tech' dipertahankan untuk produk lama.
    */
    'categories' => [
        'Fashion'      => ['label' => 'Pakaian',    'icon' => '👕'],
        'Sepatu'       => ['label' => 'Sepatu',     'icon' => '👟'],
        'Tas'          => ['label' => 'Tas',        'icon' => '👜'],
        'Aksesoris'    => ['label' => 'Aksesoris',  'icon' => '⌚'],
        'Vintage Tech' => ['label' => 'Elektronik', 'icon' => '📷'],
        'Lainnya'      => ['label' => 'Lainnya',    'icon' => '📦'],
    ],

    /*
    | Grade kondisi barang. Teks dalam kurung ditampilkan sebagai badge di kartu produk.
    */
    'grades' => [
        'Grade A (Like New)',
        'Grade B (Minus Pemakaian)',
        'Grade C (Need Repair)',
    ],

    /*
    | Langganan penjual. Tanpa langganan, penjual hanya bisa menayangkan sejumlah
    | free_product_limit produk aktif (Available + Booked).
    | simulate_payment: tombol "Bayar Sekarang" langsung mengaktifkan langganan tanpa
    | payment gateway. Default mati di production sampai Midtrans terpasang.
    */
    'subscription' => [
        'free_product_limit' => 5,
        'price'              => 5000,
        'days'               => 30,
        'simulate_payment'   => (bool) env('SUBSCRIPTION_SIMULATION', env('APP_ENV') !== 'production'),
    ],

];
