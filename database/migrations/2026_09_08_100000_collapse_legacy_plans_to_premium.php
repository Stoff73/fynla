<?php

declare(strict_types=1);

use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierCollapseLock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finish the collapse: the plans sold before the tier scheme (student, standard,
 * family, pro) become Premium in the stored data, not only at runtime.
 *
 * `2026_07_15_000000_collapse_tier_identity_to_free_premium` rewrote the retired
 * tier1/2/3 identities and left these four in place; #771 then honoured them at
 * runtime through `TierConfigurationStore::canonicalPlanForEntitlement()`. CSJ,
 * 2026-09-08: the application has two states, Free and Premium, and the columns
 * have to say so — a stored `pro` a year from now is a trap for whoever reads it.
 *
 * What is rewritten (account state):
 *   - users.plan, subscriptions.plan: legacy plan -> premium (status untouched, so
 *     an expired standard subscription is an expired premium one).
 *   - payments.upgrade_from_plan: legacy plan -> premium.
 *   - discount_codes.applicable_plans: legacy entries -> premium (deduplicated).
 *   - users.deletion_reason: trial_expired -> subscription_cancelled_grace_ended.
 *     There is no trial; those accounts were deleted because a time-limited
 *     subscription ended and its grace period ran out, which is what the
 *     remaining value means. Restorability is identical for both values.
 *   - The users.plan, subscriptions.plan and users.deletion_reason enums are
 *     narrowed so the names cannot come back.
 *
 * Financial history is rewritten too (CSJ, 2026-09-08: "rewrite history too"):
 *   - payments.plan_slug: legacy plans and the retired tiers the July collapse
 *     left there -> premium. Amounts are untouched.
 *   - invoices.plan_name: "Student", "Standard", "Family", "Pro", "Tier 1/2/3"
 *     -> "Premium", the tier's display name. A past invoice now names the
 *     product as it is called today, not as it was sold.
 * Renewals never read the slug for the amount once `SubscriptionPlan::findBySlug()`
 * misses: `SubscriptionRenewalService` falls back to the amount stored on the
 * subscription, so nobody's price changes.
 *
 * The down migration widens the enums back. It cannot reconstruct which legacy
 * plan a row used.
 */
return new class extends Migration
{
    private const NARROW_USERS_PLAN = "ALTER TABLE users MODIFY COLUMN plan ENUM('free','premium') NOT NULL DEFAULT 'free'";

    private const NARROW_SUBSCRIPTIONS_PLAN = "ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('free','premium') NOT NULL";

    private const NARROW_DELETION_REASON = "ALTER TABLE users MODIFY COLUMN deletion_reason ENUM('user_requested','subscription_cancelled_grace_ended','admin_initiated','legacy_purged') NULL";

    private const WIDEN_USERS_PLAN = "ALTER TABLE users MODIFY COLUMN plan ENUM('free','student','standard','family','pro','tier1','tier2','tier3','premium') NOT NULL DEFAULT 'free'";

    private const WIDEN_SUBSCRIPTIONS_PLAN = "ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student','standard','family','pro','free','tier1','tier2','tier3','premium') NOT NULL";

    private const WIDEN_DELETION_REASON = "ALTER TABLE users MODIFY COLUMN deletion_reason ENUM('user_requested','trial_expired','subscription_cancelled_grace_ended','admin_initiated','legacy_purged') NULL";

    public function up(): void
    {
        app(TierCollapseLock::class)->runExclusive(function (): void {
            $this->collapse();
        }, 60);
    }

    private function collapse(): void
    {
        $legacy = TierConfigurationStore::LEGACY_PAID_PLANS;

        $counts = DB::transaction(function () use ($legacy): array {
            $counts = [
                'users_plan' => DB::table('users')->whereIn('plan', $legacy)->update(['plan' => 'premium']),
                'subscriptions_plan' => DB::table('subscriptions')->whereIn('plan', $legacy)->update(['plan' => 'premium']),
                'payments_upgrade_from_plan' => DB::table('payments')->whereIn('upgrade_from_plan', $legacy)->update(['upgrade_from_plan' => 'premium']),
                'users_deletion_reason' => DB::table('users')->where('deletion_reason', 'trial_expired')->update(['deletion_reason' => 'subscription_cancelled_grace_ended']),
                'payments_plan_slug' => DB::table('payments')->whereIn('plan_slug', [...$legacy, ...TierConfigurationStore::RETIRED_TIERS])->update(['plan_slug' => 'premium']),
                'invoices_plan_name' => DB::table('invoices')->whereIn('plan_name', ['Student', 'Standard', 'Family', 'Pro', 'Tier 1', 'Tier 2', 'Tier 3'])->update(['plan_name' => 'Premium']),
                'discount_codes_applicable_plans' => 0,
            ];

            $codes = DB::table('discount_codes')->whereNotNull('applicable_plans')->get(['id', 'applicable_plans']);
            foreach ($codes as $code) {
                $plans = json_decode((string) $code->applicable_plans, true);
                if (! is_array($plans) || array_intersect($plans, $legacy) === []) {
                    continue;
                }
                $rewritten = array_values(array_unique(array_map(
                    fn ($plan) => in_array($plan, $legacy, true) ? 'premium' : $plan,
                    $plans,
                )));
                DB::table('discount_codes')->where('id', $code->id)->update(['applicable_plans' => json_encode($rewritten)]);
                $counts['discount_codes_applicable_plans']++;
            }

            return $counts;
        });

        Log::info('Legacy plans collapsed to premium', $counts);

        DB::statement(self::NARROW_USERS_PLAN);
        DB::statement(self::NARROW_SUBSCRIPTIONS_PLAN);
        DB::statement(self::NARROW_DELETION_REASON);
    }

    public function down(): void
    {
        app(TierCollapseLock::class)->runExclusive(function (): void {
            DB::statement(self::WIDEN_USERS_PLAN);
            DB::statement(self::WIDEN_SUBSCRIPTIONS_PLAN);
            DB::statement(self::WIDEN_DELETION_REASON);
        }, 60);
    }
};
