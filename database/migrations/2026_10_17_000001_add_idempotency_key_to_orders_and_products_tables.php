<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Token formulir sekali pakai untuk checkout Kirim (orders) dan form jual (products), supaya
 * klik ganda / kirim ulang tidak membuat pesanan atau produk duplikat. Data lama tetap NULL
 * (NULL boleh lebih dari satu di unique index MySQL maupun SQLite). Aman dijalankan ulang.
 */
return new class extends Migration
{
    private const TABLES = ['orders', 'products'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'idempotency_key')) {
                    $table->string('idempotency_key', 64)->nullable();
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasIndex($tableName, "{$tableName}_idempotency_key_unique")) {
                    $table->unique('idempotency_key');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasIndex($tableName, "{$tableName}_idempotency_key_unique")) {
                    $table->dropUnique("{$tableName}_idempotency_key_unique");
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'idempotency_key')) {
                    $table->dropColumn('idempotency_key');
                }
            });
        }
    }
};
