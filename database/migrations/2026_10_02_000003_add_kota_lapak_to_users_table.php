<?php

use App\Support\SellerLocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kota asal penjual "Luar Batam" (mis. Surabaya), ditampilkan ke pembeli sebagai asal pengiriman.
     * Sengaja dijalankan sebelum migrasi normalisasi lokasi: teks lokasi lama yang akan menjadi
     * "Luar Batam" disalin dulu ke kota_lapak supaya nama kotanya tidak hilang.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kota_lapak', 100)->nullable()->after('lokasi_lapak');
        });

        DB::table('users')->where('role', 'penjual')->whereNotNull('lokasi_lapak')->orderBy('id')->each(function ($user) {
            $text = trim($user->lokasi_lapak);

            if ($text !== '' && $text !== SellerLocation::OUTSIDE_BATAM && SellerLocation::match($text) === SellerLocation::OUTSIDE_BATAM) {
                DB::table('users')->where('id', $user->id)->update(['kota_lapak' => mb_substr($text, 0, 100)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kota_lapak');
        });
    }
};
