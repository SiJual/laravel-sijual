<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('demographics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->foreignUuid('analysis_id')->nullable()->constrained('market_analyses')->cascadeOnDelete();
            $table->string('area_name');
            $table->jsonb('population_data')->default('{}');
            $table->jsonb('income_data')->default('{}');
            $table->jsonb('age_distribution')->default('{}');
            $table->enum('data_source', ['bps', 'osm'])->default('bps');
            $table->timestampTz('fetched_at')->useCurrent();
            $table->timestamps();

            $table->index('umkm_id');
            $table->index('analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demographics');
    }
};
