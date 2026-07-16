<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;

class SubscriptionStatusService
{
    public function __construct(
        private readonly TierResolver $tierResolver,
        private readonly TierConfigurationStore $tierConfigurations,
        private readonly SubscriptionEntitlementService $entitlements,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $subscription = $this->entitlements->activePremiumFor($user)
            ?? $user->subscriptions()->latest('id')->first();
        $tier = $this->tierResolver->resolve($user);
        $tierConfig = $this->tierConfigurations->forTier($tier);
        $paymentEnabled = (bool) config('app.payment_enabled', false);

        return [
            'has_subscription' => $subscription !== null,
            'tier' => $tier,
            'tier_display_name' => $tierConfig->display_name,
            'subscription_status' => $subscription?->status,
            'plan' => $subscription?->plan,
            'billing_cycle' => $subscription?->billing_cycle,
            'amount' => $subscription?->amount,
            'current_period_start' => $subscription?->current_period_start?->toISOString(),
            'current_period_end' => $subscription?->current_period_end?->toISOString(),
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
            'auto_renew' => $subscription?->auto_renew ?? false,
            'next_renewal_date' => $subscription?->status === 'active' && $subscription->auto_renew
                ? $subscription->current_period_end?->toISOString()
                : null,
            'count_caps' => $tierConfig->count_caps ?? [],
            'capability_matrix' => $tierConfig->capability_matrix ?? [],
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
