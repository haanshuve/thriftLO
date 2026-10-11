<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Akun admin tidak bisa dibuat lewat halaman daftar: daftarkan akun biasa dulu, lalu jadikan admin
Artisan::command('thriftlo:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (!$user) {
        $this->error("Akun dengan email {$email} tidak ditemukan. Daftarkan akunnya dulu lewat halaman Daftar.");

        return 1;
    }

    $user->forceFill(['role' => 'admin'])->save();
    $this->info("{$user->name} ({$email}) sekarang admin. Panel admin: " . route('admin.sellers'));

    return 0;
})->purpose('Jadikan akun yang sudah terdaftar sebagai admin thriftLO');

// Escrow pesanan Kirim: cairkan dana ke penjual (dipotong komisi) kalau pembeli tidak
// konfirmasi dalam batas waktu. Halaman pesanan juga memanggil ini sebagai cadangan.
Artisan::command('thriftlo:release-escrow', function () {
    $released = Order::releaseOverdue();
    $this->info("{$released} pesanan dicairkan otomatis ke penjual.");

    return 0;
})->purpose('Cairkan dana escrow pesanan Kirim yang melewati batas konfirmasi pembeli');

Schedule::command('thriftlo:release-escrow')->hourly();
