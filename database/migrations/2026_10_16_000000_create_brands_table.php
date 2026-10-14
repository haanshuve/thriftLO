<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Merek awal yang sering muncul di pasar thrift/preloved. [nama, populer?]
    private const INITIAL = [
        ['Nike', true], ['Adidas', true], ['Uniqlo', true], ['H&M', true], ['Zara', true],
        ['Converse', true], ['Vans', true], ['Puma', true], ['New Balance', true], ["Levi's", true],
        ['Hush Puppies', false], ['Eiger', true], ['Consina', false], ['Polo Ralph Lauren', false],
        ['Tommy Hilfiger', false], ['The North Face', true], ['Champion', false], ['Fila', false],
        ['Erigo', false], ['Executive', false], ['Samsung', false], ['Apple', true], ['Xiaomi', false],
        ['Canon', false], ['Sony', false],
    ];

    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 70)->unique();
            $table->boolean('is_popular')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (self::INITIAL as $i => [$name, $popular]) {
            DB::table('brands')->insert([
                'name'       => $name,
                'slug'       => Str::slug($name),
                'is_popular' => $popular,
                'sort_order' => $i + 1,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
