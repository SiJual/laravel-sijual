<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('content_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->enum('content_type', ['social_media', 'ad_copy', 'blog_post', 'email']);
            $table->text('prompt')->nullable();
            $table->text('generated_text')->nullable();
            $table->string('generated_image_url')->nullable();
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->jsonb('brand_metadata')->default('{}');
            $table->string('tone')->nullable();
            $table->string('style')->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['umkm_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_assets');
    }
};
