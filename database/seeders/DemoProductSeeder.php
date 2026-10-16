<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Produk CONTOH untuk demo beranda di lokal. TIDAK dipanggil DatabaseSeeder dan tidak berjalan saat deploy.
 *
 *   php artisan db:seed --class=DemoProductSeeder   (tambah produk contoh)
 *   php artisan demo:clear                          (hapus semua produk contoh)
 *
 * Produk ditandai jelas: judul diawali "[Contoh]" dan deskripsi berisi "[DATA CONTOH]".
 * Foto berupa SVG placeholder lokal di storage/app/public/products/demo.
 */
class DemoProductSeeder extends Seeder
{
    public const TITLE_PREFIX = '[Contoh] ';
    public const MARKER = '[DATA CONTOH]';
    public const IMAGE_DIR = 'products/demo';

    // [judul, kategori, merek, harga, indeks grade, warna latar, bisa dikirim?]
    private const ITEMS = [
        ["Jaket Denim Levi's Trucker", 'Fashion', "Levi's", 185000, 0, '#1e3a8a', true],
        ['Kaos Uniqlo U Crew Neck', 'Fashion', 'Uniqlo', 65000, 0, '#b91c1c', false],
        ['Sepatu Nike Air Max 90', 'Sepatu', 'Nike', 450000, 1, '#0f172a', true],
        ['Converse Chuck 70 High', 'Sepatu', 'Converse', 320000, 1, '#7c2d12', false],
        ['Tas Ransel Eiger 25L', 'Tas', 'Eiger', 210000, 0, '#166534', true],
        ['Tote Bag H&M Kanvas', 'Tas', 'H&M', 55000, 1, '#9f1239', false],
        ['Kamera Canon EOS 600D (body)', 'Vintage Tech', 'Canon', 2100000, 1, '#334155', true],
        ['Headphone Sony WH-CH510', 'Vintage Tech', 'Sony', 280000, 0, '#4c1d95', false],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoProductSeeder hanya untuk lokal. Dibatalkan karena APP_ENV=production.');

            return;
        }

        if (Product::where('description', 'like', '%' . self::MARKER . '%')->exists()) {
            $this->command?->warn('Produk contoh sudah ada. Jalankan "php artisan demo:clear" dulu kalau mau mengisi ulang.');

            return;
        }

        $sellers = User::where('role', 'penjual')->where('seller_status', 'verified')->whereNull('deactivated_at')->orderBy('id')->get();
        if ($sellers->isEmpty()) {
            $this->command?->error('Belum ada penjual terverifikasi. Daftarkan penjual dan setujui di Panel Admin dulu.');

            return;
        }

        $grades = config('thriftlo.grades');

        foreach (self::ITEMS as $i => [$title, $kategori, $brandName, $price, $grade, $color, $ship]) {
            $seller = $sellers[$i % $sellers->count()];
            $brand = Brand::active()->where('name', $brandName)->first();
            $image = $this->placeholder($title, $brandName, $color);

            Product::create([
                'user_id'          => $seller->id,
                'title'            => self::TITLE_PREFIX . $title,
                'description'      => "Produk contoh untuk demo tampilan beranda, bukan barang sungguhan. " . self::MARKER,
                'kategori'         => $kategori,
                'brand_id'         => $brand?->id,
                'brand_other'      => $brand ? null : $brandName,
                'mode_jual'        => 'ecer',
                'grade'            => $grades[$grade] ?? $grades[0],
                'price'            => $price,
                'image_url'        => $image,
                'image_path'       => $image,
                // Penjual luar Batam wajib punya opsi kirim supaya barangnya bisa dibeli
                'shipping_options' => ($ship || !$seller->isInBatam()) ? [['courier' => 'JNE Reguler', 'cost' => 18000]] : null,
                'status'           => 'Available',
            ]);
        }

        $this->command?->info(count(self::ITEMS) . " produk contoh ditambahkan untuk {$sellers->count()} penjual. Hapus dengan: php artisan demo:clear");

        // Penjual tanpa langganan hanya menayangkan N produk aktif pertama di katalog
        $limit = (int) config('thriftlo.subscription.free_product_limit');
        foreach ($sellers as $seller) {
            if (!$seller->hasActiveSubscription() && $seller->activeProductCount() > $limit) {
                $this->command?->warn("Penjual {$seller->name} punya lebih dari {$limit} produk aktif tanpa langganan, jadi sebagian produk contoh tidak tampil di katalog.");
            }
        }
    }

    // Foto placeholder SVG lokal: blok warna + nama merek + label "CONTOH" (bukan logo resmi)
    private function placeholder(string $title, string $brand, string $color): string
    {
        $path = self::IMAGE_DIR . '/' . Str::slug($title) . '.svg';
        $brand = e($brand);
        $label = e(Str::limit($title, 28));

        Storage::disk('public')->put($path, <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">
  <rect width="600" height="600" fill="{$color}"/>
  <circle cx="470" cy="130" r="170" fill="#ffffff" opacity="0.08"/>
  <circle cx="120" cy="520" r="200" fill="#ffffff" opacity="0.06"/>
  <text x="300" y="285" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="72" font-weight="900" fill="#ffffff">{$brand}</text>
  <text x="300" y="345" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="26" fill="#ffffff" opacity="0.85">{$label}</text>
  <rect x="210" y="430" width="180" height="48" rx="24" fill="#fbbf24"/>
  <text x="300" y="463" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="700" fill="#451a03">CONTOH</text>
</svg>
SVG);

        return $path;
    }
}
