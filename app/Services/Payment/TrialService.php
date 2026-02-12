<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

class TrialService
{
    private const TRIAL_DAYS = 7;

    private const PLAN_PRICING = [
        'student' => ['monthly' => 399, 'yearly' => 3000],
        'standard' => ['monthly' => 1099, 'yearly' => 10000],
        'pro' => ['monthly' => 1999, 'yearly' => 20000],
    ];

    public function startTrial(User $user, string $plan, string $billingCycle): Subscription
    {
        $now = Carbon::now();
        $amount = self::PLAN_PRICING[$plan][$billingCycle] ?? 0;

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'billing_cycle' => $billingCycle,
            'status' => 'trialing',
            'trial_started_at' => $now,
            'trial_ends_at' => $now->copy()->addDays(self::TRIAL_DAYS),
            'amount' => $amount,
        ]);

        $user->update([
            'plan' => $plan,
            'trial_ends_at' => $subscription->trial_ends_at,
        ]);

        return $subscription;
    }

    public function expireTrials(): int
    {
        $expired = Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '<', Carbon::now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);
            $subscription->user()->update([
                'plan' => 'free',
            ]);
        }

        return $expired->count();
    }
}
