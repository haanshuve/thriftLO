<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Catatan pembayaran langganan penjual. idempotency_key (unique) mencegah satu formulir
 * bayar diproses dua kali. Data awal: satu baris "migrasi" per penjual yang sudah pernah
 * berlangganan (riwayat pembayaran sebelumnya tidak tercatat, jadi hanya periode terakhir).
 * Aman dijalankan ulang: tabel dibuat sekali, isi awal tidak digandakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('idempotency_key', 64)->nullable()->unique();
                $table->unsignedInteger('amount');
                $table->unsignedSmallInteger('days');
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('period_ends_at')->nullable();
                $table->string('source', 20)->default('simulasi'); // simulasi | migrasi | (nanti) midtrans
                $table->timestamps();
            });
        }

        $this->backfillExistingSubscriptions();
    }

    private function backfillExistingSubscriptions(): void
    {
        $days = (int) config('thriftlo.subscription.days', 30);
        $price = (int) config('thriftlo.subscription.price', 0);

        $alreadyRecorded = DB::table('subscription_payments')->pluck('user_id')->flip();

        DB::table('users')->whereNotNull('subscription_expires_at')->orderBy('id')
            ->get(['id', 'subscription_expires_at'])
            ->reject(fn ($user) => $alreadyRecorded->has($user->id))
            ->each(function ($user) use ($days, $price) {
                $endsAt = Carbon::parse($user->subscription_expires_at);

                DB::table('subscription_payments')->insert([
                    'user_id'         => $user->id,
                    'idempotency_key' => null,
                    'amount'          => $price,
                    'days'            => $days,
                    'paid_at'         => $endsAt->copy()->subDays($days),
                    'period_ends_at'  => $endsAt,
                    'source'          => 'migrasi',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
