<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Merek dari daftar (opsional). Merek yang dihapus admin tidak menghapus produknya.
            $table->foreignId('brand_id')->nullable()->after('kategori')->constrained('brands')->nullOnDelete();
            // Merek yang tidak ada di daftar, diketik bebas oleh penjual
            $table->string('brand_other', 60)->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('brand_other');
        });
    }
};
