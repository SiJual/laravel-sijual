<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('umkm_profiles', function (Blueprint $table) {
            $table->bigInteger('target_cuan')->default(100000)->after('profile_completeness');
            $table->string('target_cuan_period')->default('monthly')->after('target_cuan');
            $table->jsonb('financial_settings')->nullable()->after('target_cuan_period');
        });
    }

    public function down(): void
    {
        Schema::table('umkm_profiles', function (Blueprint $table) {
            $table->dropColumn(['target_cuan', 'target_cuan_period', 'financial_settings']);
        });
    }
};
