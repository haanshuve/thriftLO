<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Foto profil unggahan sendiri (disk public, folder avatars)
            $table->string('avatar_path')->nullable()->after('email');
            // Foto akun Google, dipakai sebagai cadangan kalau belum mengunggah foto sendiri
            $table->string('google_avatar_url', 1000)->nullable()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'google_avatar_url']);
        });
    }
};
