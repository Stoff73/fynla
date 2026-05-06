<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_strategy_household_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Working-spouse fields (path B / dual_earner)
            $table->decimal('spouse_annual_income', 12, 2)->nullable();
            $table->string('spouse_employment_status', 32)->nullable();
            $table->decimal('spouse_isa_balance', 12, 2)->nullable();
            $table->string('spouse_psa_band', 16)->nullable();
            $table->decimal('spouse_unrealised_gains', 12, 2)->nullable();
            $table->decimal('spouse_annual_dividends', 12, 2)->nullable();
            $table->decimal('spouse_pension_input_annual', 12, 2)->nullable();

            // Non-working-spouse fields (path C / single_earner_couple)
            $table->decimal('spouse_existing_isa_balance', 12, 2)->nullable();
            $table->decimal('spouse_existing_savings_balance', 12, 2)->nullable();
            $table->decimal('spouse_existing_investment_balance', 12, 2)->nullable();
            $table->decimal('spouse_existing_dividend_holdings_value', 12, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_strategy_household_inputs');
    }
};
