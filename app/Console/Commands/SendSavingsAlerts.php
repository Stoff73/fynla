<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SavingsAccount;
use App\Models\User;
use App\Notifications\EmergencyFundAlertNotification;
use App\Notifications\ISAAllowanceWarningNotification;
use App\Notifications\SavingsMaturityAlertNotification;
use App\Notifications\SavingsRateExpiryNotification;
use App\Services\Savings\EmergencyFundCalculator;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendSavingsAlerts extends Command
{
    protected $signature = 'savings:send-alerts';

    protected $description = 'Send daily savings alerts (maturity, rate expiry, ISA allowance, emergency fund)';

    public function handle(TaxConfigService $taxConfig, EmergencyFundCalculator $emergencyFundCalculator): int
    {
        $today = today();
        $this->info('Running savings alerts for '.$today->toDateString());

        $this->checkMaturityAlerts($today);
        $this->checkRateExpiryAlerts($today);
        $this->checkISAAllowanceAlerts($today, $taxConfig);
        $this->checkEmergencyFundAlerts($emergencyFundCalculator);

        $this->info('Savings alerts complete.');

        return self::SUCCESS;
    }

    private function checkMaturityAlerts(Carbon $today): void
    {
        $alertDays = [90, 30, 7];

        foreach ($alertDays as $days) {
            $targetDate = $today->copy()->addDays($days);

            $accounts = SavingsAccount::whereNotNull('maturity_date')
                ->whereDate('maturity_date', $targetDate)
                ->with('user')
                ->get();

            foreach ($accounts as $account) {
                if ($account->user && ! $account->user->is_preview_user) {
                    $account->user->notify(new SavingsMaturityAlertNotification(
                        $account->account_name ?? 'Fixed-rate account',
                        $days
                    ));
                }
            }

            $this->info("  Maturity {$days}-day alerts: {$accounts->count()} sent");
        }
    }

    private function checkRateExpiryAlerts(Carbon $today): void
    {
        $alertDays = [90, 30, 7];

        foreach ($alertDays as $days) {
            $targetDate = $today->copy()->addDays($days);

            $accounts = SavingsAccount::whereNotNull('rate_valid_until')
                ->whereDate('rate_valid_until', $targetDate)
                ->with('user')
                ->get();

            foreach ($accounts as $account) {
                if ($account->user && ! $account->user->is_preview_user) {
                    $account->user->notify(new SavingsRateExpiryNotification(
                        $account->account_name ?? 'Savings account',
                        $days
                    ));
                }
            }

            $this->info("  Rate expiry {$days}-day alerts: {$accounts->count()} sent");
        }
    }

    private function checkISAAllowanceAlerts(Carbon $today, TaxConfigService $taxConfig): void
    {
        $taxYearEnd = Carbon::parse($taxConfig->getEffectiveTo());
        $daysUntilEnd = (int) $today->diffInDays($taxYearEnd, false);

        if ($daysUntilEnd > 90 || $daysUntilEnd < 0) {
            return;
        }

        $isaAllowance = $taxConfig->getISAAllowances()['annual_allowance'] ?? 20000;

        User::where('is_preview_user', false)
            ->whereNotNull('email_verified_at')
            ->chunk(100, function ($users) use ($isaAllowance, $daysUntilEnd) {
                foreach ($users as $user) {
                    $totalISAContributions = $user->savingsAccounts()
                        ->where('is_isa', true)
                        ->sum('contributions_this_year') ?? 0;

                    $investmentISAContributions = $user->investmentAccounts()
                        ->where('account_type', 'isa')
                        ->sum('contributions_this_year') ?? 0;

                    $totalUsed = $totalISAContributions + $investmentISAContributions;
                    $remaining = max(0, $isaAllowance - $totalUsed);

                    if ($remaining > 1000) {
                        $user->notify(new ISAAllowanceWarningNotification($remaining, $daysUntilEnd));
                    }
                }
            });

        $this->info("  ISA allowance alerts sent ({$daysUntilEnd} days until year end)");
    }

    private function checkEmergencyFundAlerts(EmergencyFundCalculator $emergencyFundCalculator): void
    {
        User::where('is_preview_user', false)
            ->whereNotNull('email_verified_at')
            ->where('monthly_expenditure', '>', 0)
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $totalSavings = $user->savingsAccounts()
                        ->where('access_type', 'easy_access')
                        ->sum('current_balance') ?? 0;

                    $monthlyExpenditure = (float) $user->monthly_expenditure;
                    if ($monthlyExpenditure <= 0) {
                        continue;
                    }

                    $runwayMonths = $totalSavings / $monthlyExpenditure;
                    $targetMonths = 6.0;

                    if ($runwayMonths < 1.0) {
                        $user->notify(new EmergencyFundAlertNotification($runwayMonths, $targetMonths));
                    }
                }
            });

        $this->info('  Emergency fund alerts sent');
    }
}
