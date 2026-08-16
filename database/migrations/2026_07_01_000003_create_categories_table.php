<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->nullable()->constrained('umkm_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('umkm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
