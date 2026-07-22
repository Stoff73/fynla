<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Payment\RevolutSubscriptionService;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Support\Facades\Log;

/**
 * One-way store → Revolut plan-variation sync (SP2 PR5).
 *
 * For every paid tier (price_monthly_pence > 0) this service pushes the
 * current price to Revolut and writes the returned variation id back into
 * TierConfigurationStore so CheckoutPage / createSubscription can reference
 * the correct variation.
 *
 * Price-lock guarantee: the sync only creates NEW Revolut plan variations.
 * It does NOT touch active Subscription rows — each active subscription
 * already holds its billed amount in Subscription.amount, written once at
 * payment confirmation (PaymentController::confirmPayment). Renewals that
 * re-derive from SubscriptionPlan (SubscriptionRenewalService) are the
 * legacy billing path; tier-based subscriptions (Free and Premium)
 * will derive renewal amounts from the Subscription row's locked amount,
 * not from a live tier read — see SubscriptionRenewalService::handleRenewalPayment.
 *
 * Since there are no existing paid subscribers (A9: all legacy cohorts
 * convert to Free), the write-back is safe: no subscriber is mid-cycle on
 * an old variation id.
 */
class RevolutTierVariationSync
{
    public function __construct(
        private readonly RevolutSubscriptionService $revolut,
        private readonly TierConfigurationStore $store,
    ) {}

    /**
     * Push each paid tier's monthly price to Revolut and write back the
     * variation id. Free tier (price_monthly_pence === 0) is skipped —
     * £0 plans need no Revolut record.
     *
     * @param  User|null  $actor  Actor for the audit log. When null (CLI),
     *                            a synthetic system user record is used with
     *                            id=1 (admin seeder always seeds id=1).
     */
    public function run(?User $actor = null): void
    {
        $actor ??= User::find(1) ?? new User(['id' => 1]);

        foreach (TierConfigurationStore::TIERS as $tier) {
            try {
                $config = $this->store->forTier($tier);

                if ($config->price_monthly_pence <= 0) {
                    Log::info('RevolutTierVariationSync: skipping free tier', ['tier' => $tier]);

                    continue;
                }

                $result = $this->revolut->upsertTierPlan(
                    $config->display_name,
                    $config->price_monthly_pence,
                );

                $variationId = $result['id'];

                $this->store->updateTier(
                    $tier,
                    ['revolut_plan_variation_id' => $variationId],
                    $actor,
                    IngestSource::ADMIN,
                );

                Log::info('RevolutTierVariationSync: variation written back', [
                    'tier' => $tier,
                    'variation_id' => $variationId,
                ]);
            } catch (\Throwable $e) {
                Log::error('RevolutTierVariationSync: failed for tier', [
                    'tier' => $tier,
                    'error' => $e->getMessage(),
                ]);
                // Continue with remaining tiers — partial success is acceptable
            }
        }
    }
}
