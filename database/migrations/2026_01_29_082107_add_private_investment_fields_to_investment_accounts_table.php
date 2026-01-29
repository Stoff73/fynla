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
        Schema::table('investment_accounts', function (Blueprint $table) {
            // Company Details
            $table->string('company_legal_name', 255)->nullable()->after('account_type_other');
            $table->string('company_registration_number', 50)->nullable()->after('company_legal_name');
            $table->string('company_country', 100)->nullable()->after('company_registration_number');
            $table->string('company_website', 255)->nullable()->after('company_country');
            $table->string('company_trading_name', 255)->nullable()->after('company_website');
            $table->string('company_sector', 100)->nullable()->after('company_trading_name');
            $table->string('crowdfunding_platform', 255)->nullable()->after('company_sector');

            // Investment Details
            $table->date('investment_date')->nullable()->after('crowdfunding_platform');
            $table->decimal('investment_amount', 15, 2)->nullable()->after('investment_date');
            $table->string('investment_currency', 3)->default('GBP')->after('investment_amount');
            $table->string('funding_round', 50)->nullable()->after('investment_currency');
            $table->decimal('pre_money_valuation', 15, 2)->nullable()->after('funding_round');
            $table->decimal('post_money_valuation', 15, 2)->nullable()->after('pre_money_valuation');
            $table->decimal('price_per_share', 12, 6)->nullable()->after('post_money_valuation');
            $table->integer('number_of_shares')->nullable()->after('price_per_share');
            $table->string('instrument_type', 50)->nullable()->after('number_of_shares');

            // Ownership & Legal
            $table->string('share_class', 100)->nullable()->after('instrument_type');
            $table->boolean('has_voting_rights')->default(true)->after('share_class');
            $table->boolean('has_dividend_rights')->default(true)->after('has_voting_rights');
            $table->string('liquidation_preference', 100)->nullable()->after('has_dividend_rights');
            $table->boolean('has_anti_dilution')->default(false)->after('liquidation_preference');
            $table->string('holding_structure', 20)->default('direct')->after('has_anti_dilution');
            $table->string('nominee_name', 255)->nullable()->after('holding_structure');
            $table->text('conversion_terms')->nullable()->after('nominee_name');
            $table->decimal('interest_rate', 5, 2)->nullable()->after('conversion_terms');
            $table->date('maturity_date')->nullable()->after('interest_rate');

            // UK Tax Relief
            $table->string('tax_relief_type', 20)->nullable()->after('maturity_date');
            $table->string('eis3_certificate_number', 50)->nullable()->after('tax_relief_type');
            $table->string('hmrc_reference', 50)->nullable()->after('eis3_certificate_number');
            $table->date('relief_claimed_date')->nullable()->after('hmrc_reference');
            $table->decimal('relief_amount_claimed', 12, 2)->nullable()->after('relief_claimed_date');
            $table->date('disposal_restriction_date')->nullable()->after('relief_amount_claimed');
            $table->boolean('clawback_risk')->default(false)->after('disposal_restriction_date');
            $table->text('clawback_notes')->nullable()->after('clawback_risk');

            // Status & Valuation
            $table->decimal('latest_valuation', 15, 2)->nullable()->after('clawback_notes');
            $table->date('latest_valuation_date')->nullable()->after('latest_valuation');
            $table->decimal('current_ownership_percent', 5, 4)->nullable()->after('latest_valuation_date');
            $table->string('company_status', 20)->default('active')->after('current_ownership_percent');
            $table->text('status_notes')->nullable()->after('company_status');

            // Exit Tracking
            $table->string('exit_type', 30)->nullable()->after('status_notes');
            $table->date('exit_date')->nullable()->after('exit_type');
            $table->decimal('exit_gross_proceeds', 15, 2)->nullable()->after('exit_date');
            $table->decimal('exit_fees', 12, 2)->nullable()->after('exit_gross_proceeds');
            $table->decimal('exit_net_proceeds', 15, 2)->nullable()->after('exit_fees');
            $table->decimal('exit_moic', 6, 2)->nullable()->after('exit_net_proceeds');
            $table->boolean('loss_relief_eligible')->default(false)->after('exit_moic');
            $table->decimal('capital_loss_amount', 15, 2)->nullable()->after('loss_relief_eligible');
            $table->boolean('negligible_value_claim')->default(false)->after('capital_loss_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            // Company Details
            $table->dropColumn([
                'company_legal_name',
                'company_registration_number',
                'company_country',
                'company_website',
                'company_trading_name',
                'company_sector',
                'crowdfunding_platform',
            ]);

            // Investment Details
            $table->dropColumn([
                'investment_date',
                'investment_amount',
                'investment_currency',
                'funding_round',
                'pre_money_valuation',
                'post_money_valuation',
                'price_per_share',
                'number_of_shares',
                'instrument_type',
            ]);

            // Ownership & Legal
            $table->dropColumn([
                'share_class',
                'has_voting_rights',
                'has_dividend_rights',
                'liquidation_preference',
                'has_anti_dilution',
                'holding_structure',
                'nominee_name',
                'conversion_terms',
                'interest_rate',
                'maturity_date',
            ]);

            // UK Tax Relief
            $table->dropColumn([
                'tax_relief_type',
                'eis3_certificate_number',
                'hmrc_reference',
                'relief_claimed_date',
                'relief_amount_claimed',
                'disposal_restriction_date',
                'clawback_risk',
                'clawback_notes',
            ]);

            // Status & Valuation
            $table->dropColumn([
                'latest_valuation',
                'latest_valuation_date',
                'current_ownership_percent',
                'company_status',
                'status_notes',
            ]);

            // Exit Tracking
            $table->dropColumn([
                'exit_type',
                'exit_date',
                'exit_gross_proceeds',
                'exit_fees',
                'exit_net_proceeds',
                'exit_moic',
                'loss_relief_eligible',
                'capital_loss_amount',
                'negligible_value_claim',
            ]);
        });
    }
};
