<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Transaksi lewat pengiriman (penjual di luar Batam). Transaksi COD tetap di tabel bookings.
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Pembeli
            $table->string('courier');
            $table->unsignedInteger('shipping_cost');
            $table->decimal('item_price', 12, 2);   // Harga barang saat checkout
            $table->decimal('total_price', 12, 2);  // Harga barang + ongkir
            $table->text('shipping_address');
            $table->string('status')->default('Awaiting Shipment');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
