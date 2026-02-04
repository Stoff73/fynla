<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends user_assumptions to support estate planning assumptions:
     * - Adds 'estate_planning' to assumption_type enum
     * - Adds property_growth_rate for estate projections
     * - Adds investment_growth_method (monte_carlo or custom)
     * - Adds custom_investment_rate for user-specified growth rates
     */
    public function up(): void
    {
        // First, modify the enum to include estate_planning
        // MySQL requires recreating the column to change enum values
        DB::statement("ALTER TABLE user_assumptions MODIFY COLUMN assumption_type ENUM('pensions', 'investments', 'estate_planning') NOT NULL");

        // Add estate planning specific columns
        Schema::table('user_assumptions', function (Blueprint $table) {
            // Property growth rate for estate projections (default 3%)
            $table->decimal('property_growth_rate', 5, 2)->nullable()->after('compound_periods');

            // Investment growth method: monte_carlo (80% confidence) or custom
            $table->enum('investment_growth_method', ['monte_carlo', 'custom'])
                ->default('monte_carlo')
                ->after('property_growth_rate');

            // Custom investment rate (only used when investment_growth_method = 'custom')
            $table->decimal('custom_investment_rate', 5, 2)->nullable()->after('investment_growth_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove estate planning specific columns
        Schema::table('user_assumptions', function (Blueprint $table) {
            $table->dropColumn(['property_growth_rate', 'investment_growth_method', 'custom_investment_rate']);
        });

        // Delete any estate_planning records first
        DB::table('user_assumptions')->where('assumption_type', 'estate_planning')->delete();

        // Revert enum to original values
        DB::statement("ALTER TABLE user_assumptions MODIFY COLUMN assumption_type ENUM('pensions', 'investments') NOT NULL");
    }
};
