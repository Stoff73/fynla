<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\RetirementActionDefinition;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

/**
 * Evaluates retirement action definitions against user data
 * to produce configurable, database-driven recommendations.
 */
class RetirementActionDefinitionService
{
    use FormatsCurrency;

    public function __construct(
        private readonly ContributionOptimizer $optimizer,
        private readonly TaxConfigService $taxConfig,
        private readonly SalarySacrificeAnalyzer $salarySacrificeAnalyzer,
        private readonly DecumulationPlanner $decumulationPlanner
    ) {}

    /**
     * Evaluate all enabled agent-sourced action definitions against analysis data.
     *
     * @return array Recommendations in the standard format consumed by structureActions()
     */
    public function evaluateAgentActions(array $analysisData): array
    {
        $definitions = RetirementActionDefinition::getEnabledBySource('agent');
        $recommendations = [];
        $priority = 1;

        if (empty($analysisData['profile'])) {
            return [];
        }

        $userId = $analysisData['profile']['user_id'];
        $profile = RetirementProfile::find($analysisData['profile']['id']);
        $dcPensions = DCPension::where('user_id', $userId)->get();

        foreach ($definitions as $definition) {
            $results = $this->evaluateAgentTrigger($definition, $analysisData, $profile, $dcPensions, $priority);

            foreach ($results as $rec) {
                $recommendations[] = $rec;
                $priority++;
            }
        }

        $recommendations = $this->resolveContributionConflicts($recommendations, $dcPensions);

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => ($r['priority'] ?? 999) <= 2)),
        ];
    }

    /**
     * Resolve conflicts between start_contributions and contribution_increase actions.
     *
     * These are mutually exclusive: if the user has any pension with active contributions,
     * show "increase contributions" only. If they have no contributing pensions at all,
     * show "start contributions" only.
     */
    private function resolveContributionConflicts(array $recommendations, $dcPensions): array
    {
        $hasStart = collect($recommendations)->contains(fn ($r) => ($r['category'] ?? '') === 'Start_contributions');
        $hasIncrease = collect($recommendations)->contains(fn ($r) => ($r['category'] ?? '') === 'Contribution_increase');

        if (! $hasStart || ! $hasIncrease) {
            return $recommendations;
        }

        $hasContributingPension = $dcPensions->contains(fn ($p) => $this->calculateAnnualContribution($p) > 0);

        $removeCategory = $hasContributingPension ? 'Start_contributions' : 'Contribution_increase';

        return array_values(array_filter(
            $recommendations,
            fn ($r) => ($r['category'] ?? '') !== $removeCategory
        ));
    }

    /**
     * Evaluate all enabled goal-sourced action definitions against linked goals.
     *
     * Accepts DC pensions so that pension contributions can be factored into
     * goal on-track evaluations (the goal system itself doesn't track pension
     * contributions, so without this the actions would incorrectly flag goals
     * as off-track when the user is making significant pension contributions).
     *
     * @param  \Illuminate\Support\Collection|null  $dcPensions  User's DC pensions
     * @return array Recommendations in the standard format consumed by structureActions()
     */
    public function evaluateGoalActions(array $linkedGoals, $dcPensions = null): array
    {
        $definitions = RetirementActionDefinition::getEnabledBySource('goal');
        $recommendations = [];

        $monthlyPensionContribution = $dcPensions
            ? $this->calculateTotalMonthlyPensionContributions($dcPensions)
            : 0.0;

        foreach ($linkedGoals as $goal) {
            $progress = $goal['progress_percentage'] ?? 0;
            $isComplete = $progress >= 100;

            if ($isComplete) {
                continue;
            }

            foreach ($definitions as $definition) {
                $rec = $this->evaluateGoalTrigger($definition, $goal, $monthlyPensionContribution);

                if ($rec !== null) {
                    $recommendations[] = $rec;
                }
            }
        }

        return $recommendations;
    }

    /**
     * Calculate total monthly pension contributions across all DC pensions.
     */
    private function calculateTotalMonthlyPensionContributions($dcPensions): float
    {
        $total = 0.0;

        foreach ($dcPensions as $pension) {
            $annual = $this->calculateAnnualContribution($pension);
            $total += $annual / 12;
        }

        return $total;
    }

    /**
     * Look up the what_if_impact_type for a given action category.
     */
    public function getWhatIfImpactType(string $category): string
    {
        $definition = RetirementActionDefinition::where('category', $category)->first();

        return $definition?->what_if_impact_type ?? 'default';
    }

    /**
     * Evaluate a single agent-sourced trigger against analysis data.
     *
     * @return array List of recommendations (may be empty or contain multiple for per-pension triggers)
     */
    private function evaluateAgentTrigger(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        $dcPensions,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'employee_contribution_percent_below' => $this->evaluateEmployerMatch($definition, $dcPensions, $config, $priority),
            'zero_contribution_with_fund_value' => $this->evaluateZeroContribution($definition, $dcPensions, $priority),
            'income_gap_positive_and_additional_contribution_required' => $this->evaluateContributionIncrease($definition, $analysisData, $profile, $dcPensions, $priority),
            'higher_rate_taxpayer_below_allowance' => $this->evaluateTaxRelief($definition, $profile, $dcPensions, $config, $priority),
            'annual_allowance_has_excess' => $this->evaluateAnnualAllowance($definition, $analysisData, $priority),
            'ni_years_wont_reach_required_by_spa' => $this->evaluateNIGaps($definition, $analysisData, $profile, $priority),
            'income_gap_exceeds_percentage_of_target' => $this->evaluateRetirementAge($definition, $analysisData, $config, $priority),
            'workplace_pension_no_salary_sacrifice' => $this->evaluateSalarySacrificeAvailable($definition, $analysisData, $dcPensions, $priority),
            'salary_sacrifice_below_proxy_floor' => $this->evaluateSalarySacrificeFloor($definition, $analysisData, $dcPensions, $priority),
            'auto_enrolment_below_minimum_total' => $this->evaluateAutoEnrolmentMinimum($definition, $analysisData, $dcPensions, $priority),
            'smoker_or_health_condition_enhanced_annuity' => $this->evaluateEnhancedAnnuity($definition, $analysisData, $priority),
            'no_care_costs_entered_over_50' => $this->evaluateCareCostsNotModelled($definition, $analysisData, $profile, $config, $priority),
            'no_state_pension_forecast' => $this->evaluateStatePensionNoForecast($definition, $analysisData, $priority),
            'within_years_of_retirement' => $this->evaluateApproachingDecumulation($definition, $analysisData, $config, $priority),
            'multiple_dc_pensions' => $this->evaluatePensionConsolidation($definition, $dcPensions, $config, $priority),
            default => [],
        };
    }

    /**
     * Employer match: triggers for each workplace pension where employee % < threshold.
     */
    private function evaluateEmployerMatch(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 5.0);
        $results = [];

        foreach ($dcPensions as $pension) {
            $trace = [];

            $isWorkplace = $pension->scheme_type === 'workplace';
            $trace[] = [
                'question' => 'Is this a workplace pension?',
                'data_field' => 'scheme_type',
                'data_value' => $pension->scheme_type ?? 'not set',
                'threshold' => 'workplace',
                'passed' => $isWorkplace,
                'explanation' => $isWorkplace
                    ? 'This is a workplace pension, so employer match rules apply.'
                    : 'This is not a workplace pension, so employer match does not apply.',
            ];

            if (! $isWorkplace) {
                continue;
            }

            $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
            $belowThreshold = $employeePercent < $threshold;
            $trace[] = [
                'question' => 'Is the employee contribution below the recommended threshold?',
                'data_field' => 'employee_contribution_percent',
                'data_value' => round($employeePercent, 1).'%',
                'threshold' => round($threshold, 1).'%',
                'passed' => $belowThreshold,
                'explanation' => $belowThreshold
                    ? 'Employee contribution of '.round($employeePercent, 1).'% is below the '.round($threshold, 1).'% threshold — additional contributions could unlock more employer matching.'
                    : 'Employee contribution meets or exceeds the threshold.',
            ];

            if (! $belowThreshold) {
                continue;
            }

            $additionalPercent = $threshold - $employeePercent;
            $vars = [
                'additional_percent' => number_format($additionalPercent, 1),
                'scheme_name' => $pension->scheme_name ?: 'pension',
            ];

            $rec = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];

            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Zero contribution: triggers for pensions with fund value but no contributions.
     */
    private function evaluateZeroContribution(
        RetirementActionDefinition $definition,
        $dcPensions,
        int $priority
    ): array {
        $results = [];

        foreach ($dcPensions as $pension) {
            $trace = [];

            $annualContrib = $this->calculateAnnualContribution($pension);
            $fundValue = (float) $pension->current_fund_value;

            $hasNoContribution = $annualContrib <= 0;
            $trace[] = [
                'question' => 'Are there any active contributions to this pension?',
                'data_field' => 'annual_contribution',
                'data_value' => '£'.number_format($annualContrib, 0),
                'threshold' => '£0',
                'passed' => $hasNoContribution,
                'explanation' => $hasNoContribution
                    ? 'No contributions are being made to this pension.'
                    : 'This pension has active contributions of £'.number_format($annualContrib, 0).' per year.',
            ];

            $hasFundValue = $fundValue > 0;
            $trace[] = [
                'question' => 'Does this pension have an existing fund value?',
                'data_field' => 'current_fund_value',
                'data_value' => '£'.number_format($fundValue, 0),
                'threshold' => 'Greater than £0',
                'passed' => $hasFundValue,
                'explanation' => $hasFundValue
                    ? 'The pension has a fund value of £'.number_format($fundValue, 0).', which could benefit from additional contributions.'
                    : 'No existing fund value — this pension may be dormant.',
            ];

            if ($annualContrib > 0 || $fundValue <= 0) {
                continue;
            }

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'pension',
            ];

            $rec = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];

            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Contribution increase: triggers when there's an income gap and the user
     * has available annual allowance headroom (including carry forward from
     * the previous three tax years).
     */
    private function evaluateContributionIncrease(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        $dcPensions,
        int $priority
    ): array {
        $trace = [];

        if (! $profile) {
            return [];
        }

        $incomeGap = $analysisData['summary']['income_gap'] ?? 0;
        $hasIncomeGap = $incomeGap > 0;
        $trace[] = [
            'question' => 'Is there a shortfall between projected and target retirement income?',
            'data_field' => 'income_gap',
            'data_value' => '£'.number_format($incomeGap, 0),
            'threshold' => 'Greater than £0',
            'passed' => $hasIncomeGap,
            'explanation' => $hasIncomeGap
                ? 'There is a projected income gap of £'.number_format($incomeGap, 0).' per year in retirement.'
                : 'Projected retirement income meets or exceeds the target.',
        ];

        if (! $hasIncomeGap) {
            return [];
        }

        // Available headroom = remaining annual allowance + carry forward from prior 3 years
        $remainingAllowance = (float) ($analysisData['annual_allowance']['remaining_allowance'] ?? 0);
        $carryForward = (float) ($analysisData['annual_allowance']['carry_forward_available'] ?? 0);
        $availableHeadroom = $remainingAllowance + $carryForward;

        $hasHeadroom = $availableHeadroom > 0;
        $trace[] = [
            'question' => 'Is there available annual allowance headroom for additional contributions?',
            'data_field' => 'available_headroom',
            'data_value' => '£'.number_format($availableHeadroom, 0),
            'threshold' => 'Greater than £0',
            'passed' => $hasHeadroom,
            'explanation' => $hasHeadroom
                ? 'There is £'.number_format($availableHeadroom, 0).' of annual allowance headroom available (including carry forward).'
                : 'No annual allowance headroom available for additional contributions.',
        ];

        if (! $hasHeadroom) {
            return [];
        }

        $vars = [
            'monthly_amount' => '£'.number_format($availableHeadroom / 12, 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'available_annual_headroom' => round($availableHeadroom, 2),
            'available_monthly_headroom' => round($availableHeadroom / 12, 2),
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Tax relief: triggers for higher-rate taxpayers with capacity below threshold.
     */
    private function evaluateTaxRelief(
        RetirementActionDefinition $definition,
        ?RetirementProfile $profile,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $trace = [];

        if (! $profile) {
            return [];
        }

        $optimization = $this->optimizer->optimizeContributions($profile, $dcPensions);

        // Find the tax_relief recommendation from the optimizer
        $taxRec = collect($optimization['recommendations'])->firstWhere('type', 'tax_relief');

        $hasTaxRec = $taxRec !== null;
        $potentialSaving = $taxRec['potential_saving'] ?? 0;
        $trace[] = [
            'question' => 'Is there an opportunity for higher-rate tax relief on pension contributions?',
            'data_field' => 'tax_relief_recommendation',
            'data_value' => $hasTaxRec ? '£'.number_format($potentialSaving, 0).' potential saving' : 'Not available',
            'threshold' => 'Tax relief opportunity exists',
            'passed' => $hasTaxRec,
            'explanation' => $hasTaxRec
                ? 'The contribution optimiser identified a potential tax saving of £'.number_format($potentialSaving, 0).' through higher-rate relief.'
                : 'No higher-rate tax relief opportunity was identified by the contribution optimiser.',
        ];

        if (! $hasTaxRec) {
            return [];
        }

        $vars = [
            'tax_saving' => '£'.number_format($potentialSaving, 2),
            'additional_contribution' => '£'.number_format($potentialSaving / 0.4, 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'potential_saving' => $potentialSaving,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Annual allowance exceeded: triggers when has_excess is true.
     */
    private function evaluateAnnualAllowance(
        RetirementActionDefinition $definition,
        array $analysisData,
        int $priority
    ): array {
        $trace = [];

        $hasExcess = $analysisData['annual_allowance']['has_excess'] ?? false;
        $excess = $analysisData['annual_allowance']['excess_contributions'] ?? 0;

        $trace[] = [
            'question' => 'Have pension contributions exceeded the annual allowance?',
            'data_field' => 'has_excess',
            'data_value' => $hasExcess ? 'Yes — £'.number_format($excess, 0).' over' : 'No',
            'threshold' => 'Contributions within annual allowance',
            'passed' => $hasExcess,
            'explanation' => $hasExcess
                ? 'Contributions exceed the annual allowance by £'.number_format($excess, 0).', which may result in a tax charge.'
                : 'Contributions are within the annual allowance — no tax charge applies.',
        ];

        if (! $hasExcess) {
            return [];
        }

        $vars = [
            'excess_amount' => '£'.number_format($excess, 2),
        ];

        return [[
            'priority' => 1, // Always high priority for tax charges
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Consult with a financial adviser to minimise tax charges.',
            'impact' => 'High',
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * NI gaps: triggers when NI years won't reach requirement by state pension age.
     */
    private function evaluateNIGaps(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $statePension = StatePension::where('user_id', $userId)->first();

        if (! $statePension || ! $profile) {
            return [];
        }

        $niCompleted = $statePension->ni_years_completed;
        $niRequired = $statePension->ni_years_required;
        $isShort = $niCompleted < $niRequired;

        $trace[] = [
            'question' => 'Are the completed National Insurance years below the required amount?',
            'data_field' => 'ni_years_completed',
            'data_value' => $niCompleted.' years completed',
            'threshold' => $niRequired.' years required',
            'passed' => $isShort,
            'explanation' => $isShort
                ? 'Only '.$niCompleted.' of '.$niRequired.' required National Insurance years have been completed.'
                : 'National Insurance record meets the requirement of '.$niRequired.' years.',
        ];

        if (! $isShort) {
            return [];
        }

        $yearsShort = $niRequired - $niCompleted;
        $yearsUntilSPA = max(0, ($statePension->state_pension_age ?? 67) - ($profile->current_age ?? 0));
        $willReachNaturally = ($niCompleted + $yearsUntilSPA) >= $niRequired;

        $trace[] = [
            'question' => 'Will the shortfall be filled naturally before State Pension age?',
            'data_field' => 'years_until_spa',
            'data_value' => $yearsUntilSPA.' years until State Pension age',
            'threshold' => $yearsShort.' years short',
            'passed' => ! $willReachNaturally,
            'explanation' => $willReachNaturally
                ? 'With '.$yearsUntilSPA.' years until State Pension age, the gap of '.$yearsShort.' years will close naturally.'
                : 'With only '.$yearsUntilSPA.' years until State Pension age, the gap of '.$yearsShort.' years will not close without voluntary contributions.',
        ];

        if ($willReachNaturally) {
            return [];
        }

        $vars = [
            'years_short' => (string) $yearsShort,
            'years_until_spa' => (string) $yearsUntilSPA,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Check your NI record and consider making voluntary contributions if cost-effective.',
            'impact' => 'High',
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Retirement age adjustment: triggers when income gap exceeds threshold % of target.
     */
    private function evaluateRetirementAge(
        RetirementActionDefinition $definition,
        array $analysisData,
        array $config,
        int $priority
    ): array {
        $trace = [];

        $targetIncome = $analysisData['summary']['target_retirement_income'] ?? 0;
        $incomeGap = $analysisData['summary']['income_gap'] ?? 0;
        $retirementAge = $analysisData['summary']['target_retirement_age'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 0.10);
        $maxSuggestedAge = (int) ($config['max_suggested_age'] ?? 70);
        $ageIncrease = (int) ($config['age_increase'] ?? 3);

        $hasTargetIncome = $targetIncome > 0;
        $trace[] = [
            'question' => 'Has a target retirement income been set?',
            'data_field' => 'target_retirement_income',
            'data_value' => '£'.number_format($targetIncome, 0),
            'threshold' => 'Greater than £0',
            'passed' => $hasTargetIncome,
            'explanation' => $hasTargetIncome
                ? 'Target retirement income is £'.number_format($targetIncome, 0).' per year.'
                : 'No target retirement income has been set.',
        ];

        $gapThresholdAmount = $targetIncome * $threshold;
        $gapExceedsThreshold = $hasTargetIncome && $incomeGap > $gapThresholdAmount && $retirementAge > 0;
        $trace[] = [
            'question' => 'Does the income gap exceed '.round($threshold * 100, 0).'% of the target income?',
            'data_field' => 'income_gap',
            'data_value' => '£'.number_format($incomeGap, 0),
            'threshold' => '£'.number_format($gapThresholdAmount, 0).' ('.round($threshold * 100, 0).'% of target)',
            'passed' => $gapExceedsThreshold,
            'explanation' => $gapExceedsThreshold
                ? 'The income gap of £'.number_format($incomeGap, 0).' exceeds '.round($threshold * 100, 0).'% of the target — delaying retirement could help close this gap.'
                : 'The income gap is within acceptable limits relative to the target income.',
        ];

        if ($targetIncome <= 0 || $incomeGap <= $gapThresholdAmount || $retirementAge <= 0) {
            return [];
        }

        $suggestedAge = min($retirementAge + $ageIncrease, $maxSuggestedAge);

        $vars = [
            'suggested_age' => (string) $suggestedAge,
            'current_age' => (string) $retirementAge,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? sprintf('Review scenarios for retiring at %d.', $suggestedAge),
            'impact' => 'High',
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Salary sacrifice available: triggers for employed users with workplace pensions.
     */
    private function evaluateSalarySacrificeAvailable(
        RetirementActionDefinition $definition,
        array $analysisData,
        $dcPensions,
        int $priority
    ): array {
        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);

        if (! $user || $user->employment_status === 'self_employed') {
            return [];
        }

        $results = [];

        foreach ($dcPensions as $pension) {
            $trace = [];

            $isWorkplace = $pension->scheme_type === 'workplace';
            $trace[] = [
                'question' => 'Is this a workplace pension?',
                'data_field' => 'scheme_type',
                'data_value' => $pension->scheme_type ?? 'not set',
                'threshold' => 'workplace',
                'passed' => $isWorkplace,
                'explanation' => $isWorkplace
                    ? 'This is a workplace pension — salary sacrifice may be available.'
                    : 'Salary sacrifice is only available for workplace pensions.',
            ];

            if (! $isWorkplace) {
                continue;
            }

            $analysis = $this->salarySacrificeAnalyzer->analyzeForPension($user, $pension);

            $isAvailable = $analysis['is_available'] && $analysis['employee_ni_saving'] > 0;
            $trace[] = [
                'question' => 'Is salary sacrifice available with a meaningful National Insurance saving?',
                'data_field' => 'employee_ni_saving',
                'data_value' => $analysis['is_available']
                    ? '£'.number_format($analysis['employee_ni_saving'], 0).' annual saving'
                    : 'Not available',
                'threshold' => 'Available with saving greater than £0',
                'passed' => $isAvailable,
                'explanation' => $isAvailable
                    ? 'Salary sacrifice could save £'.number_format($analysis['employee_ni_saving'], 0).' in employee National Insurance contributions.'
                    : 'No salary sacrifice opportunity identified for this pension.',
            ];

            if (! $isAvailable) {
                continue;
            }

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'workplace pension',
                'employee_ni_saving' => '£'.number_format($analysis['employee_ni_saving'], 2),
                'employer_ni_saving' => '£'.number_format($analysis['employer_ni_saving'], 2),
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'Review salary sacrifice options with your employer.',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];
        }

        return $results;
    }

    /**
     * Salary sacrifice floor warning: triggers when sacrifice would drop below proxy floor.
     */
    private function evaluateSalarySacrificeFloor(
        RetirementActionDefinition $definition,
        array $analysisData,
        $dcPensions,
        int $priority
    ): array {
        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);

        if (! $user || $user->employment_status === 'self_employed') {
            return [];
        }

        $salary = (float) ($user->annual_employment_income ?? 0);
        if ($salary <= 0) {
            return [];
        }

        $proxyFloor = (float) $this->taxConfig->get('pension.salary_sacrifice.conservative_proxy_floor', 10000);
        $results = [];

        foreach ($dcPensions as $pension) {
            $trace = [];

            $isWorkplace = $pension->scheme_type === 'workplace';
            $trace[] = [
                'question' => 'Is this a workplace pension?',
                'data_field' => 'scheme_type',
                'data_value' => $pension->scheme_type ?? 'not set',
                'threshold' => 'workplace',
                'passed' => $isWorkplace,
                'explanation' => $isWorkplace
                    ? 'This is a workplace pension — salary sacrifice floor check applies.'
                    : 'Salary sacrifice floor check only applies to workplace pensions.',
            ];

            if (! $isWorkplace) {
                continue;
            }

            $analysis = $this->salarySacrificeAnalyzer->analyzeForPension($user, $pension);

            $isAvailable = $analysis['is_available'];
            $trace[] = [
                'question' => 'Is salary sacrifice available for this pension?',
                'data_field' => 'is_available',
                'data_value' => $isAvailable ? 'Yes' : 'No',
                'threshold' => 'Available',
                'passed' => $isAvailable,
                'explanation' => $isAvailable
                    ? 'Salary sacrifice is available for this workplace pension.'
                    : 'Salary sacrifice is not available for this pension.',
            ];

            if (! $isAvailable) {
                continue;
            }

            $postSacrifice = $analysis['post_sacrifice_salary'];
            $belowFloor = $postSacrifice < $proxyFloor;
            $trace[] = [
                'question' => 'Would the post-sacrifice salary fall below the safety floor?',
                'data_field' => 'post_sacrifice_salary',
                'data_value' => '£'.number_format($postSacrifice, 0),
                'threshold' => '£'.number_format($proxyFloor, 0),
                'passed' => $belowFloor,
                'explanation' => $belowFloor
                    ? 'Post-sacrifice salary of £'.number_format($postSacrifice, 0).' would fall below the £'.number_format($proxyFloor, 0).' safety floor, which could affect benefits entitlement.'
                    : 'Post-sacrifice salary remains above the safety floor.',
            ];

            if (! $belowFloor) {
                continue;
            }

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'workplace pension',
                'post_sacrifice_salary' => '£'.number_format($postSacrifice, 2),
                'proxy_floor' => '£'.number_format($proxyFloor, 0),
            ];

            $results[] = [
                'priority' => 1, // Always critical
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'Review your salary sacrifice amount.',
                'impact' => 'High',
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
                'decision_trace' => $trace,
            ];
        }

        return $results;
    }

    /**
     * Auto-enrolment below minimum: triggers when total contributions are below 8%.
     */
    private function evaluateAutoEnrolmentMinimum(
        RetirementActionDefinition $definition,
        array $analysisData,
        $dcPensions,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $user = User::find($userId);

        if (! $user) {
            return [];
        }

        $compliance = $this->optimizer->checkAutoEnrolmentCompliance($user, $dcPensions);

        $isEligible = $compliance['eligible'];
        $trace[] = [
            'question' => 'Is the user eligible for auto-enrolment?',
            'data_field' => 'eligible',
            'data_value' => $isEligible ? 'Yes' : 'No',
            'threshold' => 'Eligible',
            'passed' => $isEligible,
            'explanation' => $isEligible
                ? 'The user meets the auto-enrolment eligibility criteria.'
                : 'The user is not eligible for auto-enrolment.',
        ];

        if (! $isEligible) {
            return [];
        }

        $meetsMinimum = $compliance['meets_minimum_total'];
        $totalPercent = $compliance['total_contribution_percent'] ?? 0;
        $trace[] = [
            'question' => 'Do total contributions meet the auto-enrolment minimum of 8%?',
            'data_field' => 'total_contribution_percent',
            'data_value' => round($totalPercent, 1).'%',
            'threshold' => '8%',
            'passed' => ! $meetsMinimum,
            'explanation' => $meetsMinimum
                ? 'Total contributions of '.round($totalPercent, 1).'% meet the 8% auto-enrolment minimum.'
                : 'Total contributions of '.round($totalPercent, 1).'% are below the 8% auto-enrolment minimum.',
        ];

        if ($meetsMinimum) {
            return [];
        }

        $vars = [
            'total_percent' => number_format($compliance['total_contribution_percent'], 1),
            'shortfall_annual' => '£'.number_format($compliance['shortfall_annual'], 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Review your pension contribution levels.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Enhanced annuity eligible: triggers when smoker or health condition qualifies.
     */
    private function evaluateEnhancedAnnuity(
        RetirementActionDefinition $definition,
        array $analysisData,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $user = User::with('protectionProfile')->find($userId);

        if (! $user) {
            return [];
        }

        $eligibility = $this->decumulationPlanner->assessEnhancedAnnuityEligibility($user);

        $isEligible = $eligibility['is_eligible'];
        $trace[] = [
            'question' => 'Does the user qualify for an enhanced annuity due to health or lifestyle factors?',
            'data_field' => 'enhanced_annuity_eligibility',
            'data_value' => $isEligible ? 'Eligible — '.($eligibility['reason'] ?? 'qualifying factor identified') : 'Not eligible',
            'threshold' => 'Smoker or health condition present',
            'passed' => $isEligible,
            'explanation' => $isEligible
                ? 'Enhanced annuity rates may be available due to: '.($eligibility['reason'] ?? 'qualifying health or lifestyle factors').'.'
                : 'No qualifying factors for enhanced annuity rates were identified.',
        ];

        if (! $isEligible) {
            return [];
        }

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle(),
            'description' => $definition->renderDescription(),
            'action' => $definition->renderAction() ?? 'Request enhanced annuity quotes when approaching retirement.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'enhanced_annuity_reason' => $eligibility['reason'],
            'enhancement_factor' => $eligibility['enhancement_factor'],
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Care costs not modelled: triggers when user is over threshold age with no care costs.
     */
    private function evaluateCareCostsNotModelled(
        RetirementActionDefinition $definition,
        array $analysisData,
        ?RetirementProfile $profile,
        array $config,
        int $priority
    ): array {
        $trace = [];

        if (! $profile) {
            return [];
        }

        $ageThreshold = (int) ($config['age_threshold'] ?? 50);
        $currentAge = $profile->current_age ?? 0;
        $careCostAnnual = (float) ($profile->care_cost_annual ?? 0);

        $isOverThreshold = $currentAge >= $ageThreshold;
        $trace[] = [
            'question' => 'Is the user aged '.$ageThreshold.' or over?',
            'data_field' => 'current_age',
            'data_value' => $currentAge.' years old',
            'threshold' => $ageThreshold.' years',
            'passed' => $isOverThreshold,
            'explanation' => $isOverThreshold
                ? 'At age '.$currentAge.', care costs should be factored into retirement planning.'
                : 'At age '.$currentAge.', care cost planning is not yet a priority.',
        ];

        $noCareCosts = $careCostAnnual <= 0;
        $trace[] = [
            'question' => 'Has the user entered any care cost assumptions?',
            'data_field' => 'care_cost_annual',
            'data_value' => '£'.number_format($careCostAnnual, 0),
            'threshold' => 'Greater than £0',
            'passed' => $noCareCosts,
            'explanation' => $noCareCosts
                ? 'No care cost assumptions have been entered — this could lead to an underestimate of retirement funding needs.'
                : 'Care costs of £'.number_format($careCostAnnual, 0).' per year have been included in the plan.',
        ];

        if (! $isOverThreshold || ! $noCareCosts) {
            return [];
        }

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle(),
            'description' => $definition->renderDescription(),
            'action' => $definition->renderAction() ?? 'Add care cost assumptions to your retirement profile.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * State Pension no forecast: triggers when no State Pension forecast entered.
     */
    private function evaluateStatePensionNoForecast(
        RetirementActionDefinition $definition,
        array $analysisData,
        int $priority
    ): array {
        $trace = [];

        $userId = $analysisData['profile']['user_id'];
        $statePension = StatePension::where('user_id', $userId)->first();

        $forecastAmount = $statePension ? (float) ($statePension->state_pension_forecast_annual ?? 0) : 0;
        $hasForecast = $statePension && $forecastAmount > 0;

        $trace[] = [
            'question' => 'Has the user entered a State Pension forecast?',
            'data_field' => 'state_pension_forecast_annual',
            'data_value' => $hasForecast ? '£'.number_format($forecastAmount, 0).' per year' : 'Not entered',
            'threshold' => 'Forecast entered',
            'passed' => ! $hasForecast,
            'explanation' => $hasForecast
                ? 'A State Pension forecast of £'.number_format($forecastAmount, 0).' per year has been entered.'
                : 'No State Pension forecast has been entered — retirement projections may be less accurate without this.',
        ];

        // If user has a forecast, no trigger
        if ($hasForecast) {
            return [];
        }

        $fullStatePension = $this->taxConfig->get('pension.state_pension.full_new_state_pension', 11502);

        $vars = [
            'full_state_pension' => '£'.number_format((float) $fullStatePension, 0),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Request your State Pension forecast from gov.uk.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Approaching decumulation: triggers when within configurable years of retirement.
     */
    private function evaluateApproachingDecumulation(
        RetirementActionDefinition $definition,
        array $analysisData,
        array $config,
        int $priority
    ): array {
        $trace = [];

        $yearsToRetirement = $analysisData['summary']['years_to_retirement'] ?? 999;
        $yearsThreshold = (int) ($config['years_threshold'] ?? 10);

        $withinThreshold = $yearsToRetirement <= $yearsThreshold;
        $trace[] = [
            'question' => 'Is the user within '.$yearsThreshold.' years of their target retirement age?',
            'data_field' => 'years_to_retirement',
            'data_value' => $yearsToRetirement.' years',
            'threshold' => $yearsThreshold.' years or fewer',
            'passed' => $withinThreshold,
            'explanation' => $withinThreshold
                ? 'With '.$yearsToRetirement.' years until retirement, it is time to plan a decumulation strategy.'
                : 'Retirement is '.$yearsToRetirement.' years away — decumulation planning is not yet urgent.',
        ];

        $isPositive = $yearsToRetirement > 0;
        $trace[] = [
            'question' => 'Is the user still pre-retirement?',
            'data_field' => 'years_to_retirement',
            'data_value' => $yearsToRetirement.' years',
            'threshold' => 'Greater than 0',
            'passed' => $isPositive,
            'explanation' => $isPositive
                ? 'The user has not yet reached their target retirement age.'
                : 'The user has already reached or passed their target retirement age.',
        ];

        if ($yearsToRetirement > $yearsThreshold || $yearsToRetirement <= 0) {
            return [];
        }

        $vars = [
            'years_to_retirement' => (string) $yearsToRetirement,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Review your decumulation strategy.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Pension consolidation opportunity: triggers when user has 3+ DC pensions.
     */
    private function evaluatePensionConsolidation(
        RetirementActionDefinition $definition,
        $dcPensions,
        array $config,
        int $priority
    ): array {
        $trace = [];

        $minPensionCount = (int) ($config['min_pension_count'] ?? 3);
        $pensionCount = $dcPensions->count();

        $meetsThreshold = $pensionCount >= $minPensionCount;
        $trace[] = [
            'question' => 'Does the user have '.$minPensionCount.' or more defined contribution pensions?',
            'data_field' => 'dc_pension_count',
            'data_value' => $pensionCount.' pensions',
            'threshold' => $minPensionCount.' or more',
            'passed' => $meetsThreshold,
            'explanation' => $meetsThreshold
                ? 'The user has '.$pensionCount.' defined contribution pensions — consolidation could reduce fees and simplify management.'
                : 'The user has '.$pensionCount.' defined contribution pensions, below the consolidation threshold.',
        ];

        if (! $meetsThreshold) {
            return [];
        }

        $vars = [
            'pension_count' => (string) $pensionCount,
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Compare fees and features before consolidating.',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ]];
    }

    /**
     * Evaluate a single goal-sourced trigger against a goal.
     */
    private function evaluateGoalTrigger(RetirementActionDefinition $definition, array $goal, float $monthlyPensionContribution = 0): ?array
    {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'linked_goal_no_monthly_contribution' => $this->evaluateGoalNoContribution($definition, $goal, $monthlyPensionContribution),
            'linked_goal_off_track' => $this->evaluateGoalOffTrack($definition, $goal, $monthlyPensionContribution),
            'goal_months_remaining_below_and_progress_below' => $this->evaluateGoalDeadline($definition, $goal, $config),
            default => null,
        };
    }

    /**
     * Goal no contribution: triggers when monthly contribution is zero but required > 0.
     *
     * Pension contributions count as effective contributions for retirement goals,
     * so if the user has pension contributions, this won't fire even if the goal's
     * own monthly_contribution field is zero.
     */
    private function evaluateGoalNoContribution(RetirementActionDefinition $definition, array $goal, float $monthlyPensionContribution = 0): ?array
    {
        $trace = [];

        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $effectiveContribution = $monthlyContribution + $monthlyPensionContribution;
        $required = $goal['required_monthly_contribution'] ?? 0;

        $hasNoContribution = $effectiveContribution <= 0;
        $trace[] = [
            'question' => 'Are there any contributions towards this goal (including pension contributions)?',
            'data_field' => 'effective_contribution',
            'data_value' => '£'.number_format($effectiveContribution, 0).' per month',
            'threshold' => 'Greater than £0',
            'passed' => $hasNoContribution,
            'explanation' => $hasNoContribution
                ? 'No contributions are being made towards this goal.'
                : 'Effective contributions of £'.number_format($effectiveContribution, 0).' per month are being made.',
        ];

        $hasRequirement = $required > 0;
        $trace[] = [
            'question' => 'Is a monthly contribution required to meet this goal?',
            'data_field' => 'required_monthly_contribution',
            'data_value' => '£'.number_format($required, 0).' per month',
            'threshold' => 'Greater than £0',
            'passed' => $hasRequirement,
            'explanation' => $hasRequirement
                ? '£'.number_format($required, 0).' per month is needed to stay on track for this goal.'
                : 'No monthly contribution is required for this goal.',
        ];

        if ($effectiveContribution > 0 || $required <= 0) {
            return null;
        }

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'required_monthly' => $this->formatCurrency($required),
            'target_amount' => $this->formatCurrency($goal['target_amount'] ?? 0),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal off track: triggers when goal is_on_track is false.
     *
     * Pension contributions are factored in: if the effective monthly contribution
     * (goal contribution + pension contributions) meets or exceeds the required
     * amount, the goal is treated as on-track even if the goal record itself
     * says otherwise (because the goal system doesn't track pension contributions).
     */
    private function evaluateGoalOffTrack(RetirementActionDefinition $definition, array $goal, float $monthlyPensionContribution = 0): ?array
    {
        $trace = [];

        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $effectiveContribution = $monthlyContribution + $monthlyPensionContribution;

        $hasContribution = $effectiveContribution > 0;
        $trace[] = [
            'question' => 'Are there any effective contributions towards this goal?',
            'data_field' => 'effective_contribution',
            'data_value' => '£'.number_format($effectiveContribution, 0).' per month',
            'threshold' => 'Greater than £0',
            'passed' => $hasContribution,
            'explanation' => $hasContribution
                ? 'Effective contributions of £'.number_format($effectiveContribution, 0).' per month are being made (including pension contributions).'
                : 'No effective contributions — this case is handled by the no-contribution check.',
        ];

        // Skip if no effective contribution (caught by no-contribution check)
        if (! $hasContribution) {
            return null;
        }

        $required = $goal['required_monthly_contribution'] ?? 0;

        $meetsRequired = $required > 0 && $effectiveContribution >= $required;
        $trace[] = [
            'question' => 'Do effective contributions meet the required monthly amount?',
            'data_field' => 'effective_contribution',
            'data_value' => '£'.number_format($effectiveContribution, 0).' per month',
            'threshold' => '£'.number_format($required, 0).' per month required',
            'passed' => ! $meetsRequired,
            'explanation' => $meetsRequired
                ? 'Effective contributions of £'.number_format($effectiveContribution, 0).' meet the required £'.number_format($required, 0).' — goal is effectively on track.'
                : 'Effective contributions of £'.number_format($effectiveContribution, 0).' fall short of the required £'.number_format($required, 0).'.',
        ];

        // If pension contributions bring the effective contribution up to the required
        // amount, treat the goal as on-track regardless of the goal record's is_on_track
        if ($meetsRequired) {
            return null;
        }

        $isOffTrack = ! ($goal['is_on_track'] ?? true);
        $trace[] = [
            'question' => 'Is the goal reported as off track?',
            'data_field' => 'is_on_track',
            'data_value' => ($goal['is_on_track'] ?? true) ? 'On track' : 'Off track',
            'threshold' => 'Off track',
            'passed' => $isOffTrack,
            'explanation' => $isOffTrack
                ? 'The goal is currently off track and needs attention.'
                : 'The goal is reported as on track.',
        ];

        // Also skip if the goal itself reports on-track
        if (! $isOffTrack) {
            return null;
        }

        $shortfall = max(0, $required - $effectiveContribution);
        $progress = $goal['progress_percentage'] ?? 0;

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'progress' => number_format($progress, 0),
            'shortfall' => $this->formatCurrency($shortfall),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress are below thresholds.
     */
    private function evaluateGoalDeadline(RetirementActionDefinition $definition, array $goal, array $config): ?array
    {
        $trace = [];

        $isOnTrack = $goal['is_on_track'] ?? true;
        $trace[] = [
            'question' => 'Is the goal currently reported as on track?',
            'data_field' => 'is_on_track',
            'data_value' => $isOnTrack ? 'On track' : 'Off track',
            'threshold' => 'On track',
            'passed' => $isOnTrack,
            'explanation' => $isOnTrack
                ? 'The goal is on track — checking whether the deadline is approaching with low progress.'
                : 'The goal is already off track — this is handled by the off-track check instead.',
        ];

        // Only triggers for goals that are otherwise on-track (not caught by off-track check)
        if (! $isOnTrack) {
            return null;
        }

        $monthsRemaining = $goal['months_remaining'] ?? 0;
        $progress = $goal['progress_percentage'] ?? 0;
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);

        $deadlineApproaching = $monthsRemaining <= $monthsThreshold;
        $trace[] = [
            'question' => 'Is the goal deadline approaching (within '.$monthsThreshold.' months)?',
            'data_field' => 'months_remaining',
            'data_value' => $monthsRemaining.' months',
            'threshold' => $monthsThreshold.' months or fewer',
            'passed' => $deadlineApproaching,
            'explanation' => $deadlineApproaching
                ? 'Only '.$monthsRemaining.' months remain until the goal deadline.'
                : 'The deadline is '.$monthsRemaining.' months away — not yet urgent.',
        ];

        $progressBelowThreshold = $progress < $progressThreshold;
        $trace[] = [
            'question' => 'Is progress below '.round($progressThreshold, 0).'%?',
            'data_field' => 'progress_percentage',
            'data_value' => round($progress, 1).'%',
            'threshold' => round($progressThreshold, 0).'%',
            'passed' => $progressBelowThreshold,
            'explanation' => $progressBelowThreshold
                ? 'Progress of '.round($progress, 1).'% is below the '.round($progressThreshold, 0).'% threshold with the deadline approaching.'
                : 'Progress of '.round($progress, 1).'% is on track relative to the deadline.',
        ];

        if ($monthsRemaining > $monthsThreshold || $progress >= $progressThreshold) {
            return null;
        }

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'progress' => number_format($progress, 0),
            'months_remaining' => (string) $monthsRemaining,
            'target_amount' => $this->formatCurrency($goal['target_amount'] ?? 0),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Calculate annual contribution for a single pension.
     */
    private function calculateAnnualContribution(DCPension $pension): float
    {
        $monthly = (float) ($pension->monthly_contribution_amount ?? 0);

        if ($monthly > 0) {
            return $monthly * 12;
        }

        $salary = (float) ($pension->annual_salary ?? 0);
        $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
        $employerPct = (float) ($pension->employer_contribution_percent ?? 0);

        if ($salary > 0 && ($employeePct + $employerPct) > 0) {
            return $salary * ($employeePct + $employerPct) / 100;
        }

        return 0;
    }
}
