<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * payment_qr_url: URL gambar QR dari Midtrans (action generate-qr-code), ditampilkan di halaman
 * bayar hanya di mode sandbox untuk membantu pengujian. Nullable: booking lama tidak memilikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_qr_url', 255)->nullable()->after('payment_qr_string');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('payment_qr_url');
        });
    }
};
