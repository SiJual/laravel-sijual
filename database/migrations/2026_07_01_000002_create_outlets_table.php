<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('umkm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
