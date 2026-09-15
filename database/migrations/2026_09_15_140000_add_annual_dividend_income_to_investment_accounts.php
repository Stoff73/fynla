<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dividend income a user offers for an investment account lives on that
 * account (CSJ 2026-09-15); until now only the user-level annual total was
 * kept. Nullable: "never told us" is not "£0".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table): void {
            $table->decimal('annual_dividend_income', 14, 2)->nullable()->after('current_value');
        });
    }

    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table): void {
            $table->dropColumn('annual_dividend_income');
        });
    }
};
