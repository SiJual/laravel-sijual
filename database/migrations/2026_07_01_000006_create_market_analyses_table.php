<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('market_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->text('location_query')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->double('radius_km')->default(1.0);
            $table->integer('market_fit_score')->default(0);
            $table->jsonb('analysis_data')->default('{}');
            $table->jsonb('demographic_data')->default('{}');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('completed');
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->index('umkm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_analyses');
    }
};
