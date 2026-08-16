<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->enum('type', ['daily', 'weekly', 'monthly']);
            $table->date('period_start');
            $table->date('period_end');
            $table->jsonb('data')->default('{}');
            $table->string('file_url')->nullable();
            $table->bigInteger('total_income')->default(0);
            $table->bigInteger('total_expense')->default(0);
            $table->bigInteger('net_profit')->default(0);
            $table->integer('transaction_count')->default(0);
            $table->timestamps();

            $table->index('umkm_id');
            $table->index(['umkm_id', 'type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
