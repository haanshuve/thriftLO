<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data referensi (kategori & merek) yang dibutuhkan beranda. AMAN untuk production dan dijalankan
 * di setiap deploy Railway:
 *
 *   php artisan db:seed --class=ReferenceDataSeeder --force
 *
 * Aturan:
 * - Tiap item hanya dibuat SEKALI seumur database. Kuncinya dicatat di tabel reference_data_seeds,
 *   jadi item yang sudah dihapus atau diganti nama oleh admin tidak dibuat ulang.
 * - Baris yang sudah ada tidak pernah diubah (label, ikon, urutan, status aktif tetap milik admin).
 * - Tidak menyentuh produk; DemoProductSeeder tidak dipanggil dari sini.
 */
class ReferenceDataSeeder extends Seeder
{
    // slug (nilai di products.kategori) => [label, ikon]
    public const CATEGORIES = [
        'Fashion'      => ['Pakaian', '👕'],
        'Sepatu'       => ['Sepatu', '👟'],
        'Tas'          => ['Tas', '👜'],
        'Aksesoris'    => ['Aksesoris', '⌚'],
        'Vintage Tech' => ['Elektronik', '📷'],
        'Lainnya'      => ['Lainnya', '📦'],
    ];

    // [nama, populer?]
    public const BRANDS = [
        ['Nike', true], ['Adidas', true], ['Uniqlo', true], ['H&M', true], ['Zara', true],
        ['Converse', true], ['Vans', true], ['Puma', true], ['New Balance', true], ["Levi's", true],
        ['Hush Puppies', false], ['Eiger', true], ['Consina', false], ['Polo Ralph Lauren', false],
        ['Tommy Hilfiger', false], ['The North Face', true], ['Champion', false], ['Fila', false],
        ['Erigo', false], ['Executive', false], ['Samsung', false], ['Apple', true], ['Xiaomi', false],
        ['Canon', false], ['Sony', false],
    ];

    private int $created = 0;

    public function run(): void
    {
        $order = 0;
        foreach (self::CATEGORIES as $slug => [$label, $icon]) {
            $sortOrder = ++$order;

            $this->seedOnce("category:{$slug}", function () use ($slug, $label, $icon, $sortOrder) {
                Category::firstOrCreate(['slug' => $slug], [
                    'label'      => $label,
                    'icon'       => $icon,
                    'sort_order' => $sortOrder,
                ]);
            });
        }

        foreach (self::BRANDS as $i => [$name, $popular]) {
            $this->seedOnce('brand:' . Str::slug($name), function () use ($name, $popular, $i) {
                // Cocokkan slug ATAU nama (tanpa beda huruf besar/kecil) supaya tidak membuat duplikat
                $exists = Brand::where('slug', Str::slug($name))
                    ->orWhereRaw('LOWER(name) = ?', [Str::lower($name)])
                    ->exists();

                if (!$exists) {
                    Brand::create([
                        'name'       => $name,
                        'is_popular' => $popular,
                        'sort_order' => $i + 1,
                        'is_active'  => true,
                    ]);
                }
            });
        }

        $this->command?->info("Data referensi: {$this->created} item baru dicatat.");
    }

    // Jalankan $create hanya bila $key belum pernah di-seed, lalu catat $key. Dalam transaksi supaya
    // dua deploy yang berjalan bersamaan tidak membuat item ganda (unique index pada key).
    private function seedOnce(string $key, callable $create): void
    {
        DB::transaction(function () use ($key, $create) {
            if (DB::table('reference_data_seeds')->where('key', $key)->exists()) {
                return;
            }

            $create();

            if (DB::table('reference_data_seeds')->insertOrIgnore(['key' => $key, 'seeded_at' => now()])) {
                $this->created++;
            }
        });
    }
}
