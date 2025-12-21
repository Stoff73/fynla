<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use Illuminate\Support\Collection;

class ContributionEstimatorService
{
    private const ISA_ANNUAL_ALLOWANCE = 20000;
    private const GIA_ANNUAL_PERCENT = 0.05;
    private const MONTHS_IN_YEAR = 12;

    /**
     * Estimate monthly contribution for an account.
     * Priority: user override > ISA subscription > account type defaults
     */
    public function estimateMonthlyContribution(
        InvestmentAccount $account,
        ?float $userOverride = null
    ): float {
        if ($userOverride !== null && $userOverride >= 0) {
            return $userOverride;
        }

        // For ISAs, use isa_subscription_current_year if available
        if ($account->account_type === 'isa') {
            return $this->estimateFromISASubscription($account);
        }

        // For GIA, estimate based on percentage of value
        if ($account->account_type === 'gia') {
            return $this->estimateFromAccountValue($account, self::GIA_ANNUAL_PERCENT);
        }

        // Default: no contribution for other types (SIPP handled via retirement)
        return 0.0;
    }

    private function estimateFromISASubscription(InvestmentAccount $account): float
    {
        $subscription = $account->isa_subscription_current_year ?? 0;

        if ($subscription > 0) {
            $monthsElapsed = $this->getMonthsElapsedInTaxYear();

            return $monthsElapsed > 0 ? $subscription / $monthsElapsed : self::ISA_ANNUAL_ALLOWANCE / self::MONTHS_IN_YEAR;
        }

        // Default to equal monthly contributions to max ISA
        return self::ISA_ANNUAL_ALLOWANCE / self::MONTHS_IN_YEAR;
    }

    private function estimateFromAccountValue(InvestmentAccount $account, float $annualPercent): float
    {
        $value = $account->current_value ?? 0;

        return ($value * $annualPercent) / self::MONTHS_IN_YEAR;
    }

    private function getMonthsElapsedInTaxYear(): int
    {
        $now = now();
        $currentMonth = $now->month;
        $currentDay = $now->day;

        // Tax year starts April 6
        if ($currentMonth > 4 || ($currentMonth === 4 && $currentDay >= 6)) {
            $taxYearStart = $now->copy()->setDate($now->year, 4, 6);
        } else {
            $taxYearStart = $now->copy()->setDate($now->year - 1, 4, 6);
        }

        return max(1, (int) $taxYearStart->diffInMonths($now) + 1);
    }

    /**
     * Estimate total portfolio monthly contribution.
     */
    public function estimatePortfolioContribution(
        Collection $accounts,
        ?array $accountOverrides = null
    ): float {
        $total = 0.0;

        foreach ($accounts as $account) {
            $override = $accountOverrides[$account->id] ?? null;
            $total += $this->estimateMonthlyContribution($account, $override);
        }

        return $total;
    }
}
