<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;

class TrialService
{
    public function startTrial(User $user, string $plan, string $billingCycle): Subscription
    {
        $now = Carbon::now();
        $subscriptionPlan = SubscriptionPlan::findBySlug($plan);

        if (! $subscriptionPlan) {
            throw new \InvalidArgumentException("Unknown or inactive subscription plan: {$plan}");
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'billing_cycle' => $billingCycle,
            'status' => 'trialing',
            'trial_started_at' => $now,
            'trial_ends_at' => $now->copy()->addDays($subscriptionPlan->trial_days),
            'amount' => $subscriptionPlan->getPriceForCycle($billingCycle),
        ]);

        $user->update([
            'plan' => $plan,
            'trial_ends_at' => $subscription->trial_ends_at,
        ]);

        return $subscription;
    }

    public function expireTrials(): int
    {
        $now = Carbon::now();

        $expired = Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '<', $now)
            ->with('user')
            ->get();

        $expiredIds = $expired->pluck('id');
        $userIds = $expired->pluck('user_id');

        // Bulk update for performance. Note: bypasses Eloquent observers (Auditable).
        // Acceptable because trial expiry is logged via this command's output.
        Subscription::whereIn('id', $expiredIds)->update([
            'status' => 'expired',
            'data_retention_starts_at' => $now,
        ]);
        // Reset the legacy billing-compat column AND the canonical gating
        // column. tier => null means "resolve via TierResolver" → 'free'
        // (§5.2 / nullable-default semantics); a tier subscriber whose
        // trial expired must lose tier access, not keep it forever.
        User::whereIn('id', $userIds)->update(['plan' => 'free', 'tier' => null]);

        return $expired->count();
    }

    /**
     * Restart a previously expired trial for a user (lifecycle Campaign 1).
     *
     * - Updates the user's most recent Subscription record to status='trialing'
     *   with a new trial_started_at/trial_ends_at window
     * - Clears data_retention_starts_at to halt the data purge countdown
     * - Updates users.plan back to 'pro' (trial = pro-level access)
     * - Is idempotent: no-op if the user is already in an active trial
     * - Throws if the user has an active paid subscription
     */
    public function restartTrial(User $user, int $days = 14): void
    {
        $now = Carbon::now();

        // Refuse to overwrite an active paid subscription
        $hasActivePaid = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due'])
            ->exists();

        if ($hasActivePaid) {
            throw new \InvalidArgumentException(
                "Cannot restart trial for user {$user->id}: they have an active paid subscription."
            );
        }

        // Find the most recent subscription
        $latest = Subscription::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        // Idempotency: if already trialing with a future end date, no-op
        if ($latest && $latest->status === 'trialing' && $latest->trial_ends_at && $latest->trial_ends_at->isFuture()) {
            return;
        }

        $newTrialEnd = $now->copy()->addDays($days);

        if ($latest) {
            // Update the existing record (preserves audit history)
            $latest->update([
                'status' => 'trialing',
                'trial_started_at' => $now,
                'trial_ends_at' => $newTrialEnd,
                'data_retention_starts_at' => null,
            ]);
        } else {
            // Edge case: user has no subscription at all — create one
            Subscription::create([
                'user_id' => $user->id,
                'plan' => 'pro',
                'billing_cycle' => 'monthly',
                'status' => 'trialing',
                'trial_started_at' => $now,
                'trial_ends_at' => $newTrialEnd,
                'amount' => 0,
            ]);
        }

        $user->update([
            'plan' => 'pro',
            'trial_ends_at' => $newTrialEnd,
        ]);
    }

    /**
     * Expire cancelled subscriptions whose current_period_end has passed.
     *
     * When a user cancels, they retain access until current_period_end.
     * Once that date passes, transition to 'expired' and start the
     * 30-day data retention grace period.
     */
    public function expireCancelledSubscriptions(): int
    {
        $now = Carbon::now();

        $expired = Subscription::where('status', 'cancelled')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->whereNull('data_retention_starts_at')
            ->with('user')
            ->get();

        if ($expired->isEmpty()) {
            return 0;
        }

        $expiredIds = $expired->pluck('id');
        $userIds = $expired->pluck('user_id');

        // Bulk update for performance (same trade-off as expireTrials)
        Subscription::whereIn('id', $expiredIds)->update([
            'status' => 'expired',
            'data_retention_starts_at' => $now,
        ]);
        // Reset legacy billing-compat column AND canonical gating column.
        // The cancelled subscriber retained access until current_period_end
        // (cancelSubscription deliberately does NOT touch the user); now
        // that the period has passed, tier access is revoked. tier => null
        // → TierResolver resolves to 'free' (§5.2).
        User::whereIn('id', $userIds)->update(['plan' => 'free', 'tier' => null]);

        return $expired->count();
    }
}
