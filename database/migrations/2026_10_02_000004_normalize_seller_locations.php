<?php

use App\Support\SellerLocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Lokasi lapak penjual lama (teks bebas) dicocokkan ke pilihan dropdown; yang tidak jelas jadi "Luar Batam"
    public function up(): void
    {
        DB::table('users')->where('role', 'penjual')->orderBy('id')->each(function ($user) {
            $location = SellerLocation::match($user->lokasi_lapak);

            if ($location !== $user->lokasi_lapak) {
                DB::table('users')->where('id', $user->id)->update(['lokasi_lapak' => $location]);
            }
        });
    }

    // Teks lokasi lama tidak disimpan, jadi migrasi ini tidak bisa dibalik
    public function down(): void
    {
    }
};
