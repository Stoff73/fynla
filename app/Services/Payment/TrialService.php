<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

class TrialService
{
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

        // Bulk update for performance. Note: bypasses Eloquent observers
        // (Auditable); acceptable because expiry is logged via command output.
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
