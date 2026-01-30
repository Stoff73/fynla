<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add include_in_retirement flag to investment_accounts table.
     * This controls whether an investment account appears in the
     * Retirement Income Planner. Defaults to false to require
     * explicit opt-in for retirement planning.
     */
    public function up(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->boolean('include_in_retirement')->default(false)->after('rebalance_threshold_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn('include_in_retirement');
        });
    }
};
