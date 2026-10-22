<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Kategori & merek yang SUDAH diisi oleh migrasi create_categories_table dan create_brands_table.
    // Dicatat di sini supaya ReferenceDataSeeder tidak membuatnya lagi setelah admin menghapus atau mengganti namanya.
    // Daftar ini sengaja ditulis tetap (bukan diambil dari seeder) agar tambahan baru di seeder tetap dibuat.
    private const CATEGORY_SLUGS = ['Fashion', 'Sepatu', 'Tas', 'Aksesoris', 'Vintage Tech', 'Lainnya'];

    private const BRAND_NAMES = [
        'Nike', 'Adidas', 'Uniqlo', 'H&M', 'Zara', 'Converse', 'Vans', 'Puma', 'New Balance', "Levi's",
        'Hush Puppies', 'Eiger', 'Consina', 'Polo Ralph Lauren', 'Tommy Hilfiger', 'The North Face', 'Champion',
        'Fila', 'Erigo', 'Executive', 'Samsung', 'Apple', 'Xiaomi', 'Canon', 'Sony',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('reference_data_seeds')) {
            Schema::create('reference_data_seeds', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->timestamp('seeded_at')->nullable();
            });
        }

        $keys = array_merge(
            array_map(fn ($slug) => "category:{$slug}", self::CATEGORY_SLUGS),
            array_map(fn ($name) => 'brand:' . Str::slug($name), self::BRAND_NAMES),
        );

        // insertOrIgnore: aman dijalankan ulang
        DB::table('reference_data_seeds')->insertOrIgnore(
            array_map(fn ($key) => ['key' => $key, 'seeded_at' => now()], $keys)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_data_seeds');
    }
};
