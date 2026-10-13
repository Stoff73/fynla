<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T48 (CSJ 2026-10-12): one Fyn limit per tier, per rolling week.
 *
 * "lets replace both, with 750k and 3 million, so we only have one limit",
 * then "yes per week please". The weekly budget (100,000 / 500,000, which
 * only switched to a cheaper model) becomes the one stop at Free 750,000 and
 * Premium 3,000,000; the daily stop (fyn_daily_hard_backstop) goes.
 *
 * TierConfigurationSeeder uses firstOrCreate, so existing rows on csjones and
 * production only change here.
 */
return new class extends Migration
{
    private const WEEKLY_LIMITS = ['free' => 750_000, 'premium' => 3_000_000];

    public function up(): void
    {
        foreach (self::WEEKLY_LIMITS as $tier => $tokens) {
            DB::table('tier_configurations')->where('tier', $tier)->update(['fyn_weekly_token_budget' => $tokens]);
        }

        if (Schema::hasColumn('tier_configurations', 'fyn_daily_hard_backstop')) {
            Schema::table('tier_configurations', function (Blueprint $table) {
                $table->dropColumn('fyn_daily_hard_backstop');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tier_configurations', 'fyn_daily_hard_backstop')) {
            Schema::table('tier_configurations', function (Blueprint $table) {
                $table->unsignedInteger('fyn_daily_hard_backstop')->default(0)->after('fyn_weekly_token_budget');
            });
        }

        foreach (['free' => [100_000, 500_000], 'premium' => [500_000, 2_000_000]] as $tier => [$weekly, $daily]) {
            DB::table('tier_configurations')->where('tier', $tier)
                ->update(['fyn_weekly_token_budget' => $weekly, 'fyn_daily_hard_backstop' => $daily]);
        }
    }
};
