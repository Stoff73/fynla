<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserMetricsService
{
    /**
     * Module tables used for engagement tracking.
     * Each entry maps to a table with a user_id column.
     */
    private const MODULE_TABLES = [
        'life_insurance_policies',
        'savings_accounts',
        'investment_accounts',
        'dc_pensions',
        'properties',
        'goals',
    ];

    /**
     * High-level user/subscription snapshot.
     *
     * @return array{total_registered: int, active_subscribers: int, on_trial: int, never_paid: int}
     */
    public function getSnapshot(): array
    {
        $totalRegistered = User::where('is_preview_user', false)->count();

        $activeSubscribers = Subscription::where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->count();

        $onTrial = Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '>', now())
            ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->count();

        $usersWithActiveOrTrial = Subscription::whereIn('status', ['active', 'trialing'])
            ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->distinct('user_id')
            ->count('user_id');

        $neverPaid = $totalRegistered - $usersWithActiveOrTrial;

        return [
            'total_registered' => $totalRegistered,
            'active_subscribers' => $activeSubscribers,
            'on_trial' => $onTrial,
            'never_paid' => max(0, $neverPaid),
        ];
    }

    /**
     * Break down trialing subscriptions by days remaining.
     *
     * @return array{four_plus_days: int, three_days: int, two_days: int, one_day: int, expiring_today: int, expired: int}
     */
    public function getTrialBreakdown(): array
    {
        $now = Carbon::now();

        $baseQuery = fn () => Subscription::where('status', 'trialing')
            ->whereHas('user', fn ($q) => $q->where('is_preview_user', false));

        $fourPlusDays = $baseQuery()
            ->where('trial_ends_at', '>', $now->copy()->addDays(3))
            ->count();

        $threeDays = $baseQuery()
            ->where('trial_ends_at', '>', $now->copy()->addDays(2))
            ->where('trial_ends_at', '<=', $now->copy()->addDays(3))
            ->count();

        $twoDays = $baseQuery()
            ->where('trial_ends_at', '>', $now->copy()->addDays(1))
            ->where('trial_ends_at', '<=', $now->copy()->addDays(2))
            ->count();

        $oneDay = $baseQuery()
            ->where('trial_ends_at', '>', $now->copy())
            ->where('trial_ends_at', '<=', $now->copy()->addDays(1))
            ->count();

        $expiringToday = $baseQuery()
            ->whereDate('trial_ends_at', $now->toDateString())
            ->count();

        // Expired: status = expired, OR trialing with trial_ends_at < now, and user has no active subscription
        $expired = Subscription::where(function ($q) use ($now) {
            $q->where('status', 'expired')
                ->orWhere(function ($q2) use ($now) {
                    $q2->where('status', 'trialing')
                        ->where('trial_ends_at', '<', $now);
                });
        })
            ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->whereDoesntHave('user', function ($q) {
                $q->whereHas('subscription', fn ($sq) => $sq->where('status', 'active'));
            })
            ->count();

        return [
            'four_plus_days' => $fourPlusDays,
            'three_days' => $threeDays,
            'two_days' => $twoDays,
            'one_day' => $oneDay,
            'expiring_today' => $expiringToday,
            'expired' => $expired,
        ];
    }

    /**
     * Active subscriptions grouped by plan with revenue breakdown.
     *
     * @return array<int, array{plan: string, total: int, monthly: int, yearly: int, monthly_revenue: int, yearly_revenue: int}>
     */
    public function getPlanBreakdown(): array
    {
        $plans = ['student', 'standard', 'family', 'pro'];
        $result = [];

        foreach ($plans as $plan) {
            $baseQuery = fn () => Subscription::where('status', 'active')
                ->where('plan', $plan)
                ->whereHas('user', fn ($q) => $q->where('is_preview_user', false));

            $monthly = $baseQuery()->where('billing_cycle', 'monthly')->count();
            $yearly = $baseQuery()->where('billing_cycle', 'yearly')->count();

            $monthlyRevenue = (int) $baseQuery()->where('billing_cycle', 'monthly')->sum('amount');
            $yearlyRevenue = (int) $baseQuery()->where('billing_cycle', 'yearly')->sum('amount');

            $result[] = [
                'plan' => $plan,
                'total' => $monthly + $yearly,
                'monthly' => $monthly,
                'yearly' => $yearly,
                'monthly_revenue' => $monthlyRevenue,
                'yearly_revenue' => $yearlyRevenue,
            ];
        }

        return $result;
    }

    /**
     * Time-bucketed activity data for charts.
     *
     * @param  string  $period  One of: day, week, month, quarter, year
     * @param  int  $range  Number of period buckets to return
     * @return array<int, array{period: string, registrations: int, conversions: int, cancellations: int, trial_expired: int, revenue: int}>
     */
    public function getActivity(string $period, int $range): array
    {
        $now = Carbon::now();

        // Build boundaries by stepping backwards from now
        // We create $range start-of-period markers, then cap with now() so the
        // final bucket covers up to the current moment.
        $boundaries = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $boundaries[] = match ($period) {
                'day' => $now->copy()->subDays($i)->startOfDay(),
                'week' => $now->copy()->subWeeks($i)->startOfWeek(),
                'month' => $now->copy()->subMonths($i)->startOfMonth(),
                'quarter' => $now->copy()->subMonths($i * 3)->startOfMonth(),
                'year' => $now->copy()->subYears($i)->startOfYear(),
                default => $now->copy()->subMonths($i)->startOfMonth(),
            };
        }
        // Final boundary is now() so the last bucket includes everything up to this moment
        $boundaries[] = $now->copy()->addSecond();

        $buckets = [];

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $bucketStart = $boundaries[$i];
            $bucketEnd = $boundaries[$i + 1];

            $label = $this->formatPeriodLabel($bucketStart, $period);

            $registrations = User::where('is_preview_user', false)
                ->where('created_at', '>=', $bucketStart)
                ->where('created_at', '<', $bucketEnd)
                ->count();

            $conversions = Subscription::where('status', 'active')
                ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('current_period_start', '>=', $bucketStart)
                ->where('current_period_start', '<', $bucketEnd)
                ->count();

            $cancellations = Subscription::whereNotNull('cancelled_at')
                ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('cancelled_at', '>=', $bucketStart)
                ->where('cancelled_at', '<', $bucketEnd)
                ->count();

            $trialExpired = Subscription::where(function ($q) {
                $q->where('status', 'expired')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'trialing');
                    });
            })
                ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('trial_ends_at', '>=', $bucketStart)
                ->where('trial_ends_at', '<', $bucketEnd)
                ->where('trial_ends_at', '<', now())
                ->count();

            $revenue = (int) Subscription::where('status', 'active')
                ->whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('current_period_start', '>=', $bucketStart)
                ->where('current_period_start', '<', $bucketEnd)
                ->sum('amount');

            $buckets[] = [
                'period' => $label,
                'registrations' => $registrations,
                'conversions' => $conversions,
                'cancellations' => $cancellations,
                'trial_expired' => $trialExpired,
                'revenue' => $revenue,
            ];
        }

        return $buckets;
    }

    /**
     * Engagement stats for non-converting users (no active subscription).
     *
     * @return array{total: int, onboarding_completed_pct: float, used_one_plus_modules_pct: float, used_three_plus_modules_pct: float}
     */
    public function getEngagementStats(): array
    {
        // Users with no active subscription (non-converters)
        $nonConverterIds = User::where('is_preview_user', false)
            ->whereDoesntHave('subscription', fn ($q) => $q->where('status', 'active'))
            ->pluck('id');

        $total = $nonConverterIds->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'onboarding_completed_pct' => 0.0,
                'used_one_plus_modules_pct' => 0.0,
                'used_three_plus_modules_pct' => 0.0,
            ];
        }

        $onboardingCompleted = User::whereIn('id', $nonConverterIds)
            ->where('onboarding_completed', true)
            ->count();

        // Count modules used per user
        $moduleCountsPerUser = [];

        foreach ($nonConverterIds as $userId) {
            $moduleCount = 0;

            foreach (self::MODULE_TABLES as $table) {
                $exists = DB::table($table)
                    ->where('user_id', $userId)
                    ->exists();

                if ($exists) {
                    $moduleCount++;
                }
            }

            $moduleCountsPerUser[$userId] = $moduleCount;
        }

        $usedOnePlus = collect($moduleCountsPerUser)->filter(fn ($c) => $c >= 1)->count();
        $usedThreePlus = collect($moduleCountsPerUser)->filter(fn ($c) => $c >= 3)->count();

        return [
            'total' => $total,
            'onboarding_completed_pct' => round(($onboardingCompleted / $total) * 100, 1),
            'used_one_plus_modules_pct' => round(($usedOnePlus / $total) * 100, 1),
            'used_three_plus_modules_pct' => round(($usedThreePlus / $total) * 100, 1),
        ];
    }

    /**
     * Format a Carbon date into a human-readable period label.
     */
    private function formatPeriodLabel(Carbon $date, string $period): string
    {
        return match ($period) {
            'day' => $date->format('j M'),
            'week' => 'w/c '.$date->startOfWeek()->format('j M'),
            'month' => $date->format('M Y'),
            'quarter' => 'Q'.ceil($date->month / 3).' '.$date->format('Y'),
            'year' => $date->format('Y'),
            default => $date->format('j M Y'),
        };
    }
}
