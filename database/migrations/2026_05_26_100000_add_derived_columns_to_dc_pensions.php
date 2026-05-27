<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->decimal('current_fund_value_gbp', 14, 2)->nullable()->after('current_fund_value');
            $table->timestamp('current_fund_value_gbp_calculated_at')->nullable()->after('current_fund_value_gbp');

            $table->decimal('projected_value_at_retirement_gbp', 14, 2)->nullable()->after('projected_value_at_retirement');
            $table->timestamp('projected_value_at_retirement_gbp_calculated_at')->nullable()->after('projected_value_at_retirement_gbp');

            $table->decimal('annual_contribution_gbp', 14, 2)->nullable()->after('monthly_contribution_amount');
            $table->timestamp('annual_contribution_gbp_calculated_at')->nullable()->after('annual_contribution_gbp');

            $table->decimal('annual_allowance_used_gbp', 8, 2)->nullable()->after('annual_contribution_gbp_calculated_at');
            $table->timestamp('annual_allowance_used_gbp_calculated_at')->nullable()->after('annual_allowance_used_gbp');

            $table->integer('years_to_drawdown')->nullable()->after('retirement_age');
            $table->timestamp('years_to_drawdown_calculated_at')->nullable()->after('years_to_drawdown');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'current_fund_value_gbp', 'current_fund_value_gbp_calculated_at',
                'projected_value_at_retirement_gbp', 'projected_value_at_retirement_gbp_calculated_at',
                'annual_contribution_gbp', 'annual_contribution_gbp_calculated_at',
                'years_to_drawdown', 'years_to_drawdown_calculated_at',
                'annual_allowance_used_gbp', 'annual_allowance_used_gbp_calculated_at',
            ]);
        });
    }
};
