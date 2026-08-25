<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a SiKas transaction to the SiStok product it was recorded for,
     * so selling a product can move its stock level.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('product_id')->nullable()->after('category_id')
                ->constrained('products')->nullOnDelete();
            $table->integer('quantity')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('quantity');
        });
    }
};
