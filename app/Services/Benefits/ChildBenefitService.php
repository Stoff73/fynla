<?php

declare(strict_types=1);

namespace App\Services\Benefits;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Child Benefit Calculation Service
 *
 * Calculates UK Child Benefit entitlement and High Income Child Benefit Charge (HICBC).
 *
 * UK Child Benefit Rules (2024-25):
 * - Eldest/only child: £26.05/week (£1,354.60/year)
 * - Additional children: £17.25/week (£897.00/year)
 * - HICBC threshold: £60,000 adjusted net income
 * - HICBC full clawback: £80,000 (100% clawed back)
 * - Clawback rate: 1% per £200 over threshold
 */
class ChildBenefitService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate total annual Child Benefit for a user.
     *
     * @return array Contains annual_amount, eligible_children_count, breakdown
     */
    public function calculateAnnualChildBenefit(User $user): array
    {
        $config = $this->taxConfig->getChildBenefit();
        $eligibleChildren = $this->getEligibleChildren($user);

        if ($eligibleChildren->isEmpty()) {
            return [
                'annual_amount' => 0.0,
                'eligible_children_count' => 0,
                'breakdown' => [],
                'eldest_child_name' => null,
            ];
        }

        // Sort by date of birth to identify eldest (oldest DOB = eldest)
        $sortedChildren = $eligibleChildren->sortBy('date_of_birth');

        $breakdown = [];
        $totalAnnual = 0.0;
        $isFirst = true;
        $eldestChildName = null;

        foreach ($sortedChildren as $child) {
            $rate = $isFirst
                ? ($config['eldest_child_annual'] ?? 1354.60)
                : ($config['additional_child_annual'] ?? 897.00);

            $breakdown[] = [
                'child_id' => $child->id,
                'child_name' => $child->name ?? $child->first_name.' '.$child->last_name,
                'is_eldest' => $isFirst,
                'annual_amount' => $rate,
                'weekly_amount' => $isFirst
                    ? ($config['eldest_child_weekly'] ?? 26.05)
                    : ($config['additional_child_weekly'] ?? 17.25),
            ];

            if ($isFirst) {
                $eldestChildName = $child->name ?? $child->first_name.' '.$child->last_name;
            }

            $totalAnnual += $rate;
            $isFirst = false;
        }

        return [
            'annual_amount' => round($totalAnnual, 2),
            'eligible_children_count' => $eligibleChildren->count(),
            'breakdown' => $breakdown,
            'eldest_child_name' => $eldestChildName,
        ];
    }

    /**
     * Calculate High Income Child Benefit Charge (HICBC).
     *
     * HICBC applies when the higher-earning parent has adjusted net income
     * exceeding the threshold (currently £60,000). The charge claws back
     * 1% of the Child Benefit for every £200 of income over the threshold.
     *
     * @param  float  $adjustedNetIncome  The higher-earning parent's adjusted net income
     * @param  float  $childBenefitAmount  The total annual Child Benefit amount
     * @return array Contains applies, charge, net_benefit, clawback_percentage
     */
    public function calculateHICBC(float $adjustedNetIncome, float $childBenefitAmount): array
    {
        $config = $this->taxConfig->getChildBenefit();
        $threshold = $config['high_income_charge_threshold'] ?? 60000;
        $fullClawback = $config['high_income_full_clawback'] ?? 80000;
        $increment = $config['clawback_increment'] ?? 200;

        // HICBC doesn't apply if income is at or below threshold
        if ($adjustedNetIncome <= $threshold) {
            return [
                'applies' => false,
                'charge' => 0.0,
                'net_benefit' => $childBenefitAmount,
                'clawback_percentage' => 0.0,
                'threshold' => $threshold,
                'income_over_threshold' => 0.0,
            ];
        }

        // Calculate how much income is over the threshold
        $incomeOverThreshold = $adjustedNetIncome - $threshold;

        // Calculate clawback percentage: 1% per £200 over threshold
        // At £80,000 (£20,000 over), this equals 100%
        $clawbackPercentage = min(100, ($incomeOverThreshold / $increment));

        // Calculate the charge (amount to be paid back via tax)
        $charge = ($childBenefitAmount * $clawbackPercentage) / 100;

        return [
            'applies' => true,
            'charge' => round($charge, 2),
            'net_benefit' => round($childBenefitAmount - $charge, 2),
            'clawback_percentage' => round($clawbackPercentage, 1),
            'threshold' => $threshold,
            'full_clawback_threshold' => $fullClawback,
            'income_over_threshold' => round($incomeOverThreshold, 2),
        ];
    }

    /**
     * Get children eligible for Child Benefit.
     *
     * Returns children (child or step_child) who have receives_child_benefit = true.
     *
     * @return Collection<FamilyMember>
     */
    public function getEligibleChildren(User $user): Collection
    {
        // Ensure family members are loaded
        if (! $user->relationLoaded('familyMembers')) {
            $user->load('familyMembers');
        }

        return $user->familyMembers
            ->filter(function (FamilyMember $member) {
                return in_array($member->relationship, ['child', 'step_child'])
                    && $member->receives_child_benefit === true;
            });
    }

    /**
     * Calculate complete Child Benefit position including HICBC.
     *
     * This is a convenience method that calculates both the benefit amount
     * and any HICBC charge in one call.
     *
     * @param  User  $user  The user to calculate for
     * @param  float|null  $adjustedNetIncome  Override income (uses user's total if null)
     * @return array Complete calculation with benefit, HICBC, and net position
     */
    public function calculateChildBenefitPosition(User $user, ?float $adjustedNetIncome = null): array
    {
        // Calculate the benefit amount
        $benefit = $this->calculateAnnualChildBenefit($user);

        // If no children receiving benefit, return early
        if ($benefit['annual_amount'] <= 0) {
            return [
                'benefit' => $benefit,
                'hicbc' => [
                    'applies' => false,
                    'charge' => 0.0,
                    'net_benefit' => 0.0,
                    'clawback_percentage' => 0.0,
                ],
                'net_annual_benefit' => 0.0,
            ];
        }

        // Use provided income or calculate from user
        if ($adjustedNetIncome === null) {
            $adjustedNetIncome = $this->calculateAdjustedNetIncome($user);
        }

        // Calculate HICBC
        $hicbc = $this->calculateHICBC($adjustedNetIncome, $benefit['annual_amount']);

        return [
            'benefit' => $benefit,
            'hicbc' => $hicbc,
            'net_annual_benefit' => $hicbc['net_benefit'],
        ];
    }

    /**
     * Calculate adjusted net income for HICBC purposes.
     *
     * Adjusted net income = Total income - Pension contributions - Gift Aid donations
     *
     * For simplicity, we use total annual income from user profile.
     * In a full implementation, this would factor in pension contributions
     * and charitable donations for a more accurate figure.
     */
    private function calculateAdjustedNetIncome(User $user): float
    {
        // Sum all income sources
        $totalIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);

        // Note: For a more accurate HICBC calculation, we would deduct:
        // - Gross pension contributions (where relief claimed at source)
        // - Gift Aid donations (grossed up)
        // - Trading losses
        // This simplified version uses gross income.

        return $totalIncome;
    }
}
