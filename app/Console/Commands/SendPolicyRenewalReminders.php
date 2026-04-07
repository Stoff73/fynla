<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPolicyRenewalReminders extends Command
{
    protected $signature = 'notifications:policy-renewals';

    protected $description = 'Send push notifications for policies renewing within 30 days';

    public function handle(PushNotificationService $pushService): int
    {
        $thirtyDaysFromNow = now()->addDays(30)->toDateString();
        $today = now()->toDateString();

        $count = 0;

        // Life insurance policies have an explicit policy_end_date
        $lifeRenewals = DB::table('life_insurance_policies')
            ->whereNull('deleted_at')
            ->whereBetween('policy_end_date', [$today, $thirtyDaysFromNow])
            ->select('user_id', DB::raw("COALESCE(provider, 'Life Insurance') as policy_name"), 'policy_end_date as renewal_date')
            ->get();

        $count += $this->sendReminders($lifeRenewals, $pushService);

        // For other policy types, compute end date from policy_start_date + policy_term_years
        $otherPolicyTables = [
            'critical_illness_policies' => 'Critical Illness',
            'disability_policies' => 'Disability',
            'sickness_illness_policies' => 'Sickness & Illness',
            'income_protection_policies' => 'Income Protection',
        ];

        foreach ($otherPolicyTables as $table => $defaultName) {
            $hasTermYears = DB::getSchemaBuilder()->hasColumn($table, 'policy_term_years');

            if (! $hasTermYears) {
                continue;
            }

            $renewals = DB::table($table)
                ->whereNull('deleted_at')
                ->whereNotNull('policy_start_date')
                ->whereNotNull('policy_term_years')
                ->where('policy_term_years', '>', 0)
                ->whereBetween(
                    DB::raw('DATE_ADD(policy_start_date, INTERVAL policy_term_years YEAR)'),
                    [$today, $thirtyDaysFromNow]
                )
                ->select('user_id')
                ->selectRaw('COALESCE(provider, ?) as policy_name', [$defaultName])
                ->selectRaw('DATE_ADD(policy_start_date, INTERVAL policy_term_years YEAR) as renewal_date')
                ->get();

            $count += $this->sendReminders($renewals, $pushService);
        }

        $this->info("Sent {$count} policy renewal reminders.");

        return Command::SUCCESS;
    }

    private function sendReminders(object $policies, PushNotificationService $pushService): int
    {
        $count = 0;

        foreach ($policies as $policy) {
            if (! $pushService->shouldSend($policy->user_id, 'policy_renewals')) {
                continue;
            }

            try {
                $renewalDate = Carbon::parse($policy->renewal_date)->format('j F Y');
                $pushService->sendToUser(
                    $policy->user_id,
                    'Policy Renewal Reminder',
                    "Your {$policy->policy_name} renews on {$renewalDate}. Review your coverage to ensure it still meets your needs.",
                    ['type' => 'policy_renewal', 'route' => '/protection']
                );
                $count++;
            } catch (\Exception $e) {
                Log::warning('Failed to send policy renewal reminder', [
                    'user_id' => $policy->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
