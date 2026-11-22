<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Titik ketemuan COD yang diusulkan pembeli di popup booking. cod_location tetap dipakai sebagai
 * keterangan tempat. Jarak disimpan saat booking dibuat (dihitung di server), supaya tidak berubah
 * kalau penjual memindahkan lokasi acuannya belakangan. Semua nullable: booking lama tidak memilikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('meeting_latitude', 10, 7)->nullable()->after('cod_location');
            $table->decimal('meeting_longitude', 10, 7)->nullable()->after('meeting_latitude');
            $table->decimal('buyer_latitude', 10, 7)->nullable()->after('meeting_longitude');
            $table->decimal('buyer_longitude', 10, 7)->nullable()->after('buyer_latitude');
            $table->decimal('seller_distance_km', 6, 1)->nullable()->after('buyer_longitude');
            $table->decimal('buyer_distance_km', 6, 1)->nullable()->after('seller_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'meeting_latitude', 'meeting_longitude', 'buyer_latitude', 'buyer_longitude',
                'seller_distance_km', 'buyer_distance_km',
            ]);
        });
    }
};
