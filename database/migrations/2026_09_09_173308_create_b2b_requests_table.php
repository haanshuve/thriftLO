<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('detail_kebutuhan');
            $table->string('budget_range');
            $table->enum('status', ['Open', 'Fulfilled'])->default('Open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_requests');
    }
};
