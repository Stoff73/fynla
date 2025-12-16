<?php

declare(strict_types=1);

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
        Schema::table('risk_profiles', function (Blueprint $table) {
            // Add new 5-level risk preference column
            $table->enum('risk_level', ['low', 'lower_medium', 'medium', 'upper_medium', 'high'])
                  ->nullable()
                  ->after('risk_tolerance');

            // Add risk profile completion timestamp
            $table->timestamp('risk_assessed_at')->nullable()->after('esg_preference');

            // Add self-assessment flag (vs questionnaire-derived)
            $table->boolean('is_self_assessed')->default(true)->after('risk_assessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_profiles', function (Blueprint $table) {
            $table->dropColumn(['risk_level', 'risk_assessed_at', 'is_self_assessed']);
        });
    }
};
