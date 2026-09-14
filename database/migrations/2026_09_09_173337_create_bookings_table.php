<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Pembeli
            $table->string('qr_token')->unique();
            $table->enum('status_cod', ['Pending', 'Confirmed', 'Completed', 'Cancelled'])->default('Pending');
            $table->dateTime('cod_schedule');
            $table->string('cod_location')->default('Batam Center / Lokasi Publik');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
