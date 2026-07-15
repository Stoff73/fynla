<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrialService
{
    /**
     * Expire ended access: cancelled subscriptions and active one-time access.
     */
    public function expireCancelledSubscriptions(): int
    {
        $now = Carbon::now();

        $userIds = Subscription::where(function ($query): void {
            $query->where('status', 'cancelled')
                ->orWhere('status', 'past_due')
                ->orWhere(function ($query): void {
                    $query->where('status', 'active')
                        ->where('auto_renew', false);
                });
        })
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->whereNull('data_retention_starts_at')
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        $expiredCount = 0;

        foreach ($userIds as $userId) {
            $expiredCount += DB::transaction(function () use ($userId, $now): int {
                $user = User::query()->lockForUpdate()->find($userId);
                if ($user === null) {
                    return 0;
                }

                $subscriptions = Subscription::query()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $expired = $subscriptions->filter(fn (Subscription $subscription): bool => $subscription->data_retention_starts_at === null
                    && $subscription->current_period_end !== null
                    && $subscription->current_period_end->lt($now)
                    && ($subscription->status === 'cancelled'
                        || $subscription->status === 'past_due'
                        || ($subscription->status === 'active' && ! $subscription->auto_renew))
                );

                foreach ($expired as $subscription) {
                    $subscription->update([
                        'status' => 'expired',
                        'data_retention_starts_at' => $now,
                    ]);
                }

                $stillEntitled = $subscriptions->contains(
                    fn (Subscription $subscription): bool => $subscription->plan !== 'free'
                        && $subscription->isActive()
                );

                if (! $stillEntitled && $expired->isNotEmpty()) {
                    $user->update(['plan' => 'free', 'tier' => null]);
                }

                return $expired->count();
            });
        }

        return $expiredCount;
    }
}
