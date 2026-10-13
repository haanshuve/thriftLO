<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // File video yang diunggah penjual; kolom video_proof lama tetap menyimpan link produk lama
            $table->string('video_proof_path')->nullable()->after('video_proof');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('video_proof_path');
        });
    }
};
