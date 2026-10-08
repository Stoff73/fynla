<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\Tax\PensionAffordability;
use App\Services\TaxConfigService;

/**
 * How far the user's projected retirement income falls short of their target,
 * and the real numbers that close it (CSJ 2026-10-01, D2: one card for the
 * shortfall; memory "compute the real split, never either/or"). Spec:
 * docs/superpowers/specs/2026-10-01-retirement-cards-review-design.md, 3.3.
 *
 * - The projection is the planning contract (RetirementProjectionContractService,
 *   plan 2026-08-10): the server-owned primary projection /m and iOS show,
 *   on the user's own assumptions, each pension from its own start age, a
 *   defined contribution pot turned into income at the sustainable withdrawal
 *   rate. The shortfall is the target less its income at the target age.
 * - The contribution that closes it inverts the same contract
 *   (calculatePlanningValue at the same net return and compounding).
 * - What can be paid is capped by the one affordability rule
 *   (PensionAffordability, "affordability check always", CSJ 2026-09-30) and
 *   by the relief limit: the remaining Annual Allowance plus carry forward,
 *   and no more than relevant UK earnings less what the member already pays
 *   this year (Finance Act 2004 s190 caps the year's total). The basic amount
 *   lifts that cap only on a relief-at-source route (a personal pension, or
 *   opening one: s190(2), s191(7)). The Money Purchase Annual Allowance applies
 *   once a pension has been flexibly accessed (FA 2004 s227ZA, s227G).
 * - Net money buys gross at the basic rate on relief at source (s192), the
 *   route the how-to gives a figure for.
 * - When that falls short, the retirement age at which the contract with the
 *   affordable payment reaches the target, up to the last age relief is
 *   given (FA 2004 s188(3)(a), `pension.relief_max_age`).
 */
class RetirementIncomePosition
{
    public function __construct(
        private readonly RetirementProjectionContractService $contract,
        private readonly PensionAffordability $affordability,
        private readonly TaxConfigService $taxConfig,
        private readonly AnnualAllowanceChecker $allowanceChecker,
    ) {}

