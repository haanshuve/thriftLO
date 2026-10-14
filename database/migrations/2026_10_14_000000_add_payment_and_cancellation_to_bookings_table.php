<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Biaya booking COD yang dibayar lewat QRIS (booking lama: 0, tanpa status)
            $table->unsignedInteger('booking_fee')->default(0)->after('status_cod');
            $table->string('fee_status', 20)->nullable()->after('booking_fee'); // paid | forfeited | refunded
            $table->timestamp('paid_at')->nullable()->after('fee_status');

            // Siapa yang membatalkan, alasannya, dan kapan
            $table->string('cancelled_by', 10)->nullable()->after('paid_at'); // buyer | seller
            $table->string('cancel_reason', 50)->nullable()->after('cancelled_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['booking_fee', 'fee_status', 'paid_at', 'cancelled_by', 'cancel_reason', 'cancelled_at']);
        });
    }
};
