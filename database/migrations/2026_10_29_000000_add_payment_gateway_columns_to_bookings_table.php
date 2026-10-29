<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pembayaran biaya booking lewat payment gateway (PAYMENT_DRIVER=midtrans).
 * Semua kolom nullable: booking lama dan booking mode simulasi tidak memakainya.
 * - payment_order_id: order_id unik yang dikirim ke Midtrans, kunci pencarian webhook.
 * - payment_status: transaction_status terakhir dari Midtrans (pending, settlement, expire, ...).
 * - payment_qr_string: isi QRIS untuk dirender ulang di halaman bayar.
 * - payment_expires_at: batas bayar; lewat dari ini booking dilepas walau webhook tidak datang.
 * status_cod mendapat nilai baru AwaitingPayment (hanya menambah, nilai lama tetap).
 */
return new class extends Migration
{
    private const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status_cod', [...self::STATUSES, 'AwaitingPayment'])->default('Pending')->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_order_id', 64)->nullable()->unique()->after('paid_at');
            $table->string('payment_status', 20)->nullable()->after('payment_order_id');
            $table->text('payment_qr_string')->nullable()->after('payment_status');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_qr_string');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_payment_order_id_unique');
            $table->dropColumn(['payment_order_id', 'payment_status', 'payment_qr_string', 'payment_expires_at']);
        });

        // Booking yang masih menunggu pembayaran tidak punya padanan lama: anggap dibatalkan sistem
        DB::table('bookings')->where('status_cod', 'AwaitingPayment')->update([
            'status_cod'        => 'Cancelled',
            'cancelled_by'      => 'system',
            'cancel_reason'     => 'Pembayaran QRIS kedaluwarsa',
            'active_product_id' => null,
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status_cod', self::STATUSES)->default('Pending')->change();
        });
    }
};
