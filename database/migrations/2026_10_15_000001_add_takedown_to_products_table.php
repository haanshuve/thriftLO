<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Produk yang diturunkan admin karena melanggar; alasannya terlihat oleh penjual
            $table->timestamp('taken_down_at')->nullable()->after('status');
            $table->string('takedown_reason', 500)->nullable()->after('taken_down_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['taken_down_at', 'takedown_reason']);
        });
    }
};
