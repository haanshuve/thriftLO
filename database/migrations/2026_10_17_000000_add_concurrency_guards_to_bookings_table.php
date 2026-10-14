<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pengaman konkurensi booking COD:
 * - active_product_id: berisi product_id selama booking masih aktif (Pending/Confirmed), NULL kalau
 *   sudah selesai/dibatalkan. Unique index di kolom ini = satu booking aktif per produk.
 *   (MySQL tidak punya partial index, jadi pakai kolom bantu yang diisi App\Models\Booking.)
 * - idempotency_key: token formulir sekali pakai, mencegah booking ganda dari klik/kirim ulang.
 *
 * Aman dijalankan ulang dan aman untuk data lama: duplikat booking aktif dibatalkan dulu
 * (yang paling awal dibayar/dibuat dipertahankan) sebelum unique index dibuat.
 */
return new class extends Migration
{
    private const ACTIVE = ['Pending', 'Confirmed'];
    private const DUPLICATE_NOTE = 'dibatalkan otomatis oleh migrasi: duplikat';

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'active_product_id')) {
                $table->unsignedBigInteger('active_product_id')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('bookings', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('qr_token');
            }
        });

        $this->cancelDuplicateActiveBookings();

        // Isi ulang dari nol supaya konsisten walau migrasi pernah setengah jalan
        DB::table('bookings')->update(['active_product_id' => null]);
        DB::table('bookings')->whereIn('status_cod', self::ACTIVE)->update(['active_product_id' => DB::raw('product_id')]);

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasIndex('bookings', 'bookings_active_product_id_unique')) {
                $table->unique('active_product_id');
            }
            if (!Schema::hasIndex('bookings', 'bookings_idempotency_key_unique')) {
                $table->unique('idempotency_key');
            }
        });
    }

    // Per produk: booking aktif yang paling awal dibayar (atau dibuat) tetap aktif, sisanya dibatalkan.
    // Pembeli duplikat tidak bersalah, jadi biaya bookingnya ditandai dikembalikan.
    private function cancelDuplicateActiveBookings(): void
    {
        $active = DB::table('bookings')
            ->whereIn('status_cod', self::ACTIVE)
            ->orderBy('product_id')
            ->get(['id', 'product_id', 'paid_at', 'created_at', 'booking_fee']);

        $duplicates = $active->groupBy('product_id')
            ->filter(fn ($group) => $group->count() > 1)
            ->flatMap(fn ($group) => $group
                ->sortBy(fn ($b) => [(string) ($b->paid_at ?? $b->created_at ?? '9999'), $b->id])
                ->slice(1));

        foreach ($duplicates as $booking) {
            DB::table('bookings')->where('id', $booking->id)->update([
                'status_cod'        => 'Cancelled',
                'cancelled_by'      => 'system',
                'cancel_reason'     => self::DUPLICATE_NOTE,
                'cancelled_at'      => now(),
                'fee_status'        => (int) $booking->booking_fee > 0 ? 'refunded' : null,
                'active_product_id' => null,
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasIndex('bookings', 'bookings_active_product_id_unique')) {
                $table->dropUnique('bookings_active_product_id_unique');
            }
            if (Schema::hasIndex('bookings', 'bookings_idempotency_key_unique')) {
                $table->dropUnique('bookings_idempotency_key_unique');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $columns = array_values(array_filter(['active_product_id', 'idempotency_key'], fn ($c) => Schema::hasColumn('bookings', $c)));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
