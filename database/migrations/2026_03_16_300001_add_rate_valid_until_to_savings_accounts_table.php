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
     * Adds rate_valid_until column to savings_accounts table.
     * This tracks when a promotional or fixed interest rate expires,
     * enabling rate expiry alerts via the savings:send-alerts command.
     */
    public function up(): void
    {
        if (Schema::hasColumn('savings_accounts', 'rate_valid_until')) {
            return;
        }

        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->date('rate_valid_until')->nullable()->after('interest_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn('rate_valid_until');
        });
    }
};
