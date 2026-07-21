<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tax_strategy_household_inputs', 'spouse_existing_pension_balance')) {
            return;
        }

        Schema::table('tax_strategy_household_inputs', function (Blueprint $table) {
            $table->decimal('spouse_existing_pension_balance', 12, 2)
                ->nullable()
                ->after('spouse_existing_dividend_holdings_value')
                ->comment('Non-working spouse current personal-pension pot value (single_earner_couple path).');
        });
    }

    public function down(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table) {
            $table->dropColumn('spouse_existing_pension_balance');
        });
    }
};
