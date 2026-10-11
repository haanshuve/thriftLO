<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Escrow: dana ditahan platform sampai pembeli konfirmasi terima (atau auto-release),
    // lalu dicairkan ke penjual setelah dipotong komisi platform
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('shipped_at');
            $table->string('release_method')->nullable()->after('released_at'); // 'buyer' atau 'auto'
            $table->unsignedInteger('biaya_platform')->default(0)->after('release_method'); // Komisi yang dipotong saat cair
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['released_at', 'release_method', 'biaya_platform']);
        });
    }
};
