<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('kategori')->nullable();       // Fashion, Vintage Tech, dll
            $table->string('mode_jual')->nullable();      // ecer (C2C) / borongan (B2B)
            $table->string('grade')->nullable();          // Grade A/B/C
            $table->decimal('price', 12, 2);
            $table->string('image_path')->nullable();
            $table->string('image_url')->nullable();
            $table->string('video_proof')->nullable();    // Bukti fisik/fungsi anti-palsu
            $table->enum('status', ['Available', 'Booked', 'Sold Out'])->default('Available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
