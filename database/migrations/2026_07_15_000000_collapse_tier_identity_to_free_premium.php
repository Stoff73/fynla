<?php

declare(strict_types=1);

use App\Services\Tiers\TierCollapseLock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
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
        app(TierCollapseLock::class)->run(function (): void {
            $this->collapse();
        }, 60);
    }

    private function collapse(): void
    {
        $entitledPaidSubscriptions = DB::table('subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->where('subscriptions.plan', '!=', 'free')
            ->where('users.is_preview_user', false)
            ->where('users.is_lifecycle_test_user', false)
            ->where(function (Builder $query) {
                $query->where('subscriptions.status', 'active')
                    ->orWhere(function (Builder $query) {
                        $query->whereIn('subscriptions.status', ['cancelled', 'past_due'])
                            ->where('subscriptions.current_period_end', '>', now());
                    });
            })
            ->count('subscriptions.id');

        if ($entitledPaidSubscriptions > 0) {
            throw new RuntimeException(
                "Tier collapse blocked: {$entitledPaidSubscriptions} real paid subscription(s) remain entitled."
            );
        }

        $configurationCount = DB::table('tier_configurations')->count();
        $hasFree = DB::table('tier_configurations')->where('tier', 'free')->exists();
        $hasPremium = DB::table('tier_configurations')->where('tier', 'premium')->exists();
        $hasTier2 = DB::table('tier_configurations')->where('tier', 'tier2')->exists();

        if ($configurationCount > 0 && (! $hasFree || (! $hasPremium && ! $hasTier2))) {
            throw new RuntimeException(
                'Tier collapse blocked: a populated configuration table requires Free and either Premium or a Tier 2 source row.'
            );
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3','premium') NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN plan ENUM('free','student','standard','family','pro','tier1','tier2','tier3','premium') NOT NULL DEFAULT 'free'");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student','standard','family','pro','free','tier1','tier2','tier3','premium') NOT NULL");
        DB::statement("ALTER TABLE tier_configurations MODIFY COLUMN tier ENUM('free','tier1','tier2','tier3','premium') NOT NULL");

        if (! DB::table('tier_configurations')->where('tier', 'premium')->exists()) {
            $tier2 = DB::table('tier_configurations')->where('tier', 'tier2')->first();

            if ($tier2 !== null) {
                $premium = (array) $tier2;
                unset($premium['id']);
                $premium['tier'] = 'premium';
                $premium['display_name'] = 'Premium';
                $premium['created_at'] = now();
                $premium['updated_at'] = now();
                DB::table('tier_configurations')->insert($premium);
            }
        }

        if ($configurationCount > 0
            && (! DB::table('tier_configurations')->where('tier', 'free')->exists()
                || ! DB::table('tier_configurations')->where('tier', 'premium')->exists())) {
            throw new RuntimeException('Tier collapse blocked: canonical Free and Premium configuration rows are required.');
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
        app(TierCollapseLock::class)->run(function (): void {
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
