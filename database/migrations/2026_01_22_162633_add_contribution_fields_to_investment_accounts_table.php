<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add contribution tracking fields to investment_accounts table.
     * This enables tracking of regular contributions and planned lump sums
     * for expenditure calculations in the user profile.
     */
    public function up(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            // Regular contribution fields (like DC pensions) - only add if not exists
            if (! Schema::hasColumn('investment_accounts', 'monthly_contribution_amount')) {
                $table->decimal('monthly_contribution_amount', 12, 2)->nullable()->after('contributions_ytd')
                    ->comment('Regular monthly contribution amount');
            }
            if (! Schema::hasColumn('investment_accounts', 'contribution_frequency')) {
                $table->enum('contribution_frequency', ['monthly', 'quarterly', 'annually'])
                    ->default('monthly')->after('monthly_contribution_amount')
                    ->comment('How often regular contributions are made');
            }

            // Planned lump sum fields - only add if not exists
            if (! Schema::hasColumn('investment_accounts', 'planned_lump_sum_amount')) {
                $table->decimal('planned_lump_sum_amount', 12, 2)->nullable()->after('contribution_frequency')
                    ->comment('One-off lump sum contribution planned');
            }
            if (! Schema::hasColumn('investment_accounts', 'planned_lump_sum_date')) {
                $table->date('planned_lump_sum_date')->nullable()->after('planned_lump_sum_amount')
                    ->comment('Date when lump sum will be contributed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_contribution_amount',
                'contribution_frequency',
                'planned_lump_sum_amount',
                'planned_lump_sum_date',
            ]);
        });
    }
};
