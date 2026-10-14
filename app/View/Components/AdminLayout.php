<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;
use Illuminate\View\View;

// Layout panel admin: sidebar kiri (off-canvas di HP) + header, terpisah dari layout marketplace
class AdminLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {
    }

    public function render(): View
    {
        return view('layouts.admin', [
            // [label, nama rute, pola rute aktif, path ikon SVG]
            'menu' => [
                ['Ringkasan', 'admin.dashboard', 'admin.dashboard', 'M3 12 12 4l9 8M5 10v10h5v-6h4v6h5V10'],
                ['Verifikasi Penjual', 'admin.sellers', 'admin.seller*', 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4'],
                ['Pengguna', 'admin.users', 'admin.users*', 'M16 19a4 4 0 0 0-8 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm9 8a3 3 0 0 0-3-3M3 19a3 3 0 0 1 3-3'],
                ['Produk', 'admin.products', 'admin.products*', 'M4 7.5 12 4l8 3.5v9L12 20l-8-3.5v-9Zm0 0 8 3.5m0 0 8-3.5M12 11v9'],
                ['Transaksi', 'admin.transactions', 'admin.transactions', 'M5 4h14v16l-3-2-2 2-2-2-2 2-2-2-3 2V4Zm4 5h6m-6 4h6'],
                ['Kategori', 'admin.categories', 'admin.categories*', 'M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z'],
                ['Laporan', 'admin.reports', 'admin.reports', 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
            ],
            'pendingSellers' => User::where('role', 'penjual')->where('seller_status', 'pending')->count(),
        ]);
    }
}
