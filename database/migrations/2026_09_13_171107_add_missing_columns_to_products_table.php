<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'mode_jual')) {
                $table->string('mode_jual')->nullable();
            }
            if (!Schema::hasColumn('products', 'grade')) {
                $table->string('grade')->nullable();
            }
            if (!Schema::hasColumn('products', 'video_proof')) {
                $table->string('video_proof')->nullable();
            }
            if (!Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['mode_jual', 'grade', 'video_proof', 'description']);
        });
    }
};
