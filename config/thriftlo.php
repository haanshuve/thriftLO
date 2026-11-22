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
    | Booking COD. fee: biaya booking yang dibayar pembeli lewat QRIS sebagai komitmen
    | (simulasi atau Midtrans, lihat config/payment.php). Setelah lunas selalu menjadi milik
    | platform dan tidak pernah dikembalikan, siapa pun yang membatalkan.
    */
    'booking' => [
        'fee' => 2000,
    ],

    'video_proof' => [
        'max_mb' => 50,
    ],

    /*
    | Titik COD penjual (pin peta Leaflet + OpenStreetMap, tanpa API key).
    | address_max: batas karakter patokan alamat, dipakai di maxlength form dan validasi server.
    | center/zoom: posisi awal peta sebelum penjual memasang pin (sekitar Batam Centre).
    | areas: wilayah yang boleh dipasangi pin (kotak lat/lng, batas ikut diterima). Titik COD
    | harus masuk salah satu kotak. Dibuat dua kotak, bukan satu kotak 0.95-1.30 / 103.85-104.25,
    | supaya selatan Singapura (lat > 1.215) dan Tanjung Uban, Bintan (lng > 104.20) tidak ikut,
    | sementara Rempang-Galang (masih Kota Batam, tersambung jembatan Barelang) tetap tercakup.
    | Batasnya perkiraan; cek ulang di peta kalau ada titik sah yang tertolak.
    | max_km: jarak maksimal (garis lurus, Haversine) titik ketemuan yang diusulkan pembeli dari
    | lokasi acuan COD penjual. Dilewati kalau penjual belum memasang lokasi acuan.
    | meeting_note_max: batas keterangan tempat ketemuan di popup booking (per karakter).
    | fairness_warning_km: selisih jarak penjual vs pembeli yang memunculkan peringatan kuning.
    */
    'cod_point' => [
        'max_km'              => 5,
        'meeting_note_max'    => 60,
        'fairness_warning_km' => 2,
        'address_max' => 150,
        'center'      => ['lat' => 1.1301, 'lng' => 104.0529],
        'zoom'        => 12,
        'areas'       => [
            ['label' => 'Pulau Batam & Belakang Padang', 'lat' => [0.95, 1.215], 'lng' => [103.85, 104.20]],
            ['label' => 'Rempang & Galang',              'lat' => [0.65, 0.95],  'lng' => [103.95, 104.30]],
        ],
    ],

];
