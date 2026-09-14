<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->enum('listing_mode', ['eceran', 'borongan']); // C2C vs B2B
            $table->enum('grade_condition', ['A', 'B', 'C']);
            $table->decimal('price', 12, 2);
            $table->string('image_path');
            $table->string('video_proof_path'); // Bukti fisik/fungsi anti-palsu
            $table->enum('status', ['available', 'booked', 'sold'])->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
