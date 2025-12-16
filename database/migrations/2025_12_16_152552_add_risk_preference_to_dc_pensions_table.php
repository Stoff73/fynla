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
        Schema::table('dc_pensions', function (Blueprint $table) {
            // Add risk preference for this specific pension
            $table->enum('risk_preference', ['low', 'lower_medium', 'medium', 'upper_medium', 'high'])
                  ->nullable();

            // Flag to indicate this pension has a different risk level than the user's main profile
            $table->boolean('has_custom_risk')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn(['risk_preference', 'has_custom_risk']);
        });
    }
};
