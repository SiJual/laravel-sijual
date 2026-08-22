<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE "transactions" ALTER COLUMN "transaction_date" TYPE TIMESTAMP WITHOUT TIME ZONE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE "transactions" ALTER COLUMN "transaction_date" TYPE DATE');
    }
};
