<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_id')->constrained('market_analyses')->cascadeOnDelete();
            $table->string('name');
            $table->string('business_type')->nullable();
            $table->text('address')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->double('rating')->default(0.0);
            $table->integer('review_count')->default(0);
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
            $table->jsonb('scraped_data')->default('{}');
            $table->timestamps();

            $table->index('analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};
