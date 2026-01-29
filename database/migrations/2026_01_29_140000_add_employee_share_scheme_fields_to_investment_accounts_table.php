<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds employee share scheme tracking fields for UK schemes:
     * - SAYE (Sharesave)
     * - CSOP (Company Share Option Plan)
     * - EMI (Enterprise Management Incentives)
     * - Unapproved Options
     * - RSUs (Restricted Stock Units)
     */
    public function up(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            // ========================================
            // Group 1: Employer Details (8 columns)
            // ========================================
            $table->string('employer_name', 255)->nullable()->after('negligible_value_claim');
            $table->string('employer_registration', 50)->nullable()->after('employer_name');
            $table->string('employer_ticker', 20)->nullable()->after('employer_registration');
            $table->boolean('employer_is_listed')->default(false)->after('employer_ticker');
            $table->string('parent_company_name', 255)->nullable()->after('employer_is_listed');
            $table->string('parent_company_country', 100)->nullable()->after('parent_company_name');
            $table->string('ers_scheme_reference', 50)->nullable()->after('parent_company_country');
            $table->boolean('ers_registered')->default(false)->after('ers_scheme_reference');

            // ========================================
            // Group 2: Grant Details (10 columns)
            // ========================================
            $table->date('grant_date')->nullable()->after('ers_registered');
            $table->string('grant_reference', 100)->nullable()->after('grant_date');
            $table->integer('units_granted')->nullable()->after('grant_reference');
            $table->decimal('exercise_price', 12, 4)->nullable()->after('units_granted');
            $table->decimal('market_value_at_grant', 12, 4)->nullable()->after('exercise_price');
            $table->string('share_class_scheme', 100)->nullable()->after('market_value_at_grant');
            $table->string('grant_currency', 3)->default('GBP')->after('share_class_scheme');
            $table->decimal('option_price_paid', 12, 2)->nullable()->after('grant_currency');
            $table->date('scheme_start_date')->nullable()->after('option_price_paid');
            $table->integer('scheme_duration_months')->nullable()->after('scheme_start_date');

            // ========================================
            // Group 3: Vesting Schedule (12 columns)
            // ========================================
            $table->string('vesting_type', 30)->nullable()->after('scheme_duration_months');
            $table->date('cliff_date')->nullable()->after('vesting_type');
            $table->integer('cliff_percentage')->nullable()->after('cliff_date');
            $table->integer('vesting_period_months')->nullable()->after('cliff_percentage');
            $table->integer('vesting_frequency_months')->nullable()->after('vesting_period_months');
            $table->boolean('has_performance_conditions')->default(false)->after('vesting_frequency_months');
            $table->text('performance_conditions_description')->nullable()->after('has_performance_conditions');
            $table->date('performance_period_end')->nullable()->after('performance_conditions_description');
            $table->integer('performance_vesting_min_percent')->nullable()->after('performance_period_end');
            $table->integer('performance_vesting_max_percent')->nullable()->after('performance_vesting_min_percent');
            $table->date('full_vest_date')->nullable()->after('performance_vesting_max_percent');
            $table->boolean('accelerated_vesting_allowed')->default(false)->after('full_vest_date');

            // ========================================
            // Group 4: Current Status (8 columns)
            // ========================================
            $table->integer('units_vested')->default(0)->after('accelerated_vesting_allowed');
            $table->integer('units_unvested')->default(0)->after('units_vested');
            $table->integer('units_exercised')->default(0)->after('units_unvested');
            $table->integer('units_forfeited')->default(0)->after('units_exercised');
            $table->integer('units_expired')->default(0)->after('units_forfeited');
            $table->string('scheme_status', 30)->default('active')->after('units_expired');
            $table->decimal('current_share_price', 12, 4)->nullable()->after('scheme_status');
            $table->date('share_price_date')->nullable()->after('current_share_price');

            // ========================================
            // Group 5: Exercise & Expiry (6 columns)
            // ========================================
            $table->date('exercise_window_start')->nullable()->after('share_price_date');
            $table->date('exercise_window_end')->nullable()->after('exercise_window_start');
            $table->date('last_exercise_date')->nullable()->after('exercise_window_end');
            $table->decimal('total_exercise_proceeds', 15, 2)->nullable()->after('last_exercise_date');
            $table->decimal('total_exercise_cost', 15, 2)->nullable()->after('total_exercise_proceeds');
            $table->text('exercise_history_json')->nullable()->after('total_exercise_cost');

            // ========================================
            // Group 6: Tax Treatment (8 columns)
            // ========================================
            $table->string('tax_treatment', 30)->nullable()->after('exercise_history_json');
            $table->boolean('is_readily_convertible_asset')->nullable()->after('tax_treatment');
            $table->boolean('paye_via_payroll')->default(true)->after('is_readily_convertible_asset');
            $table->decimal('income_tax_at_vest_exercise', 15, 2)->nullable()->after('paye_via_payroll');
            $table->decimal('ni_at_vest_exercise', 15, 2)->nullable()->after('income_tax_at_vest_exercise');
            $table->boolean('csop_disqualifying_event')->default(false)->after('ni_at_vest_exercise');
            $table->date('csop_three_year_date')->nullable()->after('csop_disqualifying_event');
            $table->decimal('cost_basis_for_cgt', 15, 2)->nullable()->after('csop_three_year_date');

            // ========================================
            // Group 7: SAYE-Specific (5 columns)
            // ========================================
            $table->decimal('saye_monthly_savings', 10, 2)->nullable()->after('cost_basis_for_cgt');
            $table->decimal('saye_current_savings_balance', 15, 2)->nullable()->after('saye_monthly_savings');
            $table->date('saye_maturity_date')->nullable()->after('saye_current_savings_balance');
            $table->decimal('saye_option_discount_percent', 5, 2)->nullable()->after('saye_maturity_date');
            $table->decimal('saye_bonus_amount', 12, 2)->nullable()->after('saye_option_discount_percent');

            // ========================================
            // Group 8: Leaver Terms (4 columns)
            // ========================================
            $table->string('leaver_category', 30)->nullable()->after('saye_bonus_amount');
            $table->integer('post_termination_exercise_days')->nullable()->after('leaver_category');
            $table->date('termination_date')->nullable()->after('post_termination_exercise_days');
            $table->text('leaver_notes')->nullable()->after('termination_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            // Group 1: Employer Details
            $table->dropColumn([
                'employer_name',
                'employer_registration',
                'employer_ticker',
                'employer_is_listed',
                'parent_company_name',
                'parent_company_country',
                'ers_scheme_reference',
                'ers_registered',
            ]);

            // Group 2: Grant Details
            $table->dropColumn([
                'grant_date',
                'grant_reference',
                'units_granted',
                'exercise_price',
                'market_value_at_grant',
                'share_class_scheme',
                'grant_currency',
                'option_price_paid',
                'scheme_start_date',
                'scheme_duration_months',
            ]);

            // Group 3: Vesting Schedule
            $table->dropColumn([
                'vesting_type',
                'cliff_date',
                'cliff_percentage',
                'vesting_period_months',
                'vesting_frequency_months',
                'has_performance_conditions',
                'performance_conditions_description',
                'performance_period_end',
                'performance_vesting_min_percent',
                'performance_vesting_max_percent',
                'full_vest_date',
                'accelerated_vesting_allowed',
            ]);

            // Group 4: Current Status
            $table->dropColumn([
                'units_vested',
                'units_unvested',
                'units_exercised',
                'units_forfeited',
                'units_expired',
                'scheme_status',
                'current_share_price',
                'share_price_date',
            ]);

            // Group 5: Exercise & Expiry
            $table->dropColumn([
                'exercise_window_start',
                'exercise_window_end',
                'last_exercise_date',
                'total_exercise_proceeds',
                'total_exercise_cost',
                'exercise_history_json',
            ]);

            // Group 6: Tax Treatment
            $table->dropColumn([
                'tax_treatment',
                'is_readily_convertible_asset',
                'paye_via_payroll',
                'income_tax_at_vest_exercise',
                'ni_at_vest_exercise',
                'csop_disqualifying_event',
                'csop_three_year_date',
                'cost_basis_for_cgt',
            ]);

            // Group 7: SAYE-Specific
            $table->dropColumn([
                'saye_monthly_savings',
                'saye_current_savings_balance',
                'saye_maturity_date',
                'saye_option_discount_percent',
                'saye_bonus_amount',
            ]);

            // Group 8: Leaver Terms
            $table->dropColumn([
                'leaver_category',
                'post_termination_exercise_days',
                'termination_date',
                'leaver_notes',
            ]);
        });
    }
};
