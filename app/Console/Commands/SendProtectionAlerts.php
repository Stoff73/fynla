<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ProtectionAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendProtectionAlerts extends Command
{
    protected $signature = 'protection:send-alerts';

    protected $description = 'Send protection alerts for expired policies, approaching renewals (24/12/3 months), and annual review prompts';

    public function handle(): int
    {
        $count = 0;

        $count += $this->alertExpiredPolicies();
        $count += $this->alertApproachingRenewals();
        $count += $this->alertAnnualReview();

        $this->info("Sent {$count} protection alert(s).");

        return Command::SUCCESS;
    }

    /**
     * Alert users about policies that have expired (end date in the past).
     */
    private function alertExpiredPolicies(): int
    {
        $count = 0;
        $today = now()->toDateString();

        // Life insurance policies with explicit end dates
        $expiredLife = DB::table('life_insurance_policies')
            ->whereNull('deleted_at')
            ->where('policy_end_date', '<', $today)
            ->where('policy_end_date', '>=', now()->subDays(30)->toDateString())
            ->select('user_id', DB::raw("COALESCE(provider, 'Life Insurance') as policy_name"), 'policy_end_date')
            ->get();

        foreach ($expiredLife as $policy) {
            $count += $this->sendAlert(
                (int) $policy->user_id,
                'policy_expired',
                'Protection Policy Expired',
                "Your {$policy->policy_name} policy expired on "
                    .Carbon::parse($policy->policy_end_date)->format('j F Y')
                    .'. If you still have protection needs, arrange replacement cover.',
                [
                    'policy_name' => $policy->policy_name,
                    'expiry_date' => $policy->policy_end_date,
                    'route' => '/protection',
                ]
            );
        }

        // Other policy types with computed end dates
        $otherPolicyTables = [
            'critical_illness_policies' => 'Critical Illness',
            'income_protection_policies' => 'Income Protection',
        ];

        $thirtyDaysAgo = now()->subDays(30);

        foreach ($otherPolicyTables as $table => $defaultName) {
            if (! DB::getSchemaBuilder()->hasColumn($table, 'policy_term_years')) {
                continue;
            }

            $policies = DB::table($table)
                ->whereNull('deleted_at')
                ->whereNotNull('policy_start_date')
                ->whereNotNull('policy_term_years')
                ->where('policy_term_years', '>', 0)
                ->select('user_id', 'policy_start_date', 'policy_term_years')
                ->selectRaw('COALESCE(provider, ?) as policy_name', [$defaultName])
                ->get()
                ->filter(function ($policy) use ($today, $thirtyDaysAgo) {
                    $endDate = Carbon::parse($policy->policy_start_date)->addYears((int) $policy->policy_term_years);

                    return $endDate->lt(Carbon::parse($today)) && $endDate->gte($thirtyDaysAgo);
                });

            foreach ($policies as $policy) {
                $endDate = Carbon::parse($policy->policy_start_date)->addYears((int) $policy->policy_term_years)->toDateString();
                $count += $this->sendAlert(
                    (int) $policy->user_id,
                    'policy_expired',
                    'Protection Policy Expired',
                    "Your {$policy->policy_name} policy expired on "
                        .Carbon::parse($endDate)->format('j F Y')
                        .'. If you still have protection needs, arrange replacement cover.',
                    [
                        'policy_name' => $policy->policy_name,
                        'expiry_date' => $endDate,
                        'route' => '/protection',
                    ]
                );
            }
        }

        return $count;
    }

    /**
     * Alert users about policies approaching renewal at 24, 12, and 3 month intervals.
     */
    private function alertApproachingRenewals(): int
    {
        $count = 0;

        $intervals = [
            ['months' => 24, 'label' => 'twenty-four months'],
            ['months' => 12, 'label' => 'twelve months'],
            ['months' => 3, 'label' => 'three months'],
        ];

        foreach ($intervals as $interval) {
            $targetDate = now()->addMonths($interval['months']);
            // Window: target date +/- 1 day to catch the specific day
            $windowStart = $targetDate->copy()->subDay()->toDateString();
            $windowEnd = $targetDate->copy()->addDay()->toDateString();

            // Life insurance policies
            $lifeRenewals = DB::table('life_insurance_policies')
                ->whereNull('deleted_at')
                ->whereBetween('policy_end_date', [$windowStart, $windowEnd])
                ->select('user_id', DB::raw("COALESCE(provider, 'Life Insurance') as policy_name"), 'policy_end_date')
                ->get();

            foreach ($lifeRenewals as $policy) {
                $count += $this->sendAlert(
                    (int) $policy->user_id,
                    'policy_approaching_renewal',
                    'Policy Renewal Approaching',
                    "Your {$policy->policy_name} policy ends in approximately {$interval['label']}. "
                        .'Review your coverage to ensure it still meets your needs and consider obtaining replacement quotes.',
                    [
                        'policy_name' => $policy->policy_name,
                        'end_date' => $policy->policy_end_date,
                        'months_remaining' => $interval['months'],
                        'route' => '/protection',
                    ]
                );
            }

            // Other policy types
            $otherPolicyTables = [
                'critical_illness_policies' => 'Critical Illness',
                'income_protection_policies' => 'Income Protection',
            ];

            foreach ($otherPolicyTables as $table => $defaultName) {
                if (! DB::getSchemaBuilder()->hasColumn($table, 'policy_term_years')) {
                    continue;
                }

                $renewals = DB::table($table)
                    ->whereNull('deleted_at')
                    ->whereNotNull('policy_start_date')
                    ->whereNotNull('policy_term_years')
                    ->where('policy_term_years', '>', 0)
                    ->select('user_id', 'policy_start_date', 'policy_term_years')
                    ->selectRaw('COALESCE(provider, ?) as policy_name', [$defaultName])
                    ->get()
                    ->filter(function ($policy) use ($windowStart, $windowEnd) {
                        $endDate = Carbon::parse($policy->policy_start_date)->addYears((int) $policy->policy_term_years);

                        return $endDate->gte(Carbon::parse($windowStart)) && $endDate->lte(Carbon::parse($windowEnd));
                    });

                foreach ($renewals as $policy) {
                    $endDate = Carbon::parse($policy->policy_start_date)->addYears((int) $policy->policy_term_years)->toDateString();
                    $count += $this->sendAlert(
                        (int) $policy->user_id,
                        'policy_approaching_renewal',
                        'Policy Renewal Approaching',
                        "Your {$policy->policy_name} policy ends in approximately {$interval['label']}. "
                            .'Review your coverage to ensure it still meets your needs and consider obtaining replacement quotes.',
                        [
                            'policy_name' => $policy->policy_name,
                            'end_date' => $endDate,
                            'months_remaining' => $interval['months'],
                            'route' => '/protection',
                        ]
                    );
                }
            }
        }

        return $count;
    }

    /**
     * Send annual protection review prompt.
     * Triggers once per year for users who have a protection profile.
     */
    private function alertAnnualReview(): int
    {
        $count = 0;

        // Find users with protection profiles who haven't had a review notification in the past 11 months
        $usersWithProfiles = DB::table('protection_profiles')
            ->join('users', 'protection_profiles.user_id', '=', 'users.id')
            ->where('users.is_preview_user', false)
            ->whereNull('users.deleted_at')
            ->select('protection_profiles.user_id')
            ->get();

        foreach ($usersWithProfiles as $row) {
            $userId = (int) $row->user_id;

            // Check if an annual review notification was already sent in the last 11 months
            $recentReview = DB::table('notifications')
                ->where('notifiable_id', $userId)
                ->where('notifiable_type', 'App\\Models\\User')
                ->where('data->type', 'annual_protection_review')
                ->where('created_at', '>=', now()->subMonths(11))
                ->exists();

            if ($recentReview) {
                continue;
            }

            $count += $this->sendAlert(
                $userId,
                'annual_protection_review',
                'Annual Protection Review',
                'It has been over a year since your last protection review. '
                    .'Life changes such as a new mortgage, a salary increase, or a new dependant can affect your protection needs. '
                    .'Review your coverage to ensure it remains adequate.',
                [
                    'route' => '/protection',
                ]
            );
        }

        return $count;
    }

    /**
     * Send a protection alert notification to a user.
     */
    private function sendAlert(int $userId, string $alertType, string $title, string $body, array $data = []): int
    {
        $user = User::find($userId);

        if (! $user || $user->is_preview_user) {
            return 0;
        }

        try {
            $user->notify(new ProtectionAlertNotification($alertType, $title, $body, $data));

            return 1;
        } catch (\Exception $e) {
            Log::warning('Failed to send protection alert', [
                'user_id' => $userId,
                'alert_type' => $alertType,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
