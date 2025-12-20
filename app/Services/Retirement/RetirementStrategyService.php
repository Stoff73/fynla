<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Services\UserProfile\UserProfileService;

/**
 * Retirement Strategy Service
 *
 * Analyzes user's retirement position and recommends strategies to achieve retirement goals.
 * Strategies are prioritized: employer match first, then contribution increases, retirement age, income target.
 */
class RetirementStrategyService
{
    private const ON_TRACK_PROBABILITY = 95;

    public function __construct(
        private TaxConfigService $taxConfig,
        private UserProfileService $userProfileService,
        private UKTaxCalculator $taxCalculator,
        private RetirementProjectionService $projectionService,
        private AnnualAllowanceChecker $allowanceChecker
    ) {}

    /**
     * Get applicable retirement strategies for a user.
     */
    public function getStrategies(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])
            ->findOrFail($userId);

        // Get current projections
        $projections = $this->projectionService->getProjections($userId);
        $currentStatus = $this->extractCurrentStatus($projections);

        // If already on track, return early
        if ($currentStatus['probability'] >= self::ON_TRACK_PROBABILITY) {
            return [
                'current_status' => $currentStatus,
                'affordability' => $this->calculateAffordability($user),
                'annual_allowance' => $this->getAnnualAllowanceStatus($userId),
                'strategies' => [],
                'on_track_at_strategy' => 0,
                'message' => 'You are on track to achieve your retirement goals.',
            ];
        }

        // Calculate affordability and allowance
        $affordability = $this->calculateAffordability($user);
        $allowanceStatus = $this->getAnnualAllowanceStatus($userId);

        // Build strategies in priority order
        $strategies = [];
        $cumulativeProbability = $currentStatus['probability'];

        // Track cumulative additional income for chained strategies
        $cumulativeAdditionalIncome = 0.0;
        $cumulativeAdditionalMonthly = 0.0;

        // Priority 1: Employer match strategies
        $employerMatchStrategies = $this->checkEmployerMatchStrategies($user, $currentStatus, $cumulativeAdditionalIncome);
        foreach ($employerMatchStrategies as $strategy) {
            // Add projection data if this strategy gets user on track
            if ($strategy['impact']['new_probability'] >= self::ON_TRACK_PROBABILITY) {
                $strategy['projection'] = $this->buildStrategyProjection(
                    $currentStatus,
                    $cumulativeAdditionalMonthly + $strategy['impact']['additional_monthly'],
                    $strategy['impact']['additional_annual_income']
                );
            }
            $strategies[] = $strategy;
            $cumulativeAdditionalIncome += $strategy['impact']['additional_annual_income'] ?? 0;
            $cumulativeAdditionalMonthly += $strategy['impact']['additional_monthly'] ?? 0;
            $cumulativeProbability = $strategy['impact']['new_probability'];
            if ($cumulativeProbability >= self::ON_TRACK_PROBABILITY) {
                break;
            }
        }

        // Priority 2: Increase contributions (if still not on track)
        if ($cumulativeProbability < self::ON_TRACK_PROBABILITY) {
            $contributionStrategy = $this->checkContributionIncreaseStrategy(
                $user,
                $affordability,
                $allowanceStatus,
                $currentStatus,
                $cumulativeAdditionalIncome
            );
            if ($contributionStrategy) {
                if ($contributionStrategy['impact']['new_probability'] >= self::ON_TRACK_PROBABILITY) {
                    $contributionStrategy['projection'] = $this->buildStrategyProjection(
                        $currentStatus,
                        $cumulativeAdditionalMonthly + $contributionStrategy['impact']['additional_monthly'],
                        $cumulativeAdditionalIncome + $contributionStrategy['impact']['additional_annual_income']
                    );
                }
                $strategies[] = $contributionStrategy;
                $cumulativeAdditionalIncome += $contributionStrategy['impact']['additional_annual_income'] ?? 0;
                $cumulativeAdditionalMonthly += $contributionStrategy['impact']['additional_monthly'] ?? 0;
                $cumulativeProbability = $contributionStrategy['impact']['new_probability'];
            }
        }

        // Priority 3: Retirement age (if still not on track)
        if ($cumulativeProbability < self::ON_TRACK_PROBABILITY) {
            $retirementAgeStrategy = $this->checkRetirementAgeStrategy($user, $currentStatus, $cumulativeAdditionalIncome);
            if ($retirementAgeStrategy) {
                if ($retirementAgeStrategy['impact']['new_probability'] >= self::ON_TRACK_PROBABILITY) {
                    $retirementAgeStrategy['projection'] = $this->buildStrategyProjection(
                        $currentStatus,
                        $cumulativeAdditionalMonthly,
                        $cumulativeAdditionalIncome + $retirementAgeStrategy['impact']['additional_annual_income']
                    );
                }
                $strategies[] = $retirementAgeStrategy;
                $cumulativeAdditionalIncome += $retirementAgeStrategy['impact']['additional_annual_income'] ?? 0;
                $cumulativeProbability = $retirementAgeStrategy['impact']['new_probability'];
            }
        }

        // Priority 4: Reduce income target (if still not on track)
        if ($cumulativeProbability < self::ON_TRACK_PROBABILITY) {
            $incomeTargetStrategy = $this->checkIncomeTargetStrategy($user, $projections, $currentStatus, $cumulativeAdditionalIncome);
            if ($incomeTargetStrategy) {
                $strategies[] = $incomeTargetStrategy;
                $cumulativeProbability = $incomeTargetStrategy['impact']['new_probability'];
            }
        }

        // Find which strategy gets user on track
        $onTrackAtStrategy = null;
        foreach ($strategies as $index => $strategy) {
            if ($strategy['impact']['new_probability'] >= self::ON_TRACK_PROBABILITY) {
                $onTrackAtStrategy = $index + 1;
                break;
            }
        }

        return [
            'current_status' => $currentStatus,
            'affordability' => $affordability,
            'annual_allowance' => $allowanceStatus,
            'strategies' => $strategies,
            'on_track_at_strategy' => $onTrackAtStrategy,
        ];
    }

    /**
     * Calculate the impact of a specific strategy change.
     */
    public function calculateStrategyImpact(int $userId, string $strategyType, float $newValue): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])
            ->findOrFail($userId);

        // Get base projections
        $projections = $this->projectionService->getProjections($userId);
        $baseProbability = $projections['income_drawdown']['probability'];

        // Calculate new probability based on strategy type
        $newProbability = match ($strategyType) {
            'employer_match' => $this->calculateEmployerMatchImpact($user, $newValue, $projections),
            'increase_contribution' => $this->calculateContributionImpact($user, $newValue, $projections),
            'retirement_age' => $this->calculateRetirementAgeImpact($user, (int) $newValue, $projections),
            'income_target' => $this->calculateIncomeTargetImpact($user, $newValue, $projections),
            default => $baseProbability,
        };

        $improvement = $newProbability - $baseProbability;

        return [
            'strategy_type' => $strategyType,
            'new_value' => $newValue,
            'base_probability' => $baseProbability,
            'new_probability' => min(100, $newProbability),
            'probability_improvement' => max(0, $improvement),
            'on_track' => $newProbability >= self::ON_TRACK_PROBABILITY,
        ];
    }

    /**
     * Extract current status from projections.
     */
    private function extractCurrentStatus(array $projections): array
    {
        $drawdown = $projections['income_drawdown'];
        $potProjection = $projections['pension_pot_projection'];
        $firstYearIncome = $drawdown['yearly_income'][0]['total_income'] ?? 0;

        return [
            'on_track_status' => $drawdown['on_track_status'],
            'probability' => $drawdown['probability'],
            'projected_income' => $firstYearIncome,  // Actual first-year income
            'target_income' => $drawdown['target_income'],
            'current_net_income' => $drawdown['current_net_income'],
            'income_gap' => max(0, $drawdown['target_income'] - $firstYearIncome),
            'income_coverage_percent' => $drawdown['target_income'] > 0
                ? round(($firstYearIncome / $drawdown['target_income']) * 100, 1)
                : 0,
            'current_pot' => $potProjection['current_value'],  // Current pot value (today)
            'pot_at_retirement' => $drawdown['starting_pot'],  // Projected pot at retirement (5th percentile)
            'current_monthly_contribution' => $potProjection['monthly_contribution'],
            'retirement_age' => $drawdown['retirement_age'],
            'years_to_retirement' => $potProjection['years_to_retirement'],
            'expected_return' => $potProjection['expected_return'],
            'guaranteed_income' => $drawdown['guaranteed_income']['total'] ?? 0,
            // Include Monte Carlo year-by-year data for consistent projections
            'monte_carlo_year_by_year' => $potProjection['year_by_year'] ?? [],
        ];
    }

    /**
     * Calculate user's affordability (disposable income).
     * Uses the same calculation as Income & Occupation tab in User Profile.
     */
    private function calculateAffordability(User $user): array
    {
        $profile = $this->userProfileService->getCompleteProfile($user);
        $incomeData = $profile['income_occupation'] ?? [];

        $grossIncome = (float) ($incomeData['total_annual_income'] ?? 0);
        $netIncome = (float) ($incomeData['net_income'] ?? 0);

        // Use annual_expenditure from profile (includes categories + financial commitments)
        $annualExpenditure = (float) ($incomeData['annual_expenditure'] ?? 0);
        $monthlyExpenditure = (float) ($incomeData['monthly_expenditure'] ?? 0);

        // Get existing pension contributions
        $existingContributions = $this->calculateTotalContributions($user);

        // Disposable income from profile (already calculated correctly)
        $disposableIncome = (float) ($incomeData['disposable_income'] ?? 0);
        $monthlyDisposable = (float) ($incomeData['monthly_disposable'] ?? 0);

        return [
            'gross_income' => round($grossIncome, 2),
            'net_income' => round($netIncome, 2),
            'annual_expenditure' => round($annualExpenditure, 2),
            'monthly_expenditure' => round($monthlyExpenditure, 2),
            'existing_pension_contributions' => round($existingContributions, 2),
            'disposable_income' => round($disposableIncome, 2),
            'monthly_disposable' => round($monthlyDisposable, 2),
        ];
    }

    /**
     * Get annual allowance status with carry forward information.
     */
    private function getAnnualAllowanceStatus(int $userId): array
    {
        $taxYear = $this->getCurrentTaxYear();
        $allowance = $this->allowanceChecker->checkAnnualAllowance($userId, $taxYear);

        // Check if carry forward is actually available (need 3-year history)
        $carryForwardAvailable = $this->hasThreeYearContributionHistory($userId);

        return [
            'standard_allowance' => $allowance['standard_allowance'],
            'available_allowance' => $allowance['available_allowance'],
            'is_tapered' => $allowance['is_tapered'],
            'current_contributions' => $allowance['total_contributions'],
            'remaining_allowance' => $allowance['remaining_allowance'],
            'carry_forward' => [
                'available' => $carryForwardAvailable,
                'amount' => $carryForwardAvailable ? $allowance['carry_forward_available'] : 0,
                'message' => $carryForwardAvailable
                    ? null
                    : 'Carry forward not available - three year contribution history needed',
            ],
        ];
    }

    /**
     * Check for employer match optimization opportunities.
     */
    private function checkEmployerMatchStrategies(User $user, array $currentStatus, float $cumulativeAdditionalIncome): array
    {
        $strategies = [];
        $priority = 1;

        $workplacePensions = $user->dcPensions->where('scheme_type', 'workplace');

        foreach ($workplacePensions as $pension) {
            $matchLimit = (float) ($pension->employer_matching_limit ?? 5.0); // Default 5% if not set
            $currentEmployee = (float) ($pension->employee_contribution_percent ?? 0);

            if ($currentEmployee < $matchLimit) {
                $annualSalary = (float) ($pension->annual_salary ?? $user->annual_employment_income ?? 0);

                // Calculate additional contribution to reach match
                $additionalPercent = $matchLimit - $currentEmployee;
                $additionalMonthly = ($annualSalary * $additionalPercent / 100) / 12;
                $employerBonus = $additionalMonthly; // Employer matches
                $totalAdditionalMonthly = $additionalMonthly + $employerBonus;

                // Calculate realistic impact on retirement income
                $additionalAnnualIncome = $this->calculateContributionImpactOnIncome(
                    $totalAdditionalMonthly,
                    $currentStatus['years_to_retirement'],
                    $currentStatus['expected_return']
                );

                // Calculate new probability including cumulative prior strategies
                $newProbability = $this->calculateNewProbability(
                    $currentStatus['projected_income'] + $cumulativeAdditionalIncome,
                    $currentStatus['target_income'],
                    $additionalAnnualIncome
                );

                $strategies[] = [
                    'type' => 'employer_match',
                    'applicable' => true,
                    'priority' => $priority,
                    'title' => 'Maximise Employer Match',
                    'description' => sprintf(
                        'Your employer matches up to %.1f%% of salary. You\'re currently contributing %.1f%%.',
                        $matchLimit,
                        $currentEmployee
                    ),
                    'pension_id' => $pension->id,
                    'pension_name' => $pension->scheme_name ?? 'Workplace Pension',
                    'current_value' => $currentEmployee,
                    'recommended_value' => $matchLimit,
                    'slider_config' => [
                        'min' => $currentEmployee,
                        'max' => $matchLimit,
                        'step' => 0.5,
                        'unit' => '%',
                        'format' => 'percentage',
                    ],
                    'impact' => [
                        'additional_monthly' => round($totalAdditionalMonthly, 2),
                        'additional_annual_income' => round($additionalAnnualIncome, 2),
                        'new_probability' => round($newProbability, 0),
                    ],
                ];

                $priority++;
            }
        }

        return $strategies;
    }

    /**
     * Check for contribution increase opportunity.
     */
    private function checkContributionIncreaseStrategy(
        User $user,
        array $affordability,
        array $allowanceStatus,
        array $currentStatus,
        float $cumulativeAdditionalIncome
    ): ?array {
        $disposableIncome = $affordability['disposable_income'];
        $remainingAllowance = $allowanceStatus['remaining_allowance'];

        // Include carry forward if available
        if ($allowanceStatus['carry_forward']['available']) {
            $remainingAllowance += $allowanceStatus['carry_forward']['amount'];
        }

        // Must have positive disposable income and available allowance
        if ($disposableIncome <= 0 || $remainingAllowance <= 0) {
            return null;
        }

        // Current total monthly contributions
        $currentMonthly = $this->calculateTotalContributions($user) / 12;

        // Maximum additional contribution is limited by both affordability and allowance
        $maxAdditionalAnnual = min($disposableIncome, $remainingAllowance);
        $maxAdditionalMonthly = $maxAdditionalAnnual / 12;

        // Recommended: use half of available additional capacity
        $recommendedAdditional = $maxAdditionalMonthly * 0.5;
        $recommendedMonthly = $currentMonthly + $recommendedAdditional;
        $maxMonthly = $currentMonthly + $maxAdditionalMonthly;

        // Calculate realistic impact on retirement income
        $additionalAnnualIncome = $this->calculateContributionImpactOnIncome(
            $recommendedAdditional,
            $currentStatus['years_to_retirement'],
            $currentStatus['expected_return']
        );

        // Calculate new probability including cumulative prior strategies
        $newProbability = $this->calculateNewProbability(
            $currentStatus['projected_income'] + $cumulativeAdditionalIncome,
            $currentStatus['target_income'],
            $additionalAnnualIncome
        );

        return [
            'type' => 'increase_contribution',
            'applicable' => true,
            'priority' => 2,
            'title' => 'Increase Pension Contributions',
            'description' => sprintf(
                'You have disposable income of %s/month available for additional contributions.',
                $this->formatCurrency($disposableIncome / 12)
            ),
            'current_value' => round($currentMonthly, 0),
            'recommended_value' => round($recommendedMonthly, 0),
            'slider_config' => [
                'min' => round($currentMonthly, 0),
                'max' => round($maxMonthly, 0),
                'step' => 50,
                'unit' => '/month',
                'format' => 'currency',
            ],
            'constraints' => [
                'affordability_limit' => round($disposableIncome / 12, 2),
                'annual_allowance_limit' => round($remainingAllowance / 12, 2),
            ],
            'impact' => [
                'additional_monthly' => round($recommendedAdditional, 2),
                'additional_annual_income' => round($additionalAnnualIncome, 2),
                'new_probability' => round($newProbability, 0),
            ],
        ];
    }

    /**
     * Check for retirement age adjustment strategy.
     */
    private function checkRetirementAgeStrategy(User $user, array $currentStatus, float $cumulativeAdditionalIncome): ?array
    {
        $currentAge = $user->date_of_birth?->age ?? 40;
        $currentRetirementAge = $user->target_retirement_age ?? 65;

        // Minimum is current target, max is 75 (UK pension access limit)
        $minAge = $currentRetirementAge;
        $maxAge = 75;

        // Only offer if there's room to adjust
        if ($minAge >= $maxAge) {
            return null;
        }

        // Recommend 2 years later
        $recommendedAge = min($currentRetirementAge + 2, $maxAge);
        $yearsDelay = $recommendedAge - $currentRetirementAge;

        // Calculate additional income from delayed retirement
        // More years means: more contributions + more growth + less retirement years
        // Estimate: pot grows ~7% per extra year, so income increases proportionally
        $currentPot = $currentStatus['starting_pot'];
        $currentIncome = $currentStatus['projected_income'] + $cumulativeAdditionalIncome;

        // Simple estimate: each extra year adds ~10% to pot (contributions + growth)
        // which translates to ~10% more income
        $additionalAnnualIncome = $currentIncome * ($yearsDelay * 0.10);

        $newProbability = $this->calculateNewProbability(
            $currentIncome,
            $currentStatus['target_income'],
            $additionalAnnualIncome
        );

        return [
            'type' => 'retirement_age',
            'applicable' => true,
            'priority' => 3,
            'title' => 'Adjust Retirement Age',
            'description' => 'Working longer allows more time for contributions and investment growth.',
            'current_value' => $currentRetirementAge,
            'recommended_value' => $recommendedAge,
            'slider_config' => [
                'min' => $minAge,
                'max' => $maxAge,
                'step' => 1,
                'unit' => ' years',
                'format' => 'age',
            ],
            'impact' => [
                'years_delay' => $yearsDelay,
                'additional_annual_income' => round($additionalAnnualIncome, 2),
                'new_probability' => round($newProbability, 0),
            ],
        ];
    }

    /**
     * Check for income target reduction strategy.
     * This strategy is about accepting a LOWER retirement income target.
     * It does NOT use cumulative income - it's an alternative to contribution-based strategies.
     */
    private function checkIncomeTargetStrategy(User $user, array $projections, array $currentStatus, float $cumulativeAdditionalIncome): ?array
    {
        $targetIncome = $projections['income_drawdown']['target_income'];
        $guaranteedIncome = $projections['income_drawdown']['guaranteed_income']['total'];

        // Use ORIGINAL projected income (not cumulative) - this is an alternative strategy
        $originalProjectedIncome = $currentStatus['projected_income'];

        // Minimum is guaranteed income (or original projected if higher), max is current target
        $minIncome = max($guaranteedIncome, $originalProjectedIncome);
        $maxIncome = $targetIncome;

        // Only offer if there's room to reduce (target must be higher than what we can achieve)
        if ($minIncome >= $maxIncome * 0.95) {
            return null;
        }

        // Recommend reducing to match what's achievable (original projected income)
        // This shows "if you accept what you'll actually get, you're on track"
        $recommendedIncome = $originalProjectedIncome;

        // Calculate new probability with reduced target
        $newProbability = $this->calculateNewProbability(
            $originalProjectedIncome,
            $recommendedIncome,
            0  // No additional income, just changing target
        );

        return [
            'type' => 'income_target',
            'applicable' => true,
            'priority' => 4,
            'title' => 'Adjust Retirement Income Target',
            'description' => sprintf(
                'Accept a lower retirement income of %s/year (currently projecting %s).',
                $this->formatCurrency($recommendedIncome),
                $this->formatCurrency($originalProjectedIncome)
            ),
            'current_value' => round($targetIncome, 0),
            'recommended_value' => round($recommendedIncome, 0),
            'slider_config' => [
                'min' => round($minIncome, 0),
                'max' => round($maxIncome, 0),
                'step' => 1000,
                'unit' => '/year',
                'format' => 'currency',
            ],
            'constraints' => [
                'guaranteed_income' => round($guaranteedIncome, 2),
                'projected_income' => round($originalProjectedIncome, 2),
            ],
            'impact' => [
                'income_reduction' => round($targetIncome - $recommendedIncome, 2),
                'additional_annual_income' => 0,
                'new_probability' => round($newProbability, 0),
            ],
        ];
    }

    /**
     * Calculate impact of employer match contribution change on probability.
     * The new value is a percentage of salary.
     */
    private function calculateEmployerMatchImpact(User $user, float $newContributionPercent, array $projections): float
    {
        $baseProbability = $projections['income_drawdown']['probability'];

        // Find workplace pensions and calculate additional contribution
        $additionalMonthly = 0.0;
        $workplacePensions = $user->dcPensions->where('scheme_type', 'workplace');

        foreach ($workplacePensions as $pension) {
            $currentEmployee = (float) ($pension->employee_contribution_percent ?? 0);
            $annualSalary = (float) ($pension->annual_salary ?? $user->annual_employment_income ?? 0);

            // Additional percentage contribution
            $additionalPercent = $newContributionPercent - $currentEmployee;
            if ($additionalPercent > 0) {
                // Employee contribution + employer match
                $employeeAdditional = ($annualSalary * $additionalPercent / 100) / 12;
                $employerMatch = $employeeAdditional; // Employer matches
                $additionalMonthly += $employeeAdditional + $employerMatch;
            }
        }

        // Each £100/month additional contribution adds roughly 2% probability
        $improvement = ($additionalMonthly / 100) * 2;

        return min(100, $baseProbability + $improvement);
    }

    /**
     * Calculate impact of contribution change on probability.
     */
    private function calculateContributionImpact(User $user, float $newMonthlyContribution, array $projections): float
    {
        $currentMonthly = $this->calculateTotalContributions($user) / 12;
        $additionalMonthly = $newMonthlyContribution - $currentMonthly;

        $baseProbability = $projections['income_drawdown']['probability'];

        // Each £100/month additional contribution adds roughly 2% probability
        $improvement = ($additionalMonthly / 100) * 2;

        return min(100, $baseProbability + $improvement);
    }

    /**
     * Calculate impact of retirement age change on probability.
     */
    private function calculateRetirementAgeImpact(User $user, int $newAge, array $projections): float
    {
        $currentAge = $projections['income_drawdown']['retirement_age'];
        $yearsDelay = $newAge - $currentAge;

        $baseProbability = $projections['income_drawdown']['probability'];

        // Each year delay adds roughly 5% probability
        $improvement = $yearsDelay * 5;

        return min(100, $baseProbability + $improvement);
    }

    /**
     * Calculate impact of income target change on probability.
     */
    private function calculateIncomeTargetImpact(User $user, float $newTarget, array $projections): float
    {
        $currentTarget = $projections['income_drawdown']['target_income'];
        $percentReduction = ($currentTarget - $newTarget) / $currentTarget * 100;

        $baseProbability = $projections['income_drawdown']['probability'];

        // Each 10% reduction adds roughly 10% probability
        $improvement = $percentReduction;

        return min(100, $baseProbability + $improvement);
    }

    /**
     * Calculate total annual pension contributions.
     */
    private function calculateTotalContributions(User $user): float
    {
        $total = 0.0;

        foreach ($user->dcPensions as $pension) {
            if ($pension->monthly_contribution_amount) {
                $total += (float) $pension->monthly_contribution_amount * 12;
            } elseif ($pension->employee_contribution_percent && $pension->annual_salary) {
                $employeeContrib = (float) $pension->annual_salary * (float) $pension->employee_contribution_percent / 100;
                $employerContrib = (float) ($pension->employer_contribution_percent ?? 0) * (float) $pension->annual_salary / 100;
                $total += $employeeContrib + $employerContrib;
            }
        }

        return $total;
    }

    /**
     * Check if user has 3-year contribution history for carry forward.
     *
     * Note: Currently returns false as contribution history tracking is not yet
     * implemented. When enabled, this should check for 3 years of pension
     * contribution records to calculate carry forward allowance.
     */
    private function hasThreeYearContributionHistory(int $userId): bool
    {
        // Contribution history tracking requires historical data storage
        // For now, return false to show the "not available" message in UI
        return false;
    }

    /**
     * Get current tax year string.
     */
    private function getCurrentTaxYear(): string
    {
        $now = now();
        $year = $now->year;

        // Tax year runs April 6 to April 5
        if ($now->month < 4 || ($now->month === 4 && $now->day < 6)) {
            $year--;
        }

        return sprintf('%d/%d', $year, ($year + 1) % 100);
    }

    /**
     * Format currency value.
     */
    private function formatCurrency(float $value): string
    {
        return '£'.number_format($value, 0);
    }

    /**
     * Build projection data for a strategy that gets user on track.
     * Includes year-by-year pension pot growth and sustainable income.
     * Shows both "with strategy" and "without strategy" projections for comparison.
     *
     * IMPORTANT: Uses Monte Carlo 5th percentile data for "without strategy" to match
     * the Future Value tab's 95% probability projection. This ensures consistency.
     */
    private function buildStrategyProjection(
        array $currentStatus,
        float $additionalMonthlyContribution,
        float $additionalAnnualIncome
    ): array {
        $yearsToRetirement = $currentStatus['years_to_retirement'];
        $expectedReturn = $currentStatus['expected_return'] / 100;
        $currentYear = (int) date('Y');
        $currentPot = $currentStatus['current_pot'];

        // Use Monte Carlo year-by-year data for "without strategy" baseline
        // This ensures consistency with the Future Value tab's 95% probability projection
        // Monte Carlo data array indices 0-29 correspond to projection years 1-30
        $monteCarloData = $currentStatus['monte_carlo_year_by_year'] ?? [];
        $monteCarloCount = count($monteCarloData);

        // Build year-by-year pot growth
        $yearByYear = [];

        // Calculate the additional pot accumulated from extra contributions using compound growth
        // This is added ON TOP of the Monte Carlo baseline for "with strategy"
        $monthlyRate = $expectedReturn / 12;

        // Year 0 = today (current pot), Years 1-30 = projections from Monte Carlo
        for ($year = 0; $year <= $yearsToRetirement; $year++) {
            if ($year === 0) {
                // Today's value
                $potWithoutStrategy = $currentPot;
                $displayYear = $currentYear;
            } else {
                // Monte Carlo projection - array is 0-indexed, so year 1 is index 0
                $mcIndex = $year - 1;
                if ($mcIndex < $monteCarloCount) {
                    $potWithoutStrategy = $monteCarloData[$mcIndex]['percentile_5'] ?? $currentPot;
                    $displayYear = $monteCarloData[$mcIndex]['year'] ?? ($currentYear + $year);
                } else {
                    // Fall back to last available Monte Carlo value
                    $lastMcIndex = $monteCarloCount - 1;
                    $potWithoutStrategy = $monteCarloData[$lastMcIndex]['percentile_5'] ?? $currentPot;
                    $displayYear = $currentYear + $year;
                }
            }

            // Calculate additional pot from extra contributions at this point
            // Using future value of annuity formula
            $additionalPotAccumulated = 0.0;
            if ($year > 0 && $monthlyRate > 0) {
                $months = $year * 12;
                $additionalPotAccumulated = $additionalMonthlyContribution *
                    ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
            }

            // "With strategy" = Monte Carlo baseline + additional pot from extra contributions
            $potWithStrategy = $potWithoutStrategy + $additionalPotAccumulated;

            $yearByYear[] = [
                'year' => $displayYear,
                'years_from_now' => $year,
                'pot_with_strategy' => round($potWithStrategy, 0),
                'pot_without_strategy' => round($potWithoutStrategy, 0),
            ];
        }

        // Get final values at retirement (last Monte Carlo year = percentile_5_at_retirement)
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];
        $potAtRetirementWith = $lastYear['pot_with_strategy'] ?? 0;
        $potAtRetirementWithout = $lastYear['pot_without_strategy'] ?? 0;

        // Use 4.7% sustainable withdrawal rate (matches RetirementProjectionService constant)
        $sustainableWithdrawalRate = 0.047;

        $sustainableIncomeWith = $potAtRetirementWith * $sustainableWithdrawalRate;
        $sustainableIncomeWithout = $potAtRetirementWithout * $sustainableWithdrawalRate;

        // Add guaranteed income (DB pensions, state pension)
        $guaranteedIncome = $currentStatus['guaranteed_income'] ?? 0;
        $totalRetirementIncomeWith = $sustainableIncomeWith + $guaranteedIncome;
        $totalRetirementIncomeWithout = $sustainableIncomeWithout + $guaranteedIncome;

        return [
            'pot_growth' => $yearByYear,
            'with_strategy' => [
                'pot_at_retirement' => round($potAtRetirementWith, 0),
                'sustainable_income' => round($sustainableIncomeWith, 0),
                'guaranteed_income' => round($guaranteedIncome, 0),
                'total_retirement_income' => round($totalRetirementIncomeWith, 0),
                'income_coverage_percent' => $currentStatus['target_income'] > 0
                    ? round($totalRetirementIncomeWith / $currentStatus['target_income'] * 100, 1)
                    : 0,
            ],
            'without_strategy' => [
                'pot_at_retirement' => round($potAtRetirementWithout, 0),
                'sustainable_income' => round($sustainableIncomeWithout, 0),
                'guaranteed_income' => round($guaranteedIncome, 0),
                'total_retirement_income' => round($totalRetirementIncomeWithout, 0),
                'income_coverage_percent' => $currentStatus['target_income'] > 0
                    ? round($totalRetirementIncomeWithout / $currentStatus['target_income'] * 100, 1)
                    : 0,
            ],
            'target_income' => round($currentStatus['target_income'], 0),
        ];
    }

    /**
     * Calculate realistic impact of additional contributions on retirement income.
     *
     * Uses compound growth formula to project how additional contributions
     * translate to additional retirement income.
     */
    private function calculateContributionImpactOnIncome(
        float $additionalMonthlyContribution,
        int $yearsToRetirement,
        float $expectedReturnPercent
    ): float {
        if ($yearsToRetirement <= 0 || $additionalMonthlyContribution <= 0) {
            return 0;
        }

        $monthlyRate = ($expectedReturnPercent / 100) / 12;
        $months = $yearsToRetirement * 12;

        // Future value of monthly contributions: PMT × (((1 + r)^n - 1) / r)
        if ($monthlyRate > 0) {
            $futureValue = $additionalMonthlyContribution *
                ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
        } else {
            $futureValue = $additionalMonthlyContribution * $months;
        }

        // Convert to annual income using sustainable withdrawal rate (4.7%)
        return $futureValue * 0.047;
    }

    /**
     * Calculate new probability based on additional income.
     *
     * Maps the new income coverage ratio to probability using the same
     * thresholds as RetirementProjectionService.
     */
    private function calculateNewProbability(
        float $currentIncome,
        float $targetIncome,
        float $additionalIncome
    ): float {
        $newIncome = $currentIncome + $additionalIncome;
        $incomeRatio = $targetIncome > 0 ? $newIncome / $targetIncome : 0;

        // Same thresholds as RetirementProjectionService::calculateRetirementProbability
        if ($incomeRatio >= 1.0) {
            return 95;
        } elseif ($incomeRatio >= 0.90) {
            return 85;
        } elseif ($incomeRatio >= 0.75) {
            return 65;
        } elseif ($incomeRatio >= 0.50) {
            return 40;
        } elseif ($incomeRatio >= 0.25) {
            return 20;
        }

        return 10;
    }
}
