<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['staff', 'viewer'])->default('staff');
            $table->enum('status', ['pending', 'accepted', 'expired'])->default('pending');
            $table->string('token')->unique();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->index('umkm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
