<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('umkm_id')->constrained('umkm_profiles')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->bigInteger('amount')->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->enum('source', ['voice', 'manual', 'qris'])->default('manual');
            $table->string('payment_method')->default('cash');
            $table->string('merchant_name')->nullable();
            $table->jsonb('ai_metadata')->default('{}');
            $table->boolean('is_verified')->default(true);
            $table->date('transaction_date')->default(now());
            $table->timestamps();

            $table->index(['umkm_id', 'outlet_id', 'transaction_date']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
