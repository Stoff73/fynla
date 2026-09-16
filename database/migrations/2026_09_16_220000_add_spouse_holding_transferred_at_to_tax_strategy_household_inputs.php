<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table): void {
            // Stamped once the spouse facts held here have been copied onto the
            // spouse's own account (CSJ 2026-09-16); the copy never runs twice.
            $table->timestamp('spouse_holding_transferred_at')->nullable()->after('spouse_pension_provider');
        });
    }

    public function down(): void
    {
        Schema::table('tax_strategy_household_inputs', function (Blueprint $table): void {
            $table->dropColumn('spouse_holding_transferred_at');
        });
    }
};
