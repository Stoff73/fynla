<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Investment\InvestmentAccount;
use App\Models\ISAAllowanceTracking;
use App\Models\SavingsAccount;
use App\Services\TaxConfigService;
use Carbon\Carbon;

class ISATracker
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get current UK tax year (April 6 - April 5)
     */
    public function getCurrentTaxYear(): string
    {
        $now = Carbon::now();
        $taxYearStart = Carbon::create($now->year, 4, 6);

        if ($now->lt($taxYearStart)) {
            // Before April 6, still in previous tax year
            $startYear = $now->year - 1;
            $endYear = $now->year;
        } else {
            // After April 6, in current tax year
            $startYear = $now->year;
            $endYear = $now->year + 1;
        }

        return sprintf('%d/%02d', $startYear, $endYear % 100);
    }

    /**
     * Get ISA allowance status for a user and tax year
     *
     * @return array{cash_isa_used: float, stocks_shares_isa_used: float, lisa_used: float, total_used: float, total_allowance: float, remaining: float, percentage_used: float}
     */
    public function getISAAllowanceStatus(int $userId, string $taxYear): array
    {
        // Get or create tracking record
        $tracking = ISAAllowanceTracking::firstOrCreate(
            [
                'user_id' => $userId,
                'tax_year' => $taxYear,
            ],
            [
                'cash_isa_used' => 0.00,
                'stocks_shares_isa_used' => 0.00,
                'lisa_used' => 0.00,
                'total_used' => 0.00,
                'total_allowance' => $this->getTotalAllowance($taxYear),
            ]
        );

        // Calculate ISA usage from savings_accounts for current tax year
        $cashIsaUsed = (float) SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->where('isa_type', 'cash')
            ->sum('isa_subscription_amount');

        $lisaUsed = (float) SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->where('isa_type', 'LISA')
            ->sum('isa_subscription_amount');

        // Calculate stocks & shares ISA usage from investment_accounts (cross-module)
        $stocksSharesIsaUsed = (float) InvestmentAccount::where('user_id', $userId)
            ->where('account_type', 'isa')
            ->where('tax_year', $taxYear)
            ->sum('isa_subscription_current_year');

        $totalUsed = $cashIsaUsed + $stocksSharesIsaUsed + $lisaUsed;
        $totalAllowance = (float) $tracking->total_allowance;
        $remaining = max(0, $totalAllowance - $totalUsed);
        $percentageUsed = $totalAllowance > 0
            ? ($totalUsed / $totalAllowance) * 100
            : 0;

        // Update tracking record
        $tracking->update([
            'cash_isa_used' => $cashIsaUsed,
            'stocks_shares_isa_used' => $stocksSharesIsaUsed,
            'lisa_used' => $lisaUsed,
            'total_used' => $totalUsed,
        ]);

        // Calculate projected ISA usage from regular contributions
        $projectedCashIsa = $this->calculateProjectedSubscriptions($userId, $taxYear, 'cash');
        $projectedTotal = $projectedCashIsa + round($stocksSharesIsaUsed, 2) + round($lisaUsed, 2);

        return [
            'cash_isa_used' => round($cashIsaUsed, 2),
            'stocks_shares_isa_used' => round($stocksSharesIsaUsed, 2),
            'lisa_used' => round($lisaUsed, 2),
            'total_used' => round($totalUsed, 2),
            'total_allowance' => round($totalAllowance, 2),
            'remaining' => round($remaining, 2),
            'percentage_used' => round($percentageUsed, 2),
            'projected_usage' => [
                'cash_isa_projected' => round($projectedCashIsa, 2),
                'total_projected' => round($projectedTotal, 2),
                'projected_remaining' => round(max(0, $totalAllowance - $projectedTotal), 2),
            ],
        ];
    }

    /**
     * Update ISA usage for a specific type
     * Note: For stocks_shares, this now auto-calculates from investment_accounts
     */
    public function updateISAUsage(int $userId, string $isaType, ?float $amount = null, ?string $taxYear = null): void
    {
        $taxYear = $taxYear ?? $this->getCurrentTaxYear();

        $tracking = ISAAllowanceTracking::firstOrCreate(
            [
                'user_id' => $userId,
                'tax_year' => $taxYear,
            ],
            [
                'cash_isa_used' => 0.00,
                'stocks_shares_isa_used' => 0.00,
                'lisa_used' => 0.00,
                'total_used' => 0.00,
                'total_allowance' => $this->getTotalAllowance($taxYear),
            ]
        );

        // Update the specific ISA type
        match ($isaType) {
            'stocks_shares' => $tracking->stocks_shares_isa_used = $amount ?? (float) InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->where('tax_year', $taxYear)
                ->sum('isa_subscription_current_year'),
            'cash' => $tracking->cash_isa_used = $amount ?? (float) SavingsAccount::where('user_id', $userId)
                ->where('is_isa', true)
                ->where('isa_subscription_year', $taxYear)
                ->where('isa_type', 'cash')
                ->sum('isa_subscription_amount'),
            'LISA' => $tracking->lisa_used = $amount ?? (float) SavingsAccount::where('user_id', $userId)
                ->where('is_isa', true)
                ->where('isa_subscription_year', $taxYear)
                ->where('isa_type', 'LISA')
                ->sum('isa_subscription_amount'),
            default => null,
        };

        // Recalculate total
        $tracking->total_used = $tracking->cash_isa_used + $tracking->stocks_shares_isa_used + $tracking->lisa_used;
        $tracking->save();
    }

    /**
     * Get total ISA allowance for a tax year
     */
    public function getTotalAllowance(string $taxYear): float
    {
        $isaConfig = $this->taxConfig->getISAAllowances();

        return (float) $isaConfig['annual_allowance'];
    }

    /**
     * Get LISA specific allowance
     */
    public function getLISAAllowance(): float
    {
        $isaConfig = $this->taxConfig->getISAAllowances();

        return (float) $isaConfig['lifetime_isa']['annual_allowance'];
    }

    /**
     * Calculate projected ISA subscription for a single account
     * based on regular contributions and planned lump sums.
     */
    public function calculateProjectedSubscription(SavingsAccount $account): float
    {
        if (! $account->is_isa || ! $account->regular_contribution_amount) {
            return 0.0;
        }

        $taxYearStart = $this->getTaxYearStartDate();
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();
        $now = Carbon::now();

        $monthsElapsed = (int) $taxYearStart->diffInMonths($now);
        $monthsRemaining = (int) $now->diffInMonths($taxYearEnd);

        $frequencyMultiplier = match ($account->contribution_frequency) {
            'monthly' => 1,
            'quarterly' => 1 / 3,
            'annually' => 1 / 12,
            default => 1,
        };

        $contributionsPerMonth = (float) $account->regular_contribution_amount * $frequencyMultiplier;
        $totalProjected = $contributionsPerMonth * ($monthsElapsed + $monthsRemaining);

        // Add planned lump sum if within tax year
        if ($account->planned_lump_sum_amount
            && $account->planned_lump_sum_date
            && $account->planned_lump_sum_date->between($taxYearStart, $taxYearEnd)
        ) {
            $totalProjected += (float) $account->planned_lump_sum_amount;
        }

        return round($totalProjected, 2);
    }

    /**
     * Calculate total projected ISA subscriptions for a user and ISA type.
     */
    private function calculateProjectedSubscriptions(int $userId, string $taxYear, string $isaType): float
    {
        $accounts = SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where('isa_type', $isaType)
            ->get();

        $total = 0.0;
        foreach ($accounts as $account) {
            $projected = $this->calculateProjectedSubscription($account);
            $total += $projected > 0 ? $projected : (float) ($account->isa_subscription_amount ?? 0);
        }

        return $total;
    }

    /**
     * Get the start date of the current tax year.
     */
    private function getTaxYearStartDate(): Carbon
    {
        $now = Carbon::now();
        $taxYearStart = Carbon::create($now->year, 4, 6);

        if ($now->lt($taxYearStart)) {
            return Carbon::create($now->year - 1, 4, 6);
        }

        return $taxYearStart;
    }
}