    /**
     * @param  array<string, mixed>  $analysisData  RetirementAgent::analyze() data
     * @return array<string, mixed>|null null when the projection meets the target
     */
    public function for(User $user, array $analysisData): ?array
    {
        $summary = (array) ($analysisData['summary'] ?? []);
        $target = (float) ($summary['target_retirement_income'] ?? 0);
        if ($target <= 0) {
            return null;
        }
        $plan = $this->contract->build($user, withUncertainty: false);
        $targetAge = (int) $plan['target_retirement_age'];
        $projected = (float) $plan['planning_total_at_target_age'];
        $shortfall = $target - $projected;
        $currentAge = (int) ($user->date_of_birth?->age ?? 0);
        $years = max(0, $targetAge - $currentAge);
        if ($shortfall <= 0 || $targetAge <= 0) {
            return null;
        }

        $neededYearly = $this->contributionFor($plan, $shortfall, $years);
        $limitYearly = $this->reliefLimit($user, (array) ($analysisData['annual_allowance'] ?? []));
        $money = $this->affordability->moneyThisYear($user);
        // Net money buys more gross through relief at source (FA 2004 s192).
        // No fallback: without the rate the card would claim relief it never added.
        $basicRate = (float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'];
        $affordableYearly = $money === null ? null : $money / (1 - $basicRate);
        // Someone with no income pays from savings: a one-off sum, not a yearly
        // surplus, so it is spread over the years to retirement.
        if ($affordableYearly !== null && $this->affordability->fundedFromCash($user)) {
            $affordableYearly /= max(1, $years);
        }

        $payableYearly = $affordableYearly === null ? null : max(0.0, min($neededYearly, $affordableYearly, $limitYearly));
        $closes = $payableYearly !== null && $neededYearly > 0 && $payableYearly >= $neededYearly - 1;

        [$ageToClose, $shortAtLastAge] = $payableYearly === null || $closes
            ? [null, null]
            : $this->ageThatCloses($user, $plan, $target, $currentAge, $payableYearly);

        return [
            'shortfall' => round($shortfall, 2),
            'target_income' => round($target, 2),
            'projected_income' => round($projected, 2),
            'target_age' => $targetAge,
            'years_to_retirement' => $years,
            'needed_monthly' => round($neededYearly / 12, 2),
            'limit_monthly' => round($limitYearly / 12, 2),
            'affordability_known' => $money !== null,
            'affordable_monthly' => $affordableYearly === null ? null : round($affordableYearly / 12, 2),
            'payable_monthly' => $payableYearly === null ? null : round($payableYearly / 12, 2),
            'payable_net_monthly' => $payableYearly === null ? null : round($payableYearly * (1 - $basicRate) / 12, 2),
            'closes_gap' => $closes,
            'age_to_close' => $ageToClose,
            'short_at_last_age' => $shortAtLastAge,
            'last_age' => (int) $this->taxConfig->get('pension.relief_max_age'),
        ];
    }

    /**
     * The extra yearly contribution, paid in from now for $years years, whose
     * pot buys $annualIncome at the contract's withdrawal rate, net return and
     * compounding. Gross: what reaches the pension.
     *
     * @param  array<string, mixed>  $plan  RetirementProjectionContractService::build()
     */
    private function contributionFor(array $plan, float $annualIncome, int $years): float
    {
        if ($annualIncome <= 0 || $years <= 0) {
            return 0.0;
        }
        $pot = $annualIncome / (float) $plan['assumptions']['sustainable_withdrawal_rate']['decimal'];
        // The pot a £1 a month contribution builds; the contract is linear in it.
        $perPound = $this->extraPot($plan, 1.0, $years);

        return $perPound > 0 ? $pot / $perPound * 12 : 0.0;
    }

    /** @param  array<string, mixed>  $plan */
    private function extraPot(array $plan, float $monthly, int $years): float
    {
        return RetirementProjectionContractService::calculatePlanningValue(
            currentValue: 0.0,
            monthlyContribution: $monthly,
            annualReturnPercent: (float) $plan['assumptions']['net_growth_rate_percent'],
            years: $years,
            compoundPeriods: (int) $plan['assumptions']['compound_periods'],
        );
    }

    /**
     * The most that can still attract relief this year: one rule for every
     * engine (AnnualAllowanceChecker::reliefRoomThisYear).
     *
     * @param  array<string, mixed>  $allowance  RetirementAgent::analyze() annual_allowance
     */
    private function reliefLimit(User $user, array $allowance): float
    {
        return $this->allowanceChecker->reliefRoomThisYear($user, $allowance);
    }

    /**
     * The first age, after the target, at which the contract with the payable
     * contribution reaches the target: each defined contribution pension
     * drawn from that age instead, a defined benefit pension or the State
     * Pension once it has started. Otherwise what is still short at the last
     * age relief is given.
     *
     * @param  array<string, mixed>  $plan  RetirementProjectionContractService::build()
     * @return array{0: int|null, 1: float|null}
     */
    private function ageThatCloses(User $user, array $plan, float $target, int $currentAge, float $payableYearly): array
    {
        $lastAge = (int) $this->taxConfig->get('pension.relief_max_age');
        $rate = (float) $plan['assumptions']['sustainable_withdrawal_rate']['decimal'];
        $income = 0.0;
        for ($age = (int) $plan['target_retirement_age'] + 1; $age <= $lastAge; $age++) {
            $years = max(0, $age - $currentAge);
            $income = $this->extraPot($plan, $payableYearly / 12, $years) * $rate;
            foreach ($plan['products'] as $product) {
                if ($product['resource_type'] === 'dc_pension') {
                    $income += RetirementProjectionContractService::calculatePlanningValue(
                        currentValue: (float) $product['current_value'],
                        monthlyContribution: (float) $product['monthly_contribution'],
                        annualReturnPercent: (float) $plan['assumptions']['net_growth_rate_percent'],
                        years: $years,
                        compoundPeriods: (int) $plan['assumptions']['compound_periods'],
                    ) * $rate;
                } elseif ((int) $product['commencement_age'] <= $age) {
                    $income += (float) $product['annual_income'];
                }
            }
            if ($income >= $target) {
                return [$age, null];
            }
        }

        return [null, $lastAge > (int) $plan['target_retirement_age'] ? round($target - $income, 2) : null];
    }
}
