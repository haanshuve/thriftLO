<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Pembeli yang minta
            $table->string('nama_barang'); // Nama barang yang dicari
            $table->string('kategori'); // Fashion, Vintage Tech, dll
            $table->decimal('budget_maksimal', 12, 2); // Budget maksimal pembeli
            $table->string('lokasi_cod'); // Lokasi preferensi COD Batam
            $table->text('deskripsi'); // Detail spesifikasi barang yang dicari
            $table->enum('status', ['Open', 'Fulfilled'])->default('Open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};
