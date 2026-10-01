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
 * - The shortfall, target and projection are the Retirement page's own
 *   (RetirementAgent::analyze summary), so card and page agree.
 * - The contribution that closes it inverts the same projection
 *   (PensionProjector::extraContributionForIncome).
 * - What can be paid is capped by the one affordability rule
 *   (PensionAffordability, "affordability check always", CSJ 2026-09-30) and
 *   by the relief limit: the remaining Annual Allowance plus carry forward,
 *   no more than relevant UK earnings or the basic amount (Finance Act 2004
 *   s189-190), and the Money Purchase Annual Allowance once a pension has been
 *   flexibly accessed (FA 2004 s227ZA).
 * - When that falls short, the retirement age at which the projection with
 *   the affordable payment reaches the target, up to the last age relief is
 *   given (FA 2004 s188(3)(a), `pension.relief_max_age`).
 */
class RetirementIncomePosition
{
    public function __construct(
        private readonly PensionProjector $projector,
        private readonly PensionAffordability $affordability,
        private readonly StatePensionAgeResolver $statePensionAge,
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $analysisData  RetirementAgent::analyze() data
     * @return array<string, mixed>|null null when the projection meets the target
     */
    public function for(User $user, array $analysisData): ?array
    {
        $summary = (array) ($analysisData['summary'] ?? []);
        $shortfall = (float) ($summary['income_gap'] ?? 0);
        $target = (float) ($summary['target_retirement_income'] ?? 0);
        $targetAge = (int) ($summary['target_retirement_age'] ?? 0);
        $years = (int) ($summary['years_to_retirement'] ?? 0);
        if ($shortfall <= 0 || $target <= 0 || $targetAge <= 0) {
            return null;
        }

        $neededYearly = $this->projector->extraContributionForIncome($user->id, $shortfall, $years);
        $limitYearly = $this->reliefLimit($user, (array) ($analysisData['annual_allowance'] ?? []));
        $money = $this->affordability->moneyThisYear($user);
        // Net money buys more gross through relief at source (FA 2004 s192).
        $basicRate = (float) ($this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'] ?? 0);
        $affordableYearly = $money === null ? null : $money / (1 - $basicRate);

        $payableYearly = $affordableYearly === null ? null : max(0.0, min($neededYearly, $affordableYearly, $limitYearly));
        $closes = $payableYearly !== null && $neededYearly > 0 && $payableYearly >= $neededYearly - 1;

        [$ageToClose, $shortAtLastAge] = $payableYearly === null || $closes
            ? [null, null]
            : $this->ageThatCloses($user, $target, $targetAge, $years, $payableYearly);

        return [
            'shortfall' => round($shortfall, 2),
            'target_income' => round($target, 2),
            'projected_income' => round((float) ($summary['projected_retirement_income'] ?? 0), 2),
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
     * The most that can still attract relief this year: remaining Annual
     * Allowance plus carry forward, capped at relevant UK earnings or the
     * basic amount, and at the Money Purchase Annual Allowance once a defined
     * contribution pension has been flexibly accessed.
     *
     * @param  array<string, mixed>  $allowance  RetirementAgent::analyze() annual_allowance
     */
    private function reliefLimit(User $user, array $allowance): float
    {
        $headroom = (float) ($allowance['remaining_allowance'] ?? 0) + (float) ($allowance['carry_forward_available'] ?? 0);
        $earnings = (float) ($user->annual_employment_income ?? 0) + (float) ($user->annual_self_employment_income ?? 0);
        $limit = min($headroom, max($earnings, (float) $this->taxConfig->get('pension.relevant_earnings_minimum')));

        $flexiblyAccessed = $user->dcPensions->contains(
            fn ($pension): bool => (bool) $pension->has_flexibly_accessed || (float) ($pension->annual_drawdown_income ?? 0) > 0
        );
        if ($flexiblyAccessed) {
            $paying = (float) ($allowance['total_contributions'] ?? $allowance['allowance_used'] ?? 0);
            $limit = min($limit, max(0.0, (float) $this->taxConfig->get('pension.mpaa') - $paying));
        }

        return max(0.0, $limit);
    }

    /**
     * The first age, after the target, at which the projection with the
     * payable contribution reaches the target; otherwise what is still short
     * at the last age relief is given.
     *
     * @return array{0: int|null, 1: float|null}
     */
    private function ageThatCloses(User $user, float $target, int $targetAge, int $years, float $payableYearly): array
    {
        $lastAge = (int) $this->taxConfig->get('pension.relief_max_age');
        $statePensionAge = $this->statePensionAge->forUser($user);
        $rate = $this->projector->safeWithdrawalRate();
        $income = 0.0;
        for ($age = $targetAge + 1; $age <= $lastAge; $age++) {
            $extraYears = $age - $targetAge;
            $projection = $this->projector->projectTotalRetirementIncome($user->id, $extraYears);
            $income = $projection['dc_annual_income']
                + $this->projector->potFromExtraContribution($user->id, $payableYearly, $years + $extraYears) * $rate
                + $projection['db_annual_income']
                + ($age >= $statePensionAge ? $projection['state_pension_income'] : 0.0);
            if ($income >= $target) {
                return [$age, null];
            }
        }

        return [null, $lastAge > $targetAge ? round($target - $income, 2) : null];
    }
}
