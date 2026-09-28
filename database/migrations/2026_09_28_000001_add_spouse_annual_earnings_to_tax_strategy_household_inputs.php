<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The part of the spouse's income that is earnings from work. Pension tax
 * relief is capped at relevant UK earnings (FA 2004 s189-190), which a total
 * that includes pension or rent overstates (CSJ 2026-09-28). Nullable: "not
 * asked" stays distinct from "earns nothing".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table): void {
            $table->decimal('spouse_annual_earnings', 12, 2)->nullable()->after('spouse_annual_income');
        });
    }

    public function down(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table): void {
            $table->dropColumn('spouse_annual_earnings');
        });
    }
};
