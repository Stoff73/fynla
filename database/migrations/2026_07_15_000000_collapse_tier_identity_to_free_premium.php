<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use App\Services\Tiers\TierCollapseLock;
use App\Services\Tiers\TierCollapsePreflight;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse the unused Tier 1, Tier 2 and Tier 3 identities into Premium.
 *
 * The down migration maps Premium to Tier 2. It cannot reconstruct which
 * former paid tier a row used before this collapse.
 */
return new class extends Migration
{
    private const RETIRED_TIERS = ['tier1', 'tier2', 'tier3'];

    public function up(): void
    {
        app(TierCollapseLock::class)->runExclusive(function (): void {
            $this->collapse();
        }, 60);
    }

    private function collapse(): void
    {
        app(TierCollapsePreflight::class)->assertSafe();

        DB::statement("ALTER TABLE users MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3','premium') NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN plan ENUM('free','student','standard','family','pro','tier1','tier2','tier3','premium') NOT NULL DEFAULT 'free'");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student','standard','family','pro','free','tier1','tier2','tier3','premium') NOT NULL");
        DB::statement("ALTER TABLE tier_configurations MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3','premium') NOT NULL");

        foreach (TierConfigurationSeeder::rows() as $row) {
            if ($row['tier'] === 'premium') {
                $row['document_upload_allowance'] = 0;
                $row['snapshot_surfacing_window_days'] = 90;
            }

            TierConfiguration::updateOrCreate(['tier' => $row['tier']], $row);
        }

        DB::transaction(function () {
            DB::table('users')->whereIn('tier', self::RETIRED_TIERS)->update(['tier' => 'premium']);
            DB::table('users')->whereIn('plan', self::RETIRED_TIERS)->update(['plan' => 'premium']);
            DB::table('subscriptions')->whereIn('plan', self::RETIRED_TIERS)->update(['plan' => 'premium']);
            DB::table('tier_configurations')->whereIn('tier', self::RETIRED_TIERS)->delete();
        });

        DB::statement("ALTER TABLE tier_configurations MODIFY COLUMN tier ENUM('free','premium') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN tier ENUM('free','premium') NULL");
    }

    public function down(): void
    {
        app(TierCollapseLock::class)->runExclusive(function (): void {
            $this->expand();
        }, 60);
    }

    private function expand(): void
    {
        DB::statement("ALTER TABLE tier_configurations MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3','premium') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3','premium') NULL");

        DB::transaction(function () {
            DB::table('users')->where('tier', 'premium')->update(['tier' => 'tier2']);
            DB::table('users')->where('plan', 'premium')->update(['plan' => 'tier2']);
            DB::table('subscriptions')->where('plan', 'premium')->update(['plan' => 'tier2']);

            if (DB::table('tier_configurations')->where('tier', 'premium')->exists()) {
                if (DB::table('tier_configurations')->where('tier', 'tier2')->exists()) {
                    DB::table('tier_configurations')->where('tier', 'premium')->delete();
                } else {
                    DB::table('tier_configurations')->where('tier', 'premium')->update([
                        'tier' => 'tier2',
                        'display_name' => 'Tier 2',
                    ]);
                }
            }
        });

        DB::statement("ALTER TABLE tier_configurations MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3') NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN plan ENUM('free','student','standard','family','pro','tier1','tier2','tier3') NOT NULL DEFAULT 'free'");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student','standard','family','pro','free','tier1','tier2','tier3') NOT NULL");
    }
};
