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
     * Get the tax year the app is configured to treat as active.
     * This reads from the TaxConfiguration DB record marked is_active
     * and may differ from the calendar tax year if an admin has switched
     * the year via the admin panel.
     */
    public function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
    }

    /**
     * Get the tax year based on today's calendar date (April 6 - April 5).
     * Used to decide whether "ongoing contribution" estimates apply to the
     * requested tax year, or whether they'd be misattributed across years.
     */
    public function getCalendarTaxYear(): string
    {
        $start = $this->getTaxYearStartDate();
        $startYear = $start->year;

        return $startYear.'/'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Get ISA allowance status for a user and tax year
     *
     * @return array{cash_isa_used: float, stocks_shares_isa_used: float, lisa_used: float, total_used: float, total_allowance: float, remaining: float, percentage_used: float}
     */
    public function getISAAllowanceStatus(int $userId, string $taxYear): array
    {
        // Estimates based on ongoing monthly contributions only make sense for
        // the tax year we're physically living through (the calendar year).
        // If a user switches to a past or future year via admin, we must not
        // attribute this year's monthly contributions to that other year.
        $isCalendarYear = $taxYear === $this->getCalendarTaxYear();

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
        // Match on isa_type='cash' OR account_type='cash_isa' (some accounts lack isa_type)
        $cashIsaUsed = (float) SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->where(function ($q) {
                $q->where('isa_type', 'cash')
                    ->orWhere('account_type', 'cash_isa');
            })
            ->whereNotNull('isa_subscription_amount')
            ->sum('isa_subscription_amount');

        // If no explicit subscription tracked AND we're showing the live year,
        // estimate from regular monthly contributions. Skip for past/future years.
        if ($cashIsaUsed <= 0 && $isCalendarYear) {
            $cashIsaAccounts = SavingsAccount::where('user_id', $userId)
                ->where('is_isa', true)
                ->where(function ($q) {
                    $q->where('isa_type', 'cash')
                        ->orWhere('account_type', 'cash_isa');
                })
                ->get();

            foreach ($cashIsaAccounts as $account) {
                $projected = $this->calculateProjectedSubscription($account);
                if ($projected > 0) {
                    $cashIsaUsed += $projected;
                }
            }
        }

        $lisaUsed = (float) SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where(function ($q) use ($taxYear) {
                $q->where('isa_subscription_year', $taxYear)
                    ->orWhere('account_type', 'lisa');
            })
            ->where(function ($q) {
                $q->where('isa_type', 'LISA')
                    ->orWhere('isa_type', 'lisa')
                    ->orWhere('account_type', 'lisa');
            })
            ->sum('isa_subscription_amount');

        // Calculate stocks & shares ISA usage from investment_accounts (cross-module)
        // First try with explicit tax year match — always respects the requested year
        $stocksSharesIsaUsed = (float) InvestmentAccount::where('user_id', $userId)
            ->where('account_type', 'isa')
            ->where('tax_year', $taxYear)
            ->sum('isa_subscription_current_year');

        // Fallbacks below rely on "current year" fields that do not carry a tax year.
        // They must only apply when we're showing the live calendar year —
        // otherwise switching to a past/future year would show live contributions
        // attributed to the wrong year.
        if ($stocksSharesIsaUsed <= 0 && $isCalendarYear) {
            $stocksSharesIsaUsed = (float) InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->whereNotNull('isa_subscription_current_year')
                ->where('isa_subscription_current_year', '>', 0)
                ->sum('isa_subscription_current_year');
        }

        if ($stocksSharesIsaUsed <= 0 && $isCalendarYear) {
            $stocksSharesIsaUsed = (float) InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->sum('contributions_ytd');
        }

        // When no explicit subscription tracked, estimate from monthly contributions
        if ($stocksSharesIsaUsed <= 0 && $isCalendarYear) {
            $stocksSharesIsaUsed = $this->estimateStocksSharesIsaUsage($userId);
        }

        $totalUsed = $cashIsaUsed + $stocksSharesIsaUsed + $lisaUsed;
        $totalAllowance = (float) $tracking->total_allowance;
        $remaining = max(0, $totalAllowance - $totalUsed);
        $percentageUsed = $totalAllowance > 0
            ? ($totalUsed / $totalAllowance) * 100
            : 0;

        // Update tracking record only if values changed
        $tracking->fill([
            'cash_isa_used' => $cashIsaUsed,
            'stocks_shares_isa_used' => $stocksSharesIsaUsed,
            'lisa_used' => $lisaUsed,
            'total_used' => $totalUsed,
        ]);

        if ($tracking->isDirty()) {
            $tracking->save();
        }

        // Calculate projected ISA usage from regular contributions.
        // Projections forecast a full-year subscription based on current monthly
        // contributions — only meaningful for the live calendar year.
        if ($isCalendarYear) {
            $projectedCashIsa = $this->calculateProjectedSubscriptions($userId, $taxYear, 'cash');
            $projectedStocksSharesIsa = $this->estimateStocksSharesIsaUsage($userId, true);
        } else {
            $projectedCashIsa = 0.0;
            $projectedStocksSharesIsa = 0.0;
        }
        $projectedTotal = $projectedCashIsa + round(max($stocksSharesIsaUsed, $projectedStocksSharesIsa), 2) + round($lisaUsed, 2);

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
            ->where(function ($q) use ($isaType) {
                $q->where('isa_type', $isaType);
                if ($isaType === 'cash') {
                    $q->orWhere('account_type', 'cash_isa');
                } elseif ($isaType === 'LISA' || $isaType === 'lisa') {
                    $q->orWhere('account_type', 'lisa');
                }
            })
            ->get();

        $total = 0.0;
        foreach ($accounts as $account) {
            $projected = $this->calculateProjectedSubscription($account);
            $total += $projected > 0 ? $projected : (float) ($account->isa_subscription_amount ?? 0);
        }

        return $total;
    }

    /**
     * Estimate S&S ISA usage from monthly contributions on investment accounts.
     */
    private function estimateStocksSharesIsaUsage(int $userId, bool $fullYear = false): float
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->where('account_type', 'isa')
            ->where('monthly_contribution_amount', '>', 0)
            ->get();

        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $taxYearStart = $this->getTaxYearStartDate();
        $now = Carbon::now();
        $monthsElapsed = max(1, (int) $taxYearStart->diffInMonths($now));

        $total = 0.0;
        foreach ($accounts as $account) {
            $monthly = (float) $account->monthly_contribution_amount;
            if ($fullYear) {
                $total += $monthly * 12;
            } else {
                $total += $monthly * $monthsElapsed;
            }
        }

        return round($total, 2);
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
