<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('publish_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->constrained('content_assets')->cascadeOnDelete();
            $table->enum('platform', ['instagram', 'facebook']);
            $table->enum('status', ['scheduled', 'publishing', 'published', 'failed'])->default('scheduled');
            $table->jsonb('platform_response')->default('{}');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->index('content_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_jobs');
    }
};
