<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->enum('role', ['pembeli', 'penjual'])->default('pembeli');
            $table->string('nama_toko')->nullable();
            $table->string('lokasi_lapak')->nullable();
            $table->string('ktp_number')->nullable();
            $table->string('selfie_path')->nullable();
            $table->enum('seller_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
