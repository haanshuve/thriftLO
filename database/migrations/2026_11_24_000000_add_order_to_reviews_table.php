<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Ulasan (bintang + komentar) kini juga bisa diberikan untuk pesanan kirim/escrow, bukan hanya
 * tiket COD. Satu ulasan berasal dari tepat satu transaksi: booking_id ATAU order_id.
 *
 * - Index unik mencegah ulasan ganda dari klik ganda / dua tab yang terkirim bersamaan.
 * - Ulasan tetap tersimpan walau barang/booking/pesanannya dihapus (referensinya jadi NULL),
 *   supaya penjual tidak bisa menghapus ulasan buruk dengan menghapus barang yang sudah terjual.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Data lama: kalau ada ulasan ganda untuk booking yang sama, simpan yang pertama saja
        $keep = DB::table('reviews')->selectRaw('MIN(id) as id')->groupBy('booking_id')->pluck('id');
        DB::table('reviews')->whereNotIn('id', $keep)->delete();

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreignId('order_id')->nullable()->after('booking_id');

            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->unique('booking_id');
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['order_id']);
            $table->dropUnique(['booking_id']);
            $table->dropUnique(['order_id']);
            $table->dropColumn('order_id');
        });

        // Ulasan yang barang/booking-nya sudah terhapus tidak muat di skema lama (kolom wajib)
        DB::table('reviews')->whereNull('booking_id')->orWhereNull('product_id')->delete();

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
