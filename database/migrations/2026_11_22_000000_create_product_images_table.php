<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Galeri foto produk (1-5 foto). Foto urutan 0 juga disalin ke products.image_url sebagai foto
        // utama katalog. Produk lama tanpa baris di sini tetap memakai products.image_url.
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
