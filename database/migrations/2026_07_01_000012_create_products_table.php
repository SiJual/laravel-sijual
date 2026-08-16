<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->enum('category', ['textiles', 'handicrafts', 'food_bev', 'services', 'other'])->nullable();
            $table->bigInteger('price')->default(0);
            $table->integer('stock_level')->default(0);
            $table->enum('status', ['in_stock', 'low_stock', 'out_of_stock'])->default('in_stock');
            $table->string('image_url')->nullable();
            $table->integer('low_stock_threshold')->default(5);
            $table->timestamps();

            $table->index(['umkm_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
