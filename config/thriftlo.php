<?php

return [

    // Kategori produk dikelola admin di tabel `categories` (App\Models\Category).

    /*
    | Grade kondisi barang. Teks dalam kurung ditampilkan sebagai badge di kartu produk.
    */
    'grades' => [
        'Grade A (Like New)',
        'Grade B (Minus Pemakaian)',
        'Grade C (Need Repair)',
    ],

    /*
    | Pilihan lokasi lapak di Batam (dropdown registrasi & profil penjual). Semua pilihan
    | ini boleh COD; opsi "Luar Batam" ditambahkan di akhir oleh App\Support\SellerLocation.
    | "Batam lainnya" untuk kawasan Batam yang tidak ada di daftar.
    */
    'batam_areas' => [
        'Batam Center',
        'Batam Kota',
        'Nagoya',
        'Jodoh',
        'Lubuk Baja',
        'Baloi',
        'Batu Ampar',
        'Bengkong',
        'Botania',
        'Sei Panas',
        'Sekupang',
        'Tiban',
        'Batu Aji',
        'Sagulung',
        'Tanjung Uncang',
        'Mukakuning',
        'Sei Beduk',
        'Tanjung Piayu',
        'Nongsa',
        'Belakang Padang',
        'Batam lainnya',
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

    /*
    | Escrow pesanan Kirim. Dana pembeli ditahan sampai pembeli konfirmasi barang diterima,
    | atau otomatis cair auto_release_days hari setelah penjual menandai dikirim.
    | platform_fee: komisi tetap per transaksi Kirim, dipotong dari dana yang cair ke
    | penjual (tidak ditambahkan ke tagihan pembeli).
    */
    'escrow' => [
        'auto_release_days' => 3,
        'platform_fee'      => 3000,
    ],

    /*
    | Video Proof: video singkat kondisi asli barang, wajib untuk setiap barang baru.
    | Batas upload PHP (upload_max_filesize & post_max_size) harus di atas max_mb
    | + foto produk (5MB), kalau tidak file ditolak server sebelum sampai validasi.
    */
    /*
    | Booking COD. fee: biaya booking yang dibayar pembeli lewat QRIS (masih simulasi)
    | sebagai komitmen. Tidak dikembalikan kalau pembeli membatalkan; dikembalikan kalau
    | penjual yang membatalkan.
    */
    'booking' => [
        'fee' => 2000,
    ],

    'video_proof' => [
        'max_mb' => 50,
    ],

];
