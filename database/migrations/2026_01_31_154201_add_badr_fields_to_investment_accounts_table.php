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
            // Business Asset Disposal Relief (BADR) fields
            $table->boolean('badr_eligible')->default(false)->after('bond_withdrawal_taken');
            $table->boolean('badr_is_employee')->default(false)->after('badr_eligible');
            $table->boolean('badr_trading_company')->default(false)->after('badr_is_employee');
            $table->boolean('badr_5_percent_holding')->default(false)->after('badr_trading_company');
            $table->boolean('badr_held_2_years')->default(false)->after('badr_5_percent_holding');
            $table->boolean('badr_emi_shares')->default(false)->after('badr_held_2_years');
            $table->decimal('badr_lifetime_used', 12, 2)->nullable()->after('badr_emi_shares');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'badr_eligible',
                'badr_is_employee',
                'badr_trading_company',
                'badr_5_percent_holding',
                'badr_held_2_years',
                'badr_emi_shares',
                'badr_lifetime_used',
            ]);
        });
    }
};
