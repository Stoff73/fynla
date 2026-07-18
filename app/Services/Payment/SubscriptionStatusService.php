<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\User;
use App\Services\Billing\PremiumEntitlementResolver;
use App\Services\Stores\TierConfigurationStore;

class SubscriptionStatusService
{
    public function __construct(
        private readonly PremiumEntitlementResolver $entitlementResolver,
        private readonly TierConfigurationStore $tierConfigurations,
        private readonly SubscriptionEntitlementService $entitlements,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $resolved = $this->entitlementResolver->resolve($user);
        $subscription = $this->entitlements->activePremiumFor($user);
        if ($subscription === null && $resolved->provider !== 'apple') {
            $subscription = $user->subscriptions()->latest('id')->first();
        }
        $tier = $resolved->tier;
        $tierConfig = $this->tierConfigurations->forTier($tier);
        $paymentEnabled = (bool) config('app.payment_enabled', false);
        $capabilities = $tierConfig->capability_matrix ?? [];
        $limits = $tierConfig->count_caps ?? [];
        $currentPeriodEnd = $subscription?->current_period_end?->toISOString();
        $renews = $subscription?->status === 'active' && ($subscription->auto_renew ?? false);
        $canonicalStatus = $resolved->tier === 'premium'
            ? $resolved->status
            : $subscription?->status;
        $canonicalPeriodEnd = $resolved->tier === 'premium'
            ? $resolved->periodEndsAt?->toISOString()
            : $currentPeriodEnd;
        $canonicalRenews = $resolved->tier === 'premium'
            ? $resolved->renews
            : $renews;

        return [
            'success' => true,
            'data' => [
                'tier' => $tier,
                'provider' => $resolved->provider,
                'status' => $resolved->status,
                'renews' => $resolved->renews,
                'current_period_end' => $resolved->periodEndsAt?->toISOString(),
                'capabilities' => $capabilities,
                'limits' => $limits,
            ],
            'has_subscription' => $resolved->tier === 'premium' || $subscription !== null,
            'tier' => $tier,
            'tier_display_name' => $tierConfig->display_name,
            'subscription_status' => $canonicalStatus,
            'plan' => $subscription?->plan,
            'billing_cycle' => $subscription?->billing_cycle,
            'amount' => $subscription?->amount,
            'current_period_start' => $subscription?->current_period_start?->toISOString(),
            'current_period_end' => $canonicalPeriodEnd,
            'cancelled_at' => $subscription?->cancelled_at?->toISOString(),
            'data_retention_starts_at' => $paymentEnabled
                ? $subscription?->data_retention_starts_at?->toISOString()
                : null,
            'grace_period_ends_at' => $paymentEnabled
                ? $subscription?->gracePeriodEndsAt()?->toISOString()
                : null,
            'is_in_grace_period' => $paymentEnabled && ($subscription?->isInGracePeriod() ?? false),
            'is_terminal_paid' => $subscription !== null
                && ! $subscription->isActive()
                && $subscription->status !== 'pending',
            'auto_renew' => $canonicalRenews,
            'next_renewal_date' => $canonicalRenews ? $canonicalPeriodEnd : null,
            'count_caps' => $limits,
            'capability_matrix' => $capabilities,
            'document_upload_allowance' => $tierConfig->document_upload_allowance,
            'document_storage_gb' => $tierConfig->document_storage_gb,
            'fyn_weekly_token_budget' => $tierConfig->fyn_weekly_token_budget,
            'fyn_daily_hard_backstop' => $tierConfig->fyn_daily_hard_backstop,
            'currency_display_mode' => $tierConfig->currency_display_mode,
            'snapshot_surfacing_window_days' => $tierConfig->snapshot_surfacing_window_days,
            'open_api_affordance' => $tierConfig->open_api_affordance,
            'payment_enabled' => $paymentEnabled && ! $user->is_preview_user,
        ];
    }
}
