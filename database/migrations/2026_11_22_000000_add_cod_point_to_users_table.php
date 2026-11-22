<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Titik COD penjual Batam: patokan alamat (teks) + pin peta. Semua kosong = belum diatur.
            $table->string('cod_address', 150)->nullable()->after('kota_lapak');
            $table->decimal('cod_latitude', 10, 7)->nullable()->after('cod_address');
            $table->decimal('cod_longitude', 10, 7)->nullable()->after('cod_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cod_address', 'cod_latitude', 'cod_longitude']);
        });
    }
};
