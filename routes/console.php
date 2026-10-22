<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

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
    $this->info("{$user->name} ({$email}) sekarang admin. Panel admin: " . route('admin.dashboard'));

    return 0;
})->purpose('Jadikan akun yang sudah terdaftar sebagai admin thriftLO');

// Hapus produk CONTOH dari DemoProductSeeder (judul "[Contoh] ..." + deskripsi "[DATA CONTOH]") beserta
// foto placeholder-nya. Booking/pesanan pada produk contoh ikut terhapus (foreign key cascade).
Artisan::command('demo:clear {--force : Hapus tanpa konfirmasi}', function () {
    $demo = Product::where('title', 'like', DemoProductSeeder::TITLE_PREFIX . '%')
        ->where('description', 'like', '%' . DemoProductSeeder::MARKER . '%');

    $count = (clone $demo)->count();
    if ($count === 0) {
        $this->info('Tidak ada produk contoh.');

        return 0;
    }

    if (!$this->option('force') && !$this->confirm("Hapus {$count} produk contoh?", true)) {
        $this->line('Dibatalkan.');

        return 1;
    }

    $demo->delete();
    Storage::disk('public')->deleteDirectory(DemoProductSeeder::IMAGE_DIR);
    $this->info("{$count} produk contoh dihapus.");

    return 0;
})->purpose('Hapus produk contoh hasil DemoProductSeeder');

// Escrow pesanan Kirim: cairkan dana ke penjual (dipotong komisi) kalau pembeli tidak
// konfirmasi dalam batas waktu. Halaman pesanan juga memanggil ini sebagai cadangan.
Artisan::command('thriftlo:release-escrow', function () {
    $released = Order::releaseOverdue();
    $this->info("{$released} pesanan dicairkan otomatis ke penjual.");

    return 0;
})->purpose('Cairkan dana escrow pesanan Kirim yang melewati batas konfirmasi pembeli');

// Tiap pesanan tetap dicairkan sekali (Order::release mengunci baris); withoutOverlapping hanya
// mencegah dua run scheduler menumpuk kalau run sebelumnya belum selesai.
Schedule::command('thriftlo:release-escrow')->hourly()->withoutOverlapping();
