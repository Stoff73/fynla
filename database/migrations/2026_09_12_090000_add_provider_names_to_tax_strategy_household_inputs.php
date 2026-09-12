<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SaveTax spouse step records the spouse's ISA and pension as household
 * figures (no spouse account exists yet). The user names the providers in the
 * same breath ("an ISA with Halifax", "an Aviva pension"); keep them so the
 * spouse rows read like every other record (CSJ 2026-09-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table) {
            $table->string('spouse_isa_provider', 120)->nullable()->after('spouse_isa_balance');
            $table->string('spouse_pension_provider', 120)->nullable()->after('spouse_existing_pension_balance');
        });
    }

    public function down(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table) {
            $table->dropColumn(['spouse_isa_provider', 'spouse_pension_provider']);
        });
    }
};
