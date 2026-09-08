<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F20: the quarterly MoneySavingExpert refresh records which provider set the
 * benchmark and where the row came from, so the admin can tell a scraped rate
 * from a hand-entered one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_market_rates', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('rate');
            $table->string('source', 40)->nullable()->after('effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('savings_market_rates', function (Blueprint $table) {
            $table->dropColumn(['provider', 'source']);
        });
    }
};
