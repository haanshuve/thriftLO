<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Opsi pengiriman dari penjual: [{"courier": "JNE Reguler", "cost": 18000}, ...]
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('shipping_options')->nullable()->after('video_proof');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shipping_options');
        });
    }
};
