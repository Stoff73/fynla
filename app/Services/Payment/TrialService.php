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
        User::whereIn('id', $userIds)->update(['plan' => 'free']);

        return $expired->count();
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
        User::whereIn('id', $userIds)->update(['plan' => 'free']);

        return $expired->count();
    }
}
