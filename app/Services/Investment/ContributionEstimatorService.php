<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use Illuminate\Support\Collection;

/**
 * Resolves the monthly contribution a projection should assume for an account.
 *
 * This is the ONE home for that question, and it answers it from what the user
 * recorded — never from a rule of thumb about what someone like them might save.
 *
 * The rule matters because a projection is presented beside the account's own
 * figures: a card reading "Monthly Contribution —" cannot be reconciled with a
 * projection that quietly assumed the ISA allowance was subscribed in full every
 * year for thirty years. Where nothing is recorded, nothing is contributed, and the
 * projected value is growth on the stated capital at the stated rate — which is what
 * the card says it is.
 */
class ContributionEstimatorService
{
    private const MONTHS_IN_YEAR = 12;

    /**
     * Divisors converting a recorded contribution at its stated frequency to a month.
     */
    private const FREQUENCY_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'annually' => 12,
    ];

    /**
     * Resolve the monthly contribution for an account.
     *
     * Priority: what-if override > recorded regular contribution > contributions
     * already made this tax year, annualised > nothing.
     */
    public function estimateMonthlyContribution(
        InvestmentAccount $account,
        ?float $userOverride = null
    ): float {
        if ($userOverride !== null && $userOverride >= 0) {
            return $userOverride;
        }

        return $this->fromRecordedRegularContribution($account)
            ?? $this->fromContributionsThisTaxYear($account)
            ?? 0.0;
    }

    /**
     * The regular contribution the user entered on the account, at its stated frequency.
     */
    private function fromRecordedRegularContribution(InvestmentAccount $account): ?float
    {
        $amount = (float) ($account->monthly_contribution_amount ?? 0);

        if ($amount <= 0) {
            return null;
        }

        $frequency = $account->contribution_frequency ?? 'monthly';
        $months = self::FREQUENCY_MONTHS[$frequency] ?? 1;

        return $amount / $months;
    }

    /**
     * What has actually gone in this tax year, spread over the months elapsed.
     *
     * `isa_subscription_current_year` is the ISA-specific spelling of the same fact and
     * is read only when the generic column is empty.
     */
    private function fromContributionsThisTaxYear(InvestmentAccount $account): ?float
    {
        $contributed = (float) ($account->contributions_ytd ?? 0);

        if ($contributed <= 0 && $account->account_type === 'isa') {
            $contributed = (float) ($account->isa_subscription_current_year ?? 0);
        }

        if ($contributed <= 0) {
            return null;
        }

        return $contributed / $this->getMonthsElapsedInTaxYear();
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

        return max(1, min(self::MONTHS_IN_YEAR, (int) $taxYearStart->diffInMonths($now) + 1));
    }

    /**
     * Total monthly contribution across a set of accounts.
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
