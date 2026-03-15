<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Services\UserProfile\UserProfileService;
use App\Traits\FormatsCurrency;

/**
 * Retirement Strategy Service
 *
 * Analyzes user's retirement position and recommends strategies to achieve retirement goals.
 * Strategies are prioritized: employer match first, then contribution increases, retirement age, income target.
 */
class RetirementStrategyService
{
    use FormatsCurrency;

    private const ON_TRACK_PROBABILITY = 95;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly UserProfileService $userProfileService,
        private readonly UKTaxCalculator $taxCalculator,
        private readonly RetirementProjectionService $projectionService,
        private readonly AnnualAllowanceChecker $allowanceChecker,
        private readonly RequiredCapitalCalculator $requiredCapitalCalculator,
        private readonly RetirementIncomeService $retirementIncomeService
    ) {}

    /**
     * Get applicable retirement strategies for a user.
     */
    public function getStrategies(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])
            ->findOrFail($userId);

        // Cannot calculate strategies without date of birth
        if (! $user->date_of_birth) {
            return [
                'requires_dob' => true,
                'message' => 'Please enter your date of birth in your profile to calculate pension strategies.',
                'strategies' => [],
            ];
        }

        // Get current projections
        $projections = $this->projectionService->getProjections($userId);
        $currentStatus = $this->extractCurrentStatus($projections);

        // Calculate capital position first to check if user is truly on track
        // This considers ALL assets (pension + ISAs + bonds + savings), not just pension pot
        $capitalPosition = $this->calculateCapitalPosition($userId, $projections);

        // User is "on track" if:
        // 1. Pension pot probability is >= 95% (traditional check), OR
        // 2. Achievable net income from ALL sources meets the target income
        $isOnTrack = $currentStatus['probability'] >= self::ON_TRACK_PROBABILITY
            || $capitalPosition['income_meets_target'];

        if ($isOnTrack) {
            return [
                'current_status' => $currentStatus,
                'affordability' => $this->calculateAffordability($user),
                'annual_allowance' => $this->getAnnualAllowanceStatus($userId),
                'strategies' => [],
                'on_track_at_strategy' => 0,
                'message' => $capitalPosition['income_meets_target']
                    ? 'Your combined assets can provide your target retirement income.'
                    : 'You are on track to achieve your retirement goals.',
                'capital_position' => $capitalPosition,
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
        $matchingSkippedForAffordability = false;

        // Priority 1: Employer match strategies (with affordability check)
        $employerMatchResult = $this->checkEmployerMatchStrategies($user, $currentStatus, $cumulativeAdditionalIncome, $affordability);
        $employerMatchStrategies = $employerMatchResult['strategies'];
        $matchingSkippedForAffordability = $employerMatchResult['skipped_for_affordability'];

        foreach ($employerMatchStrategies as $strategy) {
            // Only accumulate values if the strategy is affordable
            $isAffordable = $strategy['affordability']['can_afford'] ?? true;

            if ($isAffordable) {
                // Calculate TRUE cumulative income with this strategy
                $newCumulativeIncome = $cumulativeAdditionalIncome + ($strategy['impact']['additional_annual_income'] ?? 0);
                $newCumulativeMonthly = $cumulativeAdditionalMonthly + ($strategy['impact']['additional_monthly'] ?? 0);

                // Recalculate probability based on TRUE cumulative income
                $trueCumulativeProbability = $this->calculateNewProbability(
                    $currentStatus['projected_income'] + $cumulativeAdditionalIncome,
                    $currentStatus['target_income'],
                    $strategy['impact']['additional_annual_income'] ?? 0
                );

                // Update strategy's impact with correct cumulative probability
                $strategy['impact']['new_probability'] = round($trueCumulativeProbability, 0);
                $strategy['impact']['probability_improvement'] = round($trueCumulativeProbability - $cumulativeProbability, 0);

                // Build projection with cumulative values
                $strategy['projection'] = $this->buildStrategyProjection(
                    $currentStatus,
                    $newCumulativeMonthly,
                    $newCumulativeIncome
                );

                $strategies[] = $strategy;
                $cumulativeAdditionalIncome = $newCumulativeIncome;
                $cumulativeAdditionalMonthly = $newCumulativeMonthly;
                $cumulativeProbability = $trueCumulativeProbability;

                if ($cumulativeProbability >= self::ON_TRACK_PROBABILITY) {
                    break;
                }
            } else {
                // Strategy not affordable - still add to list but don't accumulate values
                $strategy['impact']['probability_improvement'] = 0;
                $strategies[] = $strategy;
            }
        }

        // Priority 2: Increase contributions (if still not on track)
        // SKIP if employer match was skipped due to affordability (user can't afford matching, so can't afford more contributions)
        if ($cumulativeProbability < self::ON_TRACK_PROBABILITY && ! $matchingSkippedForAffordability) {
            $contributionStrategy = $this->checkContributionIncreaseStrategy(
                $user,
                $affordability,
                $allowanceStatus,
                $currentStatus,
                $cumulativeAdditionalIncome
            );
            if ($contributionStrategy) {
                // Calculate TRUE cumulative income with this strategy
                $newCumulativeIncome = $cumulativeAdditionalIncome + ($contributionStrategy['impact']['additional_annual_income'] ?? 0);
                $newCumulativeMonthly = $cumulativeAdditionalMonthly + ($contributionStrategy['impact']['additional_monthly'] ?? 0);

                // Recalculate probability based on TRUE cumulative income
                $trueCumulativeProbability = $this->calculateNewProbability(
                    $currentStatus['projected_income'] + $cumulativeAdditionalIncome,
                    $currentStatus['target_income'],
                    $contributionStrategy['impact']['additional_annual_income'] ?? 0
                );

                // Update strategy's impact with correct cumulative probability
                $contributionStrategy['impact']['new_probability'] = round($trueCumulativeProbability, 0);
                $contributionStrategy['impact']['probability_improvement'] = round($trueCumulativeProbability - $cumulativeProbability, 0);

                // Build projection with cumulative values
                $contributionStrategy['projection'] = $this->buildStrategyProjection(
                    $currentStatus,
                    $newCumulativeMonthly,
                    $newCumulativeIncome
                );

                $strategies[] = $contributionStrategy;
                $cumulativeAdditionalIncome = $newCumulativeIncome;
                $cumulativeAdditionalMonthly = $newCumulativeMonthly;
                $cumulativeProbability = $trueCumulativeProbability;
            }
        }

        // Priority 3: Retirement age (if still not on track)
        $cannotAchieveTargetBy68 = false;
        $sustainableIncomeAt68 = null;

        if ($cumulativeProbability < self::ON_TRACK_PROBABILITY) {
            $retirementAgeStrategy = $this->checkRetirementAgeStrategy($user, $currentStatus, $cumulativeAdditionalIncome);
            if ($retirementAgeStrategy) {
                // Track if target cannot be achieved by age 68 (triggers Strategy 4)
                $cannotAchieveTargetBy68 = $retirementAgeStrategy['cannot_achieve_target_by_68'] ?? false;
                $sustainableIncomeAt68 = $retirementAgeStrategy['impact']['sustainable_income_at_68'] ?? null;

                // Build retirement-age-specific projection with cumulative context
                $yearsDelay = $retirementAgeStrategy['impact']['years_delay'] ?? 0;
                $retirementAgeStrategy['projection'] = $this->buildRetirementAgeProjection(
                    $currentStatus,
                    $yearsDelay,
                    $cumulativeAdditionalMonthly
                );

                // Recalculate probability from projection to ensure consistency
                $projectionIncome = $retirementAgeStrategy['projection']['with_strategy']['total_retirement_income'] ?? 0;
                $targetIncome = $currentStatus['target_income'];
                $incomeRatio = $targetIncome > 0 ? $projectionIncome / $targetIncome : 0;
                $trueCumulativeProbability = min(self::ON_TRACK_PROBABILITY, max(10, round(10 + ($incomeRatio * 85), 0)));

                // Update strategy's impact with consistent values
                $retirementAgeStrategy['impact']['new_probability'] = $trueCumulativeProbability;
                $retirementAgeStrategy['impact']['probability_improvement'] = round($trueCumulativeProbability - $cumulativeProbability, 0);

                // Calculate TRUE cumulative income with this strategy
                $newCumulativeIncome = $cumulativeAdditionalIncome + ($retirementAgeStrategy['impact']['additional_annual_income'] ?? 0);

                $strategies[] = $retirementAgeStrategy;
                $cumulativeAdditionalIncome = $newCumulativeIncome;
                $cumulativeProbability = $trueCumulativeProbability;
            }
        }

        // Priority 4: Reduce income target
        // Triggered if: still not on track OR cannot achieve target by age 68
        if ($cumulativeProbability < self::ON_TRACK_PROBABILITY || $cannotAchieveTargetBy68) {
            // Pass sustainable income at 68 if Strategy 3 flagged cannot_achieve_target_by_68
            $incomeTargetStrategy = $this->checkIncomeTargetStrategy(
                $user,
                $projections,
                $currentStatus,
                $cumulativeAdditionalIncome,
                $cannotAchieveTargetBy68 ? $sustainableIncomeAt68 : null
            );
            if ($incomeTargetStrategy) {
                // For income target, show current projection with the new target
                $incomeTargetStrategy['projection'] = $this->buildIncomeTargetProjection(
                    $currentStatus,
                    $incomeTargetStrategy['recommended_value']
                );
                // Add probability_improvement for display on initial load
                $incomeTargetStrategy['impact']['probability_improvement'] = round($incomeTargetStrategy['impact']['new_probability'] - $cumulativeProbability, 0);
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

        // $capitalPosition was already calculated at the start of this method
        return [
            'current_status' => $currentStatus,
            'affordability' => $affordability,
            'annual_allowance' => $allowanceStatus,
            'strategies' => $strategies,
            'on_track_at_strategy' => $onTrackAtStrategy,
            'capital_position' => $capitalPosition,
        ];
    }

    /**
     * Calculate the impact of a specific strategy change.
     * Returns both probability changes AND updated projection data for the chart.
     *
     * @param  float  $priorAdditionalMonthly  Cumulative monthly contributions from prior strategies
     * @param  float  $priorAdditionalIncome  Cumulative annual income from prior strategies
     * @param  float|null  $priorProbability  Probability after prior strategies (use this as baseline)
     */
    public function calculateStrategyImpact(
        int $userId,
        string $strategyType,
        float $newValue,
        float $priorAdditionalMonthly = 0,
        float $priorAdditionalIncome = 0,
        ?float $priorProbability = null
    ): array {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])
            ->findOrFail($userId);

        // Get base projections
        $projections = $this->projectionService->getProjections($userId);
        $originalBaseProbability = $projections['income_drawdown']['probability'];
        // Use prior probability as baseline if provided (for cumulative strategy calculations)
        $baseProbability = $priorProbability ?? $originalBaseProbability;
        $currentStatus = $this->extractCurrentStatus($projections);

        // Calculate additional monthly contribution based on strategy type
        $additionalMonthly = 0.0;
        $additionalAnnualIncome = 0.0;

        // Calculate new probability and additional contributions based on strategy type
        $newProbability = match ($strategyType) {
            'employer_match' => $this->calculateEmployerMatchImpact($user, $newValue, $projections),
            'increase_contribution' => $this->calculateContributionImpact($user, $newValue, $projections),
            'retirement_age' => $this->calculateRetirementAgeImpact($user, (int) $newValue, $projections),
            'income_target' => $this->calculateIncomeTargetImpact($user, $newValue, $projections),
            default => $baseProbability,
        };

        // Calculate additional monthly for contribution-based strategies
        if ($strategyType === 'employer_match') {
            $additionalMonthly = $this->calculateEmployerMatchAdditionalMonthly($user, $newValue);
            $additionalAnnualIncome = $this->calculateContributionImpactOnIncome(
                $additionalMonthly,
                $currentStatus['years_to_retirement'],
                $currentStatus['expected_return']
            );
        } elseif ($strategyType === 'increase_contribution') {
            $currentMonthly = $this->calculateTotalContributions($user) / 12;
            $additionalMonthly = max(0, $newValue - $currentMonthly);
            $additionalAnnualIncome = $this->calculateContributionImpactOnIncome(
                $additionalMonthly,
                $currentStatus['years_to_retirement'],
                $currentStatus['expected_return']
            );
        }

        $improvement = $newProbability - $baseProbability;

        // Total cumulative values (prior strategies + this strategy)
        $totalAdditionalMonthly = $priorAdditionalMonthly + $additionalMonthly;
        $totalAdditionalIncome = $priorAdditionalIncome + $additionalAnnualIncome;

        // Build updated projection for the chart based on strategy type
        // Use cumulative values so projection shows full impact of all prior strategies + this one
        $projection = match ($strategyType) {
            'income_target' => $this->buildIncomeTargetProjection($currentStatus, $newValue),
            'retirement_age' => $this->buildRetirementAgeProjection(
                $currentStatus,
                (int) $newValue - ($user->target_retirement_age ?? 68),
                $priorAdditionalMonthly  // Pass prior strategies' additional monthly for cumulative projection
            ),
            default => $this->buildStrategyProjection($currentStatus, $totalAdditionalMonthly, $totalAdditionalIncome),
        };

        return [
            'strategy_type' => $strategyType,
            'new_value' => $newValue,
            'base_probability' => $baseProbability,
            'new_probability' => min(self::ON_TRACK_PROBABILITY, $newProbability),
            'probability_improvement' => max(0, $improvement),
            'additional_monthly' => round($additionalMonthly, 2),
            'total_additional_monthly' => round($totalAdditionalMonthly, 2),
            'additional_annual_income' => round($additionalAnnualIncome, 2),
            'total_additional_income' => round($totalAdditionalIncome, 2),
            'on_track' => min(self::ON_TRACK_PROBABILITY, $newProbability) >= self::ON_TRACK_PROBABILITY,
            'projection' => $projection,
        ];
    }

    /**
     * Calculate additional monthly contribution for employer match strategy.
     */
    private function calculateEmployerMatchAdditionalMonthly(User $user, float $newContributionPercent): float
    {
        $additionalMonthly = 0.0;
        $workplacePensions = $user->dcPensions->where('scheme_type', 'workplace');

        foreach ($workplacePensions as $pension) {
            $currentEmployee = (float) ($pension->employee_contribution_percent ?? 0);
            $currentEmployer = (float) ($pension->employer_contribution_percent ?? 0);
            $annualSalary = (float) ($pension->annual_salary ?? $user->annual_employment_income ?? 0);

            $matchLimit = $this->inferEmployerMatchLimit($pension, $currentEmployee, $currentEmployer);
            if ($matchLimit === null) {
                continue;
            }

            $cappedNewPercent = min($newContributionPercent, $matchLimit);
            $additionalPercent = max(0, $cappedNewPercent - $currentEmployee);

            if ($additionalPercent > 0) {
                $employeeAdditional = ($annualSalary * $additionalPercent / 100) / 12;
                $employerMatch = $employeeAdditional;
                $additionalMonthly += $employeeAdditional + $employerMatch;
            }
        }

        return $additionalMonthly;
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
     *
     * @param  array  $affordability  User's affordability data including disposable_income
     * @return array Contains 'strategies' array and 'skipped_for_affordability' bool
     */
    private function checkEmployerMatchStrategies(User $user, array $currentStatus, float $cumulativeAdditionalIncome, array $affordability): array
    {
        $strategies = [];
        $priority = 1;
        $skippedForAffordability = false;

        $workplacePensions = $user->dcPensions->where('scheme_type', 'workplace');

        foreach ($workplacePensions as $pension) {
            $currentEmployee = (float) ($pension->employee_contribution_percent ?? 0);
            $currentEmployer = (float) ($pension->employer_contribution_percent ?? 0);

            // Determine the matching limit using robust inference
            $matchLimit = $this->inferEmployerMatchLimit($pension, $currentEmployee, $currentEmployer);

            // Skip if no valid matching opportunity detected
            if ($matchLimit === null) {
                continue;
            }

            // Only show strategy if there's meaningful room to increase (at least 0.5% gap)
            if ($currentEmployee < $matchLimit && ($matchLimit - $currentEmployee) >= 0.5) {
                $annualSalary = (float) ($pension->annual_salary ?? $user->annual_employment_income ?? 0);

                // Calculate additional contribution to reach match
                $additionalPercent = $matchLimit - $currentEmployee;
                $additionalMonthlyEmployee = ($annualSalary * $additionalPercent / 100) / 12;
                $employerBonus = $additionalMonthlyEmployee; // Employer matches
                $totalAdditionalMonthly = $additionalMonthlyEmployee + $employerBonus;

                // Calculate employee's annual contribution increase (for affordability)
                $additionalAnnualEmployee = $additionalMonthlyEmployee * 12;

                // AFFORDABILITY CHECK: Calculate net cost considering salary sacrifice
                $netCost = $this->calculateNetCostOfContribution($additionalAnnualEmployee, $user);
                $canAfford = $affordability['disposable_income'] >= $netCost;

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

                $strategy = [
                    'type' => 'employer_match',
                    'applicable' => true,
                    'priority' => $priority,
                    'title' => 'Maximise Employer Match',
                    'description' => sprintf(
                        'Your employer matches up to %.1f%% of salary. You\'re currently contributing %.1f%%. Growth projected over %d years to retirement age %d.',
                        $matchLimit,
                        $currentEmployee,
                        $currentStatus['years_to_retirement'],
                        $currentStatus['retirement_age']
                    ),
                    'retirement_age' => $currentStatus['retirement_age'],
                    'years_to_retirement' => $currentStatus['years_to_retirement'],
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
                    'affordability' => [
                        'net_cost_annual' => round($netCost, 2),
                        'disposable_income' => round($affordability['disposable_income'], 2),
                        'can_afford' => $canAfford,
                    ],
                ];

                // Add skip reason and message if not affordable
                if (! $canAfford) {
                    $skippedForAffordability = true;
                    $strategy['skipped_reason'] = 'affordability';
                    $strategy['affordability_message'] = 'While maximising your employer\'s matching contribution would significantly boost your pension, it\'s not currently affordable based on your disposable income. Consider reviewing your monthly expenditure or revisiting this strategy when your financial circumstances change.';
                }

                $strategies[] = $strategy;
                $priority++;
            }
        }

        return [
            'strategies' => $strategies,
            'skipped_for_affordability' => $skippedForAffordability,
        ];
    }

    /**
     * Infer the employer matching limit from pension data.
     *
     * Uses robust logic to handle any data:
     * 1. If employer_matching_limit is set and reasonable (1-50%), use it
     * 2. If missing/invalid, infer from contribution patterns:
     *    - If employer > employee: employee can likely increase to employer's level
     *    - If employer == employee: already at max match
     *    - If employer < employee: not a matching scheme
     *
     * @return float|null The match limit as % of salary, or null if no matching opportunity
     */
    private function inferEmployerMatchLimit(mixed $pension, float $currentEmployee, float $currentEmployer): ?float
    {
        $explicitLimit = $pension->employer_matching_limit ?? null;

        // Case 1: Explicit limit is set and reasonable (1-50% of salary)
        if ($explicitLimit !== null && $explicitLimit > 0 && $explicitLimit <= 50) {
            return (float) $explicitLimit;
        }

        // Case 2: No valid explicit limit - infer from contribution patterns
        // If employer contributes more than employee, employee can likely increase to match
        if ($currentEmployer > $currentEmployee && $currentEmployer <= 50) {
            return $currentEmployer;
        }

        // Case 3: Employee already at or above employer level
        // This suggests they're either at max match, or it's a fixed employer contribution
        if ($currentEmployee >= $currentEmployer) {
            return null; // No matching opportunity
        }

        // Case 4: Employer contribution is 0 or negative - no matching scheme
        if ($currentEmployer <= 0) {
            return null;
        }

        // Default: no valid matching opportunity detected
        return null;
    }

    /**
     * Check for contribution increase opportunity.
     *
     * Enhanced to include:
     * - Relief at source calculation (HMRC adds 20%)
     * - Self-assessment refund for higher/additional rate taxpayers
     * - Refund reinvestment strategy (Pension → ISA → Bond Wrapper → GIA)
     * - Compound projection of refund reinvestment until retirement
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

        // Get user's marginal tax rate for relief calculations
        $marginalRate = $this->getMarginalTaxRate($user);

        // Calculate contribution breakdown with relief at source
        // User's disposable income is what they can pay upfront (net)
        $contributionBreakdown = $this->calculateContributionWithRelief(
            $disposableIncome,
            $remainingAllowance,
            $marginalRate
        );

        // Get the recommended gross contribution (50% of max for conservative approach)
        $recommendedGrossAnnual = $contributionBreakdown['gross_contribution'] * 0.5;
        $maxGrossAnnual = $contributionBreakdown['gross_contribution'];

        // Current total monthly contributions
        $currentMonthly = $this->calculateTotalContributions($user) / 12;

        // Convert to monthly for slider
        $recommendedGrossMonthly = $recommendedGrossAnnual / 12;
        $maxGrossMonthly = $maxGrossAnnual / 12;

        // Calculate realistic impact on retirement income
        $additionalAnnualIncome = $this->calculateContributionImpactOnIncome(
            $recommendedGrossMonthly,
            $currentStatus['years_to_retirement'],
            $currentStatus['expected_return']
        );

        // Calculate new probability including cumulative prior strategies
        $newProbability = $this->calculateNewProbability(
            $currentStatus['projected_income'] + $cumulativeAdditionalIncome,
            $currentStatus['target_income'],
            $additionalAnnualIncome
        );

        // Calculate refund reinvestment strategy for recommended contribution
        $recommendedRefund = 0.0;
        if ($marginalRate >= 0.45) {
            $recommendedRefund = $recommendedGrossAnnual * 0.25;
        } elseif ($marginalRate >= 0.40) {
            $recommendedRefund = $recommendedGrossAnnual * 0.20;
        }

        // Assume pension allowance for next year is same (full year of reinvestment possible)
        $refundReinvestment = $this->calculateRefundReinvestmentStrategy(
            $recommendedRefund,
            $remainingAllowance - $recommendedGrossAnnual, // Remaining after this year's contribution
            (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000)
        );

        // Project compound benefit of reinvestment until retirement
        $yearsToRetirement = $currentStatus['years_to_retirement'];
        // expected_return is stored as percentage (e.g., 5 for 5%), convert to decimal
        $growthRate = ($currentStatus['expected_return'] ?? 5) / 100;
        $compoundProjection = $this->projectCompoundBenefitToRetirement(
            $recommendedGrossAnnual,
            $marginalRate,
            $yearsToRetirement,
            $growthRate
        );

        // Build enhanced description based on tax band
        $retirementAge = $currentStatus['retirement_age'];
        $description = $this->buildContributionDescription(
            $contributionBreakdown,
            $recommendedGrossAnnual,
            $compoundProjection,
            $yearsToRetirement,
            $retirementAge
        );

        return [
            'type' => 'increase_contribution',
            'applicable' => true,
            'priority' => 2,
            'title' => 'Increase Pension Contributions',
            'description' => $description,
            'retirement_age' => $retirementAge,
            'years_to_retirement' => $yearsToRetirement,
            'current_value' => round($currentMonthly, 0),
            'recommended_value' => round($currentMonthly + $recommendedGrossMonthly, 0),
            'slider_config' => [
                'min' => round($currentMonthly, 0),
                'max' => round($currentMonthly + $maxGrossMonthly, 0),
                'step' => 50,
                'unit' => '/month gross',
                'format' => 'currency',
            ],
            'contribution_breakdown' => [
                'gross_contribution' => round($recommendedGrossAnnual, 0),
                'user_pays_upfront' => round($recommendedGrossAnnual * 0.80, 0),
                'hmrc_adds' => round($recommendedGrossAnnual * 0.20, 0),
                'self_assessment_refund' => round($recommendedRefund, 0),
                'effective_annual_cost' => round(($recommendedGrossAnnual * 0.80) - $recommendedRefund, 0),
                'tax_band' => $contributionBreakdown['tax_band'],
            ],
            'refund_reinvestment' => $refundReinvestment,
            'compound_projection' => $compoundProjection,
            'constraints' => [
                'limited_by' => $contributionBreakdown['limited_by'],
                'remaining_allowance' => round($remainingAllowance, 0),
                'disposable_income' => round($disposableIncome, 0),
            ],
            'impact' => [
                'additional_monthly' => round($recommendedGrossMonthly, 2),
                'additional_annual_income' => round($additionalAnnualIncome, 2),
                'new_probability' => round($newProbability, 0),
            ],
        ];
    }

    /**
     * Build user-friendly description for contribution increase strategy.
     */
    private function buildContributionDescription(
        array $breakdown,
        float $recommendedGross,
        array $compoundProjection,
        int $yearsToRetirement,
        int $retirementAge = 65
    ): string {
        $taxBand = $breakdown['tax_band'];
        $effectiveCost = $taxBand === 'additional'
            ? $this->formatCurrency($recommendedGross * 0.55)
            : ($taxBand === 'higher'
                ? $this->formatCurrency($recommendedGross * 0.60)
                : $this->formatCurrency($recommendedGross * 0.80));

        $yearsContext = sprintf(' Growth projected over %d years to age %d.', $yearsToRetirement, $retirementAge);

        if ($taxBand === 'basic' || $taxBand === 'non_taxpayer') {
            return sprintf(
                'Increase your pension contributions. With tax relief, %s/year costs you just %s.%s',
                $this->formatCurrency($recommendedGross),
                $effectiveCost,
                $yearsContext
            );
        }

        // Higher or additional rate taxpayer - emphasise the benefit
        $additionalBenefit = $compoundProjection['with_reinvestment']['additional_benefit'] ?? 0;

        return sprintf(
            'Increase your pension contributions. As a %s rate taxpayer, %s/year costs you just %s after tax relief. Reinvesting your refunds could add %s to your pot.%s',
            $taxBand,
            $this->formatCurrency($recommendedGross),
            $effectiveCost,
            $this->formatCurrency($additionalBenefit),
            $yearsContext
        );
    }

    /**
     * Check for retirement age adjustment strategy.
     */
    private function checkRetirementAgeStrategy(User $user, array $currentStatus, float $cumulativeAdditionalIncome): ?array
    {
        $currentAge = $user->date_of_birth?->age ?? 40;
        $currentRetirementAge = $user->target_retirement_age ?? 68;

        // Minimum is current target, max recommendation is 68, slider max is 75
        $minAge = $currentRetirementAge;
        $maxRecommendedAge = 68;
        $maxSliderAge = 75;

        // Only offer if there's room to adjust
        if ($minAge >= $maxSliderAge) {
            return null;
        }

        $targetIncome = $currentStatus['target_income'] ?? 0;
        $guaranteedIncome = $currentStatus['guaranteed_income'] ?? 0;

        // Cannot calculate if no target income set
        if ($targetIncome <= 0) {
            return null;
        }

        // Find the optimal retirement age by testing each year
        // We want the MINIMUM delay that achieves ~95-100% income coverage
        $recommendedAge = $currentRetirementAge;
        $recommendedYearsDelay = 0;
        $bestIncomeCoverage = 0;
        $cannotAchieveTargetBy68 = false;

        for ($testYears = 1; $testYears <= ($maxRecommendedAge - $currentRetirementAge); $testYears++) {
            // Calculate projected pot at this delayed retirement
            $projectedPot = $this->calculatePotAtDelayedRetirement(
                $currentStatus,
                $testYears,
                $cumulativeAdditionalIncome
            );

            // Calculate sustainable income at this pot
            $sustainableRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);
            $sustainableIncome = $projectedPot * $sustainableRate;
            $totalIncome = $sustainableIncome + $guaranteedIncome;
            $incomeCoverage = $targetIncome > 0 ? ($totalIncome / $targetIncome) * 100 : 0;

            // Find the minimum delay that gets us to at least 95% coverage
            if ($incomeCoverage >= 95 && $recommendedYearsDelay === 0) {
                $recommendedAge = $currentRetirementAge + $testYears;
                $recommendedYearsDelay = $testYears;
                $bestIncomeCoverage = $incomeCoverage;
                break; // Stop at first age that achieves target
            }
        }

        // If we couldn't reach 95% within max recommended age, use max and flag it
        if ($recommendedYearsDelay === 0) {
            $recommendedAge = $maxRecommendedAge;
            $recommendedYearsDelay = $maxRecommendedAge - $currentRetirementAge;
            $cannotAchieveTargetBy68 = true; // Flag for triggering Strategy 4

            // Calculate coverage at max recommended age
            $projectedPotAtMax = $this->calculatePotAtDelayedRetirement(
                $currentStatus,
                $recommendedYearsDelay,
                $cumulativeAdditionalIncome
            );
            $sustainableIncomeAtMax = $projectedPotAtMax * $sustainableRate;
            $totalIncomeAtMax = $sustainableIncomeAtMax + $guaranteedIncome;
            $bestIncomeCoverage = $targetIncome > 0 ? ($totalIncomeAtMax / $targetIncome) * 100 : 0;
        }

        $yearsDelay = $recommendedYearsDelay;

        // Build description with retirement age context
        $yearsToRetirement = max(1, $currentRetirementAge - $currentAge);
        $description = sprintf(
            'Your current retirement age is %d (%d years away). Working longer allows more time for contributions and investment growth.',
            $currentRetirementAge,
            $yearsToRetirement
        );

        // Calculate impact using pot at delayed retirement
        $projectedPot = $this->calculatePotAtDelayedRetirement(
            $currentStatus,
            $yearsDelay,
            $cumulativeAdditionalIncome
        );
        $sustainableRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);
        $sustainableIncome = $projectedPot * $sustainableRate;
        $totalIncome = $sustainableIncome + $guaranteedIncome;

        // Calculate probability directly from income coverage
        $incomeRatio = $targetIncome > 0 ? $totalIncome / $targetIncome : 0;
        $newProbability = min(self::ON_TRACK_PROBABILITY, max(10, round(10 + ($incomeRatio * 85), 0)));

        // Calculate additional income over current situation
        $currentSustainable = $currentStatus['projected_income'];
        $currentTotal = $currentSustainable + $guaranteedIncome + $cumulativeAdditionalIncome;
        $additionalAnnualIncome = $totalIncome - $currentTotal;

        return [
            'type' => 'retirement_age',
            'applicable' => true,
            'priority' => 3,
            'title' => 'Adjust Retirement Age',
            'description' => $description,
            'retirement_age' => $currentRetirementAge,
            'years_to_retirement' => $yearsToRetirement,
            'current_value' => $currentRetirementAge,
            'recommended_value' => $recommendedAge,
            'slider_config' => [
                'min' => $minAge,
                'max' => $maxSliderAge,
                'step' => 1,
                'unit' => ' years',
                'format' => 'age',
            ],
            'constraints' => [
                'max_recommended_age' => $maxRecommendedAge,
                'age_for_95_percent' => $recommendedAge,
            ],
            'impact' => [
                'years_delay' => $yearsDelay,
                'additional_annual_income' => round($additionalAnnualIncome, 2),
                'new_probability' => round($newProbability, 0),
                'sustainable_income_at_68' => round($totalIncome, 2),
            ],
            // Flag for triggering Strategy 4 when target cannot be achieved by age 68
            'cannot_achieve_target_by_68' => $cannotAchieveTargetBy68,
            // Note: projection is added in getStrategies() to ensure consistency with cumulative values
        ];
    }

    /**
     * Check for income target reduction strategy.
     * This strategy is about accepting a LOWER retirement income target.
     *
     * Can be triggered:
     * 1. As final fallback when other strategies are insufficient
     * 2. When Strategy 3 (retirement age) cannot achieve target by age 68
     *
     * @param  float|null  $sustainableIncomeAt68  Sustainable income from Strategy 3 at age 68
     */
    private function checkIncomeTargetStrategy(User $user, array $projections, array $currentStatus, float $cumulativeAdditionalIncome, ?float $sustainableIncomeAt68 = null): ?array
    {
        $targetIncome = $projections['income_drawdown']['target_income'];
        $guaranteedIncome = $projections['income_drawdown']['guaranteed_income']['total'] ?? 0;

        // Use sustainable income at 68 if provided (from Strategy 3), otherwise use current projection
        // This ensures we show the achievable income INCLUDING the retirement age delay benefit
        if ($sustainableIncomeAt68 !== null && $sustainableIncomeAt68 > 0) {
            $totalAchievableIncome = $sustainableIncomeAt68;
            $triggeredByRetirementAge = true;
        } else {
            $originalSustainableIncome = $currentStatus['projected_income'];
            $totalAchievableIncome = $originalSustainableIncome + $guaranteedIncome;
            $triggeredByRetirementAge = false;
        }

        // Minimum is total achievable, max is current target
        $minIncome = $totalAchievableIncome;
        $maxIncome = $targetIncome;

        // Only offer if there's room to reduce (target must be higher than what we can achieve)
        if ($minIncome >= $maxIncome * 0.95) {
            return null;
        }

        // Calculate the gap between target and achievable
        $incomeGap = $targetIncome - $totalAchievableIncome;

        // Recommend reducing to match what's achievable
        $recommendedIncome = $totalAchievableIncome;

        // Calculate new probability with reduced target
        $incomeRatio = $recommendedIncome > 0 ? $totalAchievableIncome / $recommendedIncome : 0;
        $newProbability = min(self::ON_TRACK_PROBABILITY, max(10, round(10 + ($incomeRatio * 85), 0)));

        // Build description showing the gap clearly (green-toned informative message)
        $projectedAge = $triggeredByRetirementAge ? 68 : ($currentStatus['retirement_age'] ?? 68);
        $description = sprintf(
            'Based on your projected pension pot at age %d, you can sustainably withdraw %s/year (using a 4.7%% withdrawal rate with 95%% probability of lasting 30 years). This is %s/year less than your target retirement income of %s/year.',
            $projectedAge,
            $this->formatCurrency($totalAchievableIncome),
            $this->formatCurrency($incomeGap),
            $this->formatCurrency($targetIncome)
        );

        return [
            'type' => 'income_target',
            'applicable' => true,
            'priority' => 4,
            'title' => 'Adjust Retirement Income Target',
            'description' => $description,
            'retirement_age' => $currentStatus['retirement_age'],
            'years_to_retirement' => $currentStatus['years_to_retirement'],
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
                'sustainable_income' => round($totalAchievableIncome - $guaranteedIncome, 2),
                'total_achievable_income' => round($totalAchievableIncome, 2),
            ],
            'impact' => [
                'income_reduction' => round($incomeGap, 2),
                'income_gap' => round($incomeGap, 2),
                'additional_annual_income' => 0,
                'new_probability' => round($newProbability, 0),
            ],
            // Green-toned message style for frontend
            'message_style' => 'informative',
            'triggered_by_retirement_age' => $triggeredByRetirementAge,
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
            $currentEmployer = (float) ($pension->employer_contribution_percent ?? 0);
            $annualSalary = (float) ($pension->annual_salary ?? $user->annual_employment_income ?? 0);

            // Use same inference logic as strategy detection
            $matchLimit = $this->inferEmployerMatchLimit($pension, $currentEmployee, $currentEmployer);
            if ($matchLimit === null) {
                continue;
            }

            // Cap the new contribution at the inferred matching limit
            $cappedNewPercent = min($newContributionPercent, $matchLimit);

            // Additional percentage contribution
            $additionalPercent = $cappedNewPercent - $currentEmployee;
            if ($additionalPercent > 0) {
                // Employee contribution + employer match
                $employeeAdditional = ($annualSalary * $additionalPercent / 100) / 12;
                $employerMatch = $employeeAdditional; // Employer matches
                $additionalMonthly += $employeeAdditional + $employerMatch;
            }
        }

        // Each £100/month additional contribution adds roughly 2% probability
        $improvement = ($additionalMonthly / 100) * 2;

        return min(self::ON_TRACK_PROBABILITY, $baseProbability + $improvement);
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

        return min(self::ON_TRACK_PROBABILITY, $baseProbability + $improvement);
    }

    /**
     * Calculate impact of retirement age change on probability.
     */
    private function calculateRetirementAgeImpact(User $user, int $newAge, array $projections): float
    {
        $currentAge = $projections['income_drawdown']['retirement_age'];
        $yearsDelay = $newAge - $currentAge;

        if ($yearsDelay <= 0) {
            return $projections['income_drawdown']['probability'];
        }

        // Calculate current projected income from pot and guaranteed income
        $startingPot = $projections['income_drawdown']['starting_pot'];
        $withdrawalRate = $projections['income_drawdown']['withdrawal_rate'] / 100;
        $guaranteedIncome = $projections['income_drawdown']['guaranteed_income']['total'] ?? 0;
        $currentIncome = ($startingPot * $withdrawalRate) + $guaranteedIncome;

        $targetIncome = $projections['income_drawdown']['target_income'];

        // Each year delay adds ~10% to projected income (more contributions + growth)
        $additionalIncomePercent = $yearsDelay * 0.10;
        $newIncome = $currentIncome * (1 + $additionalIncomePercent);

        // Use same probability formula as other strategies
        $incomeRatio = $targetIncome > 0 ? $newIncome / $targetIncome : 0;
        $probability = 10 + ($incomeRatio * 85);

        return min(95, max(10, round($probability, 0)));
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

        return min(self::ON_TRACK_PROBABILITY, $baseProbability + $improvement);
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
     * Calculate the net cost of additional pension contributions.
     *
     * First £2,000/year via salary sacrifice has zero cost (saves both tax AND NI).
     * Above £2,000/year via relief at source: cost = contribution × (1 - marginal_tax_rate).
     *
     * Tax Year 2025/26: Salary sacrifice limit is £2,000.
     */
    private function calculateNetCostOfContribution(float $additionalAnnual, User $user): float
    {
        $salarySacrificeLimit = 2000.0;

        // First £2,000 via salary sacrifice - no cost to employee
        $viaReliefAtSource = max(0, $additionalAnnual - $salarySacrificeLimit);

        if ($viaReliefAtSource <= 0) {
            // All contribution is within salary sacrifice limit - zero cost
            return 0.0;
        }

        // Get user's marginal tax rate
        $marginalRate = $this->getMarginalTaxRate($user);

        // Relief at source: employee pays net (after tax relief)
        // Basic rate (20%): cost = contribution × 0.80
        // Higher rate (40%): cost = contribution × 0.60
        // Additional rate (45%): cost = contribution × 0.55
        $netCostReliefAtSource = $viaReliefAtSource * (1 - $marginalRate);

        return $netCostReliefAtSource;
    }

    /**
     * Get user's marginal income tax rate based on gross income.
     *
     * Uses TaxConfigService for tax bands to ensure consistency.
     */
    private function getMarginalTaxRate(User $user): float
    {
        $grossIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0);

        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTax['personal_allowance'];
        $basicLimit = $personalAllowance + $incomeTax['bands'][0]['max']; // £50,270
        $higherLimit = $personalAllowance + $incomeTax['bands'][1]['max']; // £125,140

        if ($grossIncome <= $personalAllowance) {
            return 0.0;
        } elseif ($grossIncome <= $basicLimit) {
            return 0.20;
        } elseif ($grossIncome <= $higherLimit) {
            return 0.40;
        } else {
            return 0.45;
        }
    }

    /**
     * Calculate contribution amounts with relief at source.
     *
     * Relief at Source: User pays net (80%), HMRC adds 20% automatically.
     * Higher/Additional rate taxpayers get additional relief via self-assessment.
     *
     * @param  float  $netAffordable  Maximum net amount user can afford upfront
     * @param  float  $remainingAllowance  Remaining Annual Allowance
     * @param  float  $marginalRate  User's marginal tax rate (0, 0.20, 0.40, or 0.45)
     * @return array Contribution breakdown with gross, net, HMRC addition, and refund
     */
    private function calculateContributionWithRelief(
        float $netAffordable,
        float $remainingAllowance,
        float $marginalRate
    ): array {
        // Convert net affordable to gross (relief at source adds 25% on top)
        // Net × 1.25 = Gross, or Gross = Net ÷ 0.80
        $maxGrossFromAffordability = $netAffordable / 0.80;

        // Cap at remaining Annual Allowance
        $grossContribution = min($maxGrossFromAffordability, $remainingAllowance);

        // User pays 80% upfront
        $userPaysUpfront = $grossContribution * 0.80;

        // HMRC adds 20% automatically (relief at source)
        $hmrcAdds = $grossContribution * 0.20;

        // Self-assessment refund for higher/additional rate taxpayers
        // Higher rate (40%): additional 20% refund
        // Additional rate (45%): additional 25% refund
        $selfAssessmentRefund = 0.0;
        $taxBand = 'basic';

        if ($marginalRate >= 0.45) {
            $selfAssessmentRefund = $grossContribution * 0.25;
            $taxBand = 'additional';
        } elseif ($marginalRate >= 0.40) {
            $selfAssessmentRefund = $grossContribution * 0.20;
            $taxBand = 'higher';
        } elseif ($marginalRate >= 0.20) {
            $taxBand = 'basic';
        } else {
            $taxBand = 'non_taxpayer';
        }

        // Effective annual cost = what user pays minus what they get back
        $effectiveAnnualCost = $userPaysUpfront - $selfAssessmentRefund;

        // Determine binding constraint
        $limitedBy = $maxGrossFromAffordability <= $remainingAllowance
            ? 'affordability'
            : 'annual_allowance';

        return [
            'gross_contribution' => round($grossContribution, 2),
            'user_pays_upfront' => round($userPaysUpfront, 2),
            'hmrc_adds' => round($hmrcAdds, 2),
            'self_assessment_refund' => round($selfAssessmentRefund, 2),
            'effective_annual_cost' => round($effectiveAnnualCost, 2),
            'tax_band' => $taxBand,
            'marginal_rate' => $marginalRate,
            'limited_by' => $limitedBy,
        ];
    }

    /**
     * Calculate refund reinvestment strategy.
     *
     * Priority order: Pension → ISA → Bond Wrapper → GIA
     *
     * @param  float  $refundAmount  The self-assessment refund to reinvest
     * @param  float  $remainingPensionAllowance  Remaining pension Annual Allowance
     * @param  float  $remainingIsaAllowance  Remaining ISA allowance (£20,000 standard)
     * @return array Recommended destination and breakdown
     */
    private function calculateRefundReinvestmentStrategy(
        float $refundAmount,
        float $remainingPensionAllowance,
        ?float $remainingIsaAllowance = null
    ): array {
        $remainingIsaAllowance = $remainingIsaAllowance ?? $this->taxConfig->getISAAllowances()['annual_allowance'];

        if ($refundAmount <= 0) {
            return [
                'refund_amount' => 0,
                'recommended_destination' => null,
                'allocations' => [],
                'fallback_order' => ['pension', 'isa', 'bond_wrapper', 'gia'],
            ];
        }

        $allocations = [];
        $remainingRefund = $refundAmount;
        $recommendedDestination = null;

        // Priority 1: Pension (if allowance available)
        if ($remainingPensionAllowance > 0 && $remainingRefund > 0) {
            $toPension = min($remainingRefund, $remainingPensionAllowance);
            $allocations['pension'] = round($toPension, 2);
            $remainingRefund -= $toPension;

            if ($recommendedDestination === null) {
                $recommendedDestination = 'pension';
            }
        }

        // Priority 2: ISA (if allowance available)
        if ($remainingIsaAllowance > 0 && $remainingRefund > 0) {
            $toIsa = min($remainingRefund, $remainingIsaAllowance);
            $allocations['isa'] = round($toIsa, 2);
            $remainingRefund -= $toIsa;

            if ($recommendedDestination === null) {
                $recommendedDestination = 'isa';
            }
        }

        // Priority 3: Bond Wrapper (no limit)
        if ($remainingRefund > 0) {
            // For simplicity, allocate a reasonable amount to bond wrapper
            // before falling back to GIA (£10k threshold)
            $toBondWrapper = min($remainingRefund, 10000.0);
            $allocations['bond_wrapper'] = round($toBondWrapper, 2);
            $remainingRefund -= $toBondWrapper;

            if ($recommendedDestination === null) {
                $recommendedDestination = 'bond_wrapper';
            }
        }

        // Priority 4: GIA (fallback, no limit)
        if ($remainingRefund > 0) {
            $allocations['gia'] = round($remainingRefund, 2);

            if ($recommendedDestination === null) {
                $recommendedDestination = 'gia';
            }
        }

        // Calculate refund timing (self-assessment refund arrives ~January after tax year end)
        $now = now();
        $currentTaxYearEnd = $now->month >= 4 && $now->day >= 6
            ? $now->year + 1
            : $now->year;
        $refundTiming = sprintf('January %d', $currentTaxYearEnd + 1);

        return [
            'refund_amount' => round($refundAmount, 2),
            'refund_timing' => $refundTiming,
            'recommended_destination' => $recommendedDestination,
            'pension_allowance_available' => $remainingPensionAllowance > 0,
            'isa_allowance_available' => $remainingIsaAllowance > 0,
            'allocations' => $allocations,
            'fallback_order' => ['pension', 'isa', 'bond_wrapper', 'gia'],
        ];
    }

    /**
     * Project compound benefit of refund reinvestment until retirement.
     *
     * For higher/additional rate taxpayers, reinvesting the self-assessment refund
     * back into pension creates a compounding cycle: refund → pension → more refund.
     *
     * @param  float  $annualGrossContribution  Annual gross contribution to pension
     * @param  float  $marginalRate  User's marginal tax rate
     * @param  int  $yearsToRetirement  Years until target retirement
     * @param  float  $growthRate  Expected annual growth rate (default 5%)
     * @return array Projection with yearly breakdown and totals
     */
    private function projectCompoundBenefitToRetirement(
        float $annualGrossContribution,
        float $marginalRate,
        int $yearsToRetirement,
        float $growthRate = 0.05
    ): array {
        if ($yearsToRetirement <= 0 || $annualGrossContribution <= 0) {
            return [
                'years_to_retirement' => $yearsToRetirement,
                'without_reinvestment' => [
                    'total_contributions' => 0,
                    'projected_pot' => 0,
                ],
                'with_reinvestment' => [
                    'total_contributions' => 0,
                    'projected_pot' => 0,
                    'additional_benefit' => 0,
                ],
                'yearly_breakdown' => [],
            ];
        }

        // Calculate refund rate based on marginal tax rate
        // Higher rate (40%): 20% refund, Additional rate (45%): 25% refund
        $refundRate = 0.0;
        if ($marginalRate >= 0.45) {
            $refundRate = 0.25;
        } elseif ($marginalRate >= 0.40) {
            $refundRate = 0.20;
        }

        // Without reinvestment: simple annual contribution with growth
        $withoutReinvestment = [
            'total_contributions' => $annualGrossContribution * $yearsToRetirement,
            'projected_pot' => 0,
        ];

        // Calculate pot without reinvestment using future value of annuity
        for ($year = 1; $year <= $yearsToRetirement; $year++) {
            $yearsToGrow = $yearsToRetirement - $year;
            $futureValue = $annualGrossContribution * pow(1 + $growthRate, $yearsToGrow);
            $withoutReinvestment['projected_pot'] += $futureValue;
        }

        // With reinvestment: refund from each year gets reinvested as pension contribution
        $yearlyBreakdown = [];
        $cumulativeRefundReinvested = 0.0;
        $totalContributionsWithReinvestment = 0.0;
        $projectedPotWithReinvestment = 0.0;

        for ($year = 1; $year <= $yearsToRetirement; $year++) {
            // Reinvest refund from previous year into pension
            // When reinvested into pension, net becomes gross (÷ 0.80)
            $refundReinvestedNet = $cumulativeRefundReinvested;
            $refundReinvestedGross = $refundReinvestedNet > 0
                ? $refundReinvestedNet / 0.80
                : 0.0;

            // Total gross contribution this year = base + reinvested refund
            $totalGrossThisYear = $annualGrossContribution + $refundReinvestedGross;
            $totalContributionsWithReinvestment += $totalGrossThisYear;

            // Calculate refund generated this year for next year's reinvestment
            $refundGeneratedThisYear = $totalGrossThisYear * $refundRate;
            $cumulativeRefundReinvested = $refundGeneratedThisYear;

            // Calculate future value of this year's contribution
            $yearsToGrow = $yearsToRetirement - $year;
            $futureValue = $totalGrossThisYear * pow(1 + $growthRate, $yearsToGrow);
            $projectedPotWithReinvestment += $futureValue;

            $yearlyBreakdown[] = [
                'year' => $year,
                'base_contribution' => round($annualGrossContribution, 0),
                'refund_reinvested_net' => round($refundReinvestedNet, 0),
                'refund_reinvested_gross' => round($refundReinvestedGross, 0),
                'total_gross' => round($totalGrossThisYear, 0),
                'refund_generated' => round($refundGeneratedThisYear, 0),
                'years_to_grow' => $yearsToGrow,
                'future_value' => round($futureValue, 0),
            ];
        }

        $additionalBenefit = $projectedPotWithReinvestment - $withoutReinvestment['projected_pot'];

        return [
            'years_to_retirement' => $yearsToRetirement,
            'growth_rate' => $growthRate,
            'refund_rate' => $refundRate,
            'without_reinvestment' => [
                'total_contributions' => round($withoutReinvestment['total_contributions'], 0),
                'projected_pot' => round($withoutReinvestment['projected_pot'], 0),
            ],
            'with_reinvestment' => [
                'total_contributions' => round($totalContributionsWithReinvestment, 0),
                'projected_pot' => round($projectedPotWithReinvestment, 0),
                'additional_benefit' => round($additionalBenefit, 0),
            ],
            'yearly_breakdown' => $yearlyBreakdown,
        ];
    }

    /**
     * Get current tax year string.
     */
    private function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
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
                    $potWithoutStrategy = $monteCarloData[$mcIndex]['percentile_20'] ?? $currentPot;
                    $displayYear = $monteCarloData[$mcIndex]['year'] ?? ($currentYear + $year);
                } else {
                    // Fall back to last available Monte Carlo value
                    $lastMcIndex = $monteCarloCount - 1;
                    $potWithoutStrategy = $monteCarloData[$lastMcIndex]['percentile_20'] ?? $currentPot;
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

        // Get final values at retirement (last Monte Carlo year = percentile_20_at_retirement)
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];
        $potAtRetirementWith = $lastYear['pot_with_strategy'] ?? 0;
        $potAtRetirementWithout = $lastYear['pot_without_strategy'] ?? 0;

        // Use 4.7% sustainable withdrawal rate (matches RetirementProjectionService constant)
        $sustainableWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);

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
     * Build projection data for income target reduction strategy.
     *
     * For income target strategy, the pot growth stays the same - we're just
     * showing that if you accept a lower target, you're on track.
     */
    private function buildIncomeTargetProjection(array $currentStatus, float $newTargetIncome): array
    {
        $yearsToRetirement = $currentStatus['years_to_retirement'];
        $currentYear = (int) date('Y');
        $currentPot = $currentStatus['current_pot'];

        // Use Monte Carlo data for pot growth (same as without strategy)
        $monteCarloData = $currentStatus['monte_carlo_year_by_year'] ?? [];
        $monteCarloCount = count($monteCarloData);

        $yearByYear = [];
        for ($year = 0; $year <= $yearsToRetirement; $year++) {
            if ($year === 0) {
                $potValue = $currentPot;
                $displayYear = $currentYear;
            } else {
                $mcIndex = $year - 1;
                if ($mcIndex < $monteCarloCount) {
                    $potValue = $monteCarloData[$mcIndex]['percentile_20'] ?? $currentPot;
                    $displayYear = $monteCarloData[$mcIndex]['year'] ?? ($currentYear + $year);
                } else {
                    $lastMcIndex = $monteCarloCount - 1;
                    $potValue = $monteCarloData[$lastMcIndex]['percentile_20'] ?? $currentPot;
                    $displayYear = $currentYear + $year;
                }
            }

            // For income target, both lines are the same (no additional contributions)
            $yearByYear[] = [
                'year' => $displayYear,
                'years_from_now' => $year,
                'pot_with_strategy' => round($potValue, 0),
                'pot_without_strategy' => round($potValue, 0),
            ];
        }

        // Get final pot at retirement
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];
        $potAtRetirement = $lastYear['pot_with_strategy'] ?? 0;

        // Calculate income
        $sustainableWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);
        $sustainableIncome = $potAtRetirement * $sustainableWithdrawalRate;
        $guaranteedIncome = $currentStatus['guaranteed_income'] ?? 0;
        $totalRetirementIncome = $sustainableIncome + $guaranteedIncome;

        $originalTarget = $currentStatus['target_income'];

        return [
            'pot_growth' => $yearByYear,
            'with_strategy' => [
                'pot_at_retirement' => round($potAtRetirement, 0),
                'sustainable_income' => round($sustainableIncome, 0),
                'guaranteed_income' => round($guaranteedIncome, 0),
                'total_retirement_income' => round($totalRetirementIncome, 0),
                // Coverage against NEW (lower) target
                'income_coverage_percent' => $newTargetIncome > 0
                    ? round($totalRetirementIncome / $newTargetIncome * 100, 1)
                    : 0,
            ],
            'without_strategy' => [
                'pot_at_retirement' => round($potAtRetirement, 0),
                'sustainable_income' => round($sustainableIncome, 0),
                'guaranteed_income' => round($guaranteedIncome, 0),
                'total_retirement_income' => round($totalRetirementIncome, 0),
                // Coverage against ORIGINAL target
                'income_coverage_percent' => $originalTarget > 0
                    ? round($totalRetirementIncome / $originalTarget * 100, 1)
                    : 0,
            ],
            'target_income' => round($newTargetIncome, 0),
            'original_target_income' => round($originalTarget, 0),
        ];
    }

    /**
     * Build projection data for retirement age adjustment strategy.
     *
     * For retirement age strategy, we show the pot continuing to grow for additional years.
     * "Without strategy" = pot with prior strategies' contributions (cumulative baseline)
     * "With strategy" = retire at delayed age (more years of growth + contributions)
     *
     * @param  float  $priorAdditionalMonthly  Cumulative monthly contributions from prior strategies
     */
    private function buildRetirementAgeProjection(array $currentStatus, int $yearsDelay, float $priorAdditionalMonthly = 0): array
    {
        $yearsToRetirement = $currentStatus['years_to_retirement'];
        $expectedReturn = $currentStatus['expected_return'] / 100;
        $currentYear = (int) date('Y');
        $currentPot = $currentStatus['current_pot'];

        // Get current monthly contributions to project growth during delay
        $currentMonthlyContribution = $currentStatus['current_monthly_contribution'] ?? 0;

        // Total monthly with prior strategies' contributions
        $totalMonthlyContribution = $currentMonthlyContribution + $priorAdditionalMonthly;

        // Use Monte Carlo data for baseline
        $monteCarloData = $currentStatus['monte_carlo_year_by_year'] ?? [];
        $monteCarloCount = count($monteCarloData);

        $yearByYear = [];
        $monthlyRate = $expectedReturn / 12;

        // Build projection up to original retirement age
        // "Without strategy" = Monte Carlo baseline + prior strategies' additional pot
        for ($year = 0; $year <= $yearsToRetirement; $year++) {
            if ($year === 0) {
                $mcBaseline = $currentPot;
                $displayYear = $currentYear;
            } else {
                $mcIndex = $year - 1;
                if ($mcIndex < $monteCarloCount) {
                    $mcBaseline = $monteCarloData[$mcIndex]['percentile_20'] ?? $currentPot;
                    $displayYear = $monteCarloData[$mcIndex]['year'] ?? ($currentYear + $year);
                } else {
                    $lastMcIndex = max(0, $monteCarloCount - 1);
                    $mcBaseline = $monteCarloData[$lastMcIndex]['percentile_20'] ?? $currentPot;
                    $displayYear = $currentYear + $year;
                }
            }

            // Calculate additional pot from prior strategies' extra contributions
            $additionalPotFromPriorStrategies = 0.0;
            if ($year > 0 && $monthlyRate > 0 && $priorAdditionalMonthly > 0) {
                $months = $year * 12;
                $additionalPotFromPriorStrategies = $priorAdditionalMonthly *
                    ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
            }

            // "Without strategy" = Monte Carlo baseline + prior strategies' pot
            // This represents where we'd be if we took prior strategies but NOT this one
            $potWithoutStrategy = $mcBaseline + $additionalPotFromPriorStrategies;

            // Up to original retirement, "with strategy" is same as "without"
            // (the retirement age delay hasn't kicked in yet)
            $yearByYear[] = [
                'year' => $displayYear,
                'years_from_now' => $year,
                'pot_with_strategy' => round($potWithoutStrategy, 0),
                'pot_without_strategy' => round($potWithoutStrategy, 0),
            ];
        }

        // Get pot at original retirement age (includes prior strategies)
        $potAtOriginalRetirement = $yearByYear[count($yearByYear) - 1]['pot_without_strategy'] ?? $currentPot;

        // Now extend projection for delay years (with strategy continues growing)
        for ($extraYear = 1; $extraYear <= $yearsDelay; $extraYear++) {
            $year = $yearsToRetirement + $extraYear;
            $displayYear = $currentYear + $year;

            // Without strategy: pot stays at original retirement value (no more growth after original retirement)
            $potWithoutStrategy = $potAtOriginalRetirement;

            // With strategy: continued growth with total contributions (original + prior strategies)
            // Use Monte Carlo if available, otherwise project with expected return
            $mcIndex = $year - 1;

            // Calculate additional pot from prior strategies for this year
            $additionalPotFromPriorStrategies = 0.0;
            if ($monthlyRate > 0 && $priorAdditionalMonthly > 0) {
                $months = $year * 12;
                $additionalPotFromPriorStrategies = $priorAdditionalMonthly *
                    ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
            }

            if ($mcIndex < $monteCarloCount) {
                $mcBaseline = $monteCarloData[$mcIndex]['percentile_20'] ?? $potAtOriginalRetirement;
                $potWithStrategy = $mcBaseline + $additionalPotFromPriorStrategies;
            } else {
                // Project forward from last Monte Carlo value + prior strategies' pot
                $lastMcValue = $monteCarloCount > 0
                    ? ($monteCarloData[$monteCarloCount - 1]['percentile_20'] ?? ($potAtOriginalRetirement - $additionalPotFromPriorStrategies))
                    : ($potAtOriginalRetirement - $additionalPotFromPriorStrategies);

                // Add prior strategies' pot at that point
                $lastMcValueWithPrior = $lastMcValue + $additionalPotFromPriorStrategies;

                // Simple projection: previous pot × (1 + return) + total contributions
                $yearsSinceLastMc = $year - $monteCarloCount;
                $potWithStrategy = $lastMcValueWithPrior * pow(1 + $expectedReturn, $yearsSinceLastMc);

                // Add continued contributions (total = original + prior strategies)
                if ($monthlyRate > 0) {
                    $extraMonths = $yearsSinceLastMc * 12;
                    $additionalFromContributions = $totalMonthlyContribution *
                        ((pow(1 + $monthlyRate, $extraMonths) - 1) / $monthlyRate);
                    $potWithStrategy += $additionalFromContributions;
                }
            }

            $yearByYear[] = [
                'year' => $displayYear,
                'years_from_now' => $year,
                'pot_with_strategy' => round($potWithStrategy, 0),
                'pot_without_strategy' => round($potWithoutStrategy, 0),
            ];
        }

        // Final values
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];
        $potAtRetirementWith = $lastYear['pot_with_strategy'] ?? 0;
        $potAtRetirementWithout = $potAtOriginalRetirement;

        $sustainableWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);
        $sustainableIncomeWith = $potAtRetirementWith * $sustainableWithdrawalRate;
        $sustainableIncomeWithout = $potAtRetirementWithout * $sustainableWithdrawalRate;

        $guaranteedIncome = $currentStatus['guaranteed_income'] ?? 0;
        $totalRetirementIncomeWith = $sustainableIncomeWith + $guaranteedIncome;
        $totalRetirementIncomeWithout = $sustainableIncomeWithout + $guaranteedIncome;

        $targetIncome = $currentStatus['target_income'];

        return [
            'pot_growth' => $yearByYear,
            'with_strategy' => [
                'pot_at_retirement' => round($potAtRetirementWith, 0),
                'sustainable_income' => round($sustainableIncomeWith, 0),
                'guaranteed_income' => round($guaranteedIncome, 0),
                'total_retirement_income' => round($totalRetirementIncomeWith, 0),
                'income_coverage_percent' => $targetIncome > 0
                    ? round($totalRetirementIncomeWith / $targetIncome * 100, 1)
                    : 0,
            ],
            'without_strategy' => [
                'pot_at_retirement' => round($potAtRetirementWithout, 0),
                'sustainable_income' => round($sustainableIncomeWithout, 0),
                'guaranteed_income' => round($guaranteedIncome, 0),
                'total_retirement_income' => round($totalRetirementIncomeWithout, 0),
                'income_coverage_percent' => $targetIncome > 0
                    ? round($totalRetirementIncomeWithout / $targetIncome * 100, 1)
                    : 0,
            ],
            'target_income' => round($targetIncome, 0),
        ];
    }

    /**
     * Calculate projected pension pot at a delayed retirement age.
     *
     * Uses Monte Carlo data where available, otherwise projects forward with expected return.
     * Accounts for cumulative contributions from prior strategies.
     *
     * @param  float  $cumulativeAdditionalIncome  Cumulative annual income from prior strategies
     *                                             (used to derive additional monthly contributions)
     */
    private function calculatePotAtDelayedRetirement(
        array $currentStatus,
        int $yearsDelay,
        float $cumulativeAdditionalIncome
    ): float {
        $yearsToRetirement = $currentStatus['years_to_retirement'];
        $expectedReturn = $currentStatus['expected_return'] / 100;
        $currentPot = $currentStatus['current_pot'];
        $currentMonthlyContribution = $currentStatus['current_monthly_contribution'] ?? 0;
        $guaranteedIncome = $currentStatus['guaranteed_income'] ?? 0;

        // Use Monte Carlo data for baseline projections
        $monteCarloData = $currentStatus['monte_carlo_year_by_year'] ?? [];
        $monteCarloCount = count($monteCarloData);

        // Calculate monthly contribution equivalent from prior strategies' income impact
        // Reverse the income calculation to get approximate monthly contributions
        $sustainableRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);
        $priorAdditionalMonthly = 0.0;
        if ($cumulativeAdditionalIncome > 0 && $yearsToRetirement > 0) {
            // Income = pot × sustainableRate, so pot = income / sustainableRate
            // pot = monthly × ((1 + r)^n - 1) / r, so monthly = pot × r / ((1 + r)^n - 1)
            $monthlyRate = $expectedReturn / 12;
            $months = $yearsToRetirement * 12;
            if ($monthlyRate > 0 && $months > 0) {
                $potNeeded = $cumulativeAdditionalIncome / $sustainableRate;
                $priorAdditionalMonthly = $potNeeded * $monthlyRate / (pow(1 + $monthlyRate, $months) - 1);
            }
        }

        $totalMonthlyContribution = $currentMonthlyContribution + $priorAdditionalMonthly;
        $totalYears = $yearsToRetirement + $yearsDelay;

        // Get pot at original retirement from Monte Carlo
        $potAtOriginalRetirement = $currentPot;
        if ($monteCarloCount >= $yearsToRetirement && $yearsToRetirement > 0) {
            $mcIndex = $yearsToRetirement - 1;
            $potAtOriginalRetirement = $monteCarloData[$mcIndex]['percentile_20'] ?? $currentPot;
        }

        // Add pot from prior strategies' additional contributions
        $monthlyRate = $expectedReturn / 12;
        $additionalPotFromPriorStrategies = 0.0;
        if ($monthlyRate > 0 && $priorAdditionalMonthly > 0 && $yearsToRetirement > 0) {
            $months = $yearsToRetirement * 12;
            $additionalPotFromPriorStrategies = $priorAdditionalMonthly *
                ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
        }

        $potWithPriorStrategies = $potAtOriginalRetirement + $additionalPotFromPriorStrategies;

        // If no delay, return pot with prior strategies
        if ($yearsDelay <= 0) {
            return $potWithPriorStrategies;
        }

        // Calculate projected pot at delayed retirement
        // Check if Monte Carlo data extends to the delayed age
        $mcIndexDelayed = $totalYears - 1;

        if ($mcIndexDelayed < $monteCarloCount) {
            // Monte Carlo data available for delayed retirement
            $mcBaseline = $monteCarloData[$mcIndexDelayed]['percentile_20'] ?? $potAtOriginalRetirement;

            // Add prior strategies' pot accumulated to that point
            if ($monthlyRate > 0 && $priorAdditionalMonthly > 0) {
                $months = $totalYears * 12;
                $additionalPotAtDelay = $priorAdditionalMonthly *
                    ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);

                return $mcBaseline + $additionalPotAtDelay;
            }

            return $mcBaseline;
        }

        // Project forward from end of Monte Carlo data
        $lastMcIndex = max(0, $monteCarloCount - 1);
        $lastMcPot = $monteCarloData[$lastMcIndex]['percentile_20'] ?? $potWithPriorStrategies;

        // Add prior strategies' pot at last MC point
        if ($monthlyRate > 0 && $priorAdditionalMonthly > 0 && $monteCarloCount > 0) {
            $months = $monteCarloCount * 12;
            $additionalPotAtLastMc = $priorAdditionalMonthly *
                ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
            $lastMcPot += $additionalPotAtLastMc;
        }

        // Project forward from last MC to delayed retirement
        $yearsToProject = $totalYears - $monteCarloCount;

        // Growth on existing pot
        $projectedPot = $lastMcPot * pow(1 + $expectedReturn, $yearsToProject);

        // Add continued contributions during delay
        if ($monthlyRate > 0 && $yearsToProject > 0) {
            $extraMonths = $yearsToProject * 12;
            $additionalFromContributions = $totalMonthlyContribution *
                ((pow(1 + $monthlyRate, $extraMonths) - 1) / $monthlyRate);
            $projectedPot += $additionalFromContributions;
        }

        return $projectedPot;
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

        // Convert to annual income using sustainable withdrawal rate
        $sustainableRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);

        return $futureValue * $sustainableRate;
    }

    /**
     * Calculate new probability based on additional income.
     *
     * Uses a linear interpolation approach that ensures probability
     * increases proportionally with income improvements.
     */
    private function calculateNewProbability(
        float $currentIncome,
        float $targetIncome,
        float $additionalIncome
    ): float {
        if ($targetIncome <= 0) {
            return 0;
        }

        $newIncome = $currentIncome + $additionalIncome;
        $incomeRatio = $newIncome / $targetIncome;

        // Linear interpolation: 0% coverage = 10% prob, 100% coverage = 95% prob
        // This ensures probability always increases with more income
        $probability = 10 + ($incomeRatio * 85);

        // Cap between 10 and 95
        return min(95, max(10, round($probability, 0)));
    }

    /**
     * Calculate capital position including retirement income planner data.
     *
     * This provides a complete picture of the user's retirement readiness by combining:
     * - Projected pension pot at retirement (80% Monte Carlo confidence)
     * - Other assets (ISAs, bonds, savings) included in retirement planning
     * - Required capital based on target income
     * - Achievable net income from tax-optimised drawdown
     */
    private function calculateCapitalPosition(int $userId, array $projections): array
    {
        // Get required capital data
        $requiredCapitalData = $this->requiredCapitalCalculator->calculate($userId);
        $requiredCapital = (float) ($requiredCapitalData['required_capital_at_retirement'] ?? 0);
        $targetIncome = (float) ($requiredCapitalData['required_income'] ?? 0);

        // Get projected pension pot (80% confidence from Monte Carlo)
        $projectedPensionPot = (float) ($projections['pension_pot_projection']['percentile_20_at_retirement'] ?? 0);

        // Get retirement income planner data for achievable net income
        $retirementIncomeData = $this->retirementIncomeService->getRetirementIncomeConfig($userId);
        $achievableNetIncome = (float) ($retirementIncomeData['tax_breakdown']['net_income'] ?? 0);
        $totalFunds = (float) ($retirementIncomeData['total_funds'] ?? 0);

        // Calculate other assets (total funds minus pension pot)
        // These are ISAs, bonds, savings that are included in retirement planning
        $otherAssets = max(0, $totalFunds - $projectedPensionPot);

        // Get list of other assets included (for display)
        $includedOtherAssets = $this->getIncludedOtherAssets($userId);

        // Calculate gap or surplus
        $totalProjectedCapital = $projectedPensionPot + $otherAssets;
        $gapToTarget = $requiredCapital - $totalProjectedCapital;

        // Calculate progress percentage
        $progressPercentage = $requiredCapital > 0
            ? round(($totalProjectedCapital / $requiredCapital) * 100, 1)
            : 0;

        // Determine if "on track" based on capital position (>= 80% of required)
        $capitalOnTrack = $progressPercentage >= 80;

        return [
            'projected_pension_pot' => round($projectedPensionPot, 2),
            'other_assets_total' => round($otherAssets, 2),
            'included_assets' => $includedOtherAssets,
            'total_projected_capital' => round($totalProjectedCapital, 2),
            'required_capital' => round($requiredCapital, 2),
            'target_income' => round($targetIncome, 2),
            'gap_to_target' => round($gapToTarget, 2),
            'is_surplus' => $gapToTarget < 0,
            'progress_percentage' => $progressPercentage,
            'capital_on_track' => $capitalOnTrack,
            'achievable_net_income' => round($achievableNetIncome, 2),
            'income_meets_target' => $achievableNetIncome >= $targetIncome,
        ];
    }

    /**
     * Get list of other assets (non-pension) included in retirement planning.
     */
    private function getIncludedOtherAssets(int $userId): array
    {
        $assets = [];

        // Get investment accounts included in retirement (ISAs, bonds, GIAs)
        $investmentAccounts = InvestmentAccount::where('user_id', $userId)
            ->where('include_in_retirement', true)
            ->whereNotIn('account_type', ['vct', 'eis', 'private_company', 'crowdfunding', 'saye', 'csop', 'emi', 'unapproved_options', 'rsu', 'other'])
            ->get();

        foreach ($investmentAccounts as $account) {
            $value = (float) ($account->current_value ?? 0);
            if ($value > 0) {
                $assets[] = [
                    'type' => $account->account_type,
                    'name' => $account->account_name ?? $account->provider ?? ucfirst($account->account_type),
                    'value' => round($value, 2),
                ];
            }
        }

        // Get savings accounts (cash)
        $savingsAccounts = SavingsAccount::where('user_id', $userId)->get();
        foreach ($savingsAccounts as $account) {
            $value = (float) ($account->current_balance ?? 0);
            if ($value > 0) {
                $assets[] = [
                    'type' => 'cash',
                    'name' => $account->account_name ?? $account->name ?? 'Savings',
                    'value' => round($value, 2),
                ];
            }
        }

        return $assets;
    }
}
