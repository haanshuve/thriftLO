<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Kategori awal = isi config lama. slug = nilai yang tersimpan di products.kategori,
    // jadi 'Fashion' dan 'Vintage Tech' dipertahankan untuk produk lama.
    private const INITIAL = [
        'Fashion'      => ['Pakaian', '👕'],
        'Sepatu'       => ['Sepatu', '👟'],
        'Tas'          => ['Tas', '👜'],
        'Aksesoris'    => ['Aksesoris', '⌚'],
        'Vintage Tech' => ['Elektronik', '📷'],
        'Lainnya'      => ['Lainnya', '📦'],
    ];

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 50);
            $table->string('icon', 16)->default('📦');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (self::INITIAL as $slug => [$label, $icon]) {
            DB::table('categories')->insert([
                'slug'       => $slug,
                'label'      => $label,
                'icon'       => $icon,
                'sort_order' => ++$order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
