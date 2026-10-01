<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The interest the spouse receives each year on their own savings. Their
 * Personal Savings Allowance and starting rate for savings depend on it (ITA
 * 2007 s12, s12B), so it decides what moving savings to them saves (CSJ
 * 2026-10-01, D1/D2). Nullable: "not asked" stays distinct from "none".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table): void {
            $table->decimal('spouse_annual_savings_interest', 12, 2)->nullable()->after('spouse_existing_savings_balance');
        });
    }

    public function down(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table): void {
            $table->dropColumn('spouse_annual_savings_interest');
        });
    }
};
