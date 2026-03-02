<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\RetirementActionDefinition;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Traits\FormatsCurrency;

/**
 * Evaluates retirement action definitions against user data
 * to produce configurable, database-driven recommendations.
 */
class RetirementActionDefinitionService
{
    use FormatsCurrency;

    public function __construct(
        private readonly ContributionOptimizer $optimizer
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
            if ($pension->scheme_type !== 'workplace') {
                continue;
            }

            $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);

            if ($employeePercent >= $threshold) {
                continue;
            }

            $additionalPercent = $threshold - $employeePercent;
            $vars = [
                'additional_percent' => number_format($additionalPercent, 1),
                'scheme_name' => $pension->scheme_name ?: 'pension',
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
            ];
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
            $annualContrib = $this->calculateAnnualContribution($pension);

            if ($annualContrib > 0 || (float) $pension->current_fund_value <= 0) {
                continue;
            }

            $vars = [
                'scheme_name' => $pension->scheme_name ?: 'pension',
            ];

            $results[] = [
                'priority' => $priority,
                'category' => $definition->category,
                'title' => $definition->renderTitle($vars),
                'description' => $definition->renderDescription($vars),
                'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
                'impact' => ucfirst($definition->priority),
                'scope' => 'account',
                'account_id' => $pension->id,
                'account_name' => $pension->scheme_name,
            ];
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
        if (! $profile) {
            return [];
        }

        $incomeGap = $analysisData['summary']['income_gap'] ?? 0;

        if ($incomeGap <= 0) {
            return [];
        }

        // Available headroom = remaining annual allowance + carry forward from prior 3 years
        $remainingAllowance = (float) ($analysisData['annual_allowance']['remaining_allowance'] ?? 0);
        $carryForward = (float) ($analysisData['annual_allowance']['carry_forward_available'] ?? 0);
        $availableHeadroom = $remainingAllowance + $carryForward;

        if ($availableHeadroom <= 0) {
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
        if (! $profile) {
            return [];
        }

        $optimization = $this->optimizer->optimizeContributions($profile, $dcPensions);

        // Find the tax_relief recommendation from the optimizer
        $taxRec = collect($optimization['recommendations'])->firstWhere('type', 'tax_relief');

        if (! $taxRec) {
            return [];
        }

        $vars = [
            'tax_saving' => '£'.number_format($taxRec['potential_saving'] ?? 0, 2),
            'additional_contribution' => '£'.number_format(($taxRec['potential_saving'] ?? 0) / 0.4, 2),
        ];

        return [[
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'potential_saving' => $taxRec['potential_saving'] ?? 0,
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
        if (! ($analysisData['annual_allowance']['has_excess'] ?? false)) {
            return [];
        }

        $excess = $analysisData['annual_allowance']['excess_contributions'] ?? 0;
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
        $userId = $analysisData['profile']['user_id'];
        $statePension = StatePension::where('user_id', $userId)->first();

        if (! $statePension || ! $profile) {
            return [];
        }

        if ($statePension->ni_years_completed >= $statePension->ni_years_required) {
            return [];
        }

        $yearsShort = $statePension->ni_years_required - $statePension->ni_years_completed;
        $yearsUntilSPA = max(0, ($statePension->state_pension_age ?? 67) - ($profile->current_age ?? 0));
        $willReachNaturally = ($statePension->ni_years_completed + $yearsUntilSPA) >= $statePension->ni_years_required;

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
        $targetIncome = $analysisData['summary']['target_retirement_income'] ?? 0;
        $incomeGap = $analysisData['summary']['income_gap'] ?? 0;
        $retirementAge = $analysisData['summary']['target_retirement_age'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 0.10);
        $maxSuggestedAge = (int) ($config['max_suggested_age'] ?? 70);
        $ageIncrease = (int) ($config['age_increase'] ?? 3);

        if ($targetIncome <= 0 || $incomeGap <= ($targetIncome * $threshold) || $retirementAge <= 0) {
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
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $effectiveContribution = $monthlyContribution + $monthlyPensionContribution;
        $required = $goal['required_monthly_contribution'] ?? 0;

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
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $effectiveContribution = $monthlyContribution + $monthlyPensionContribution;

        // Skip if no effective contribution (caught by no-contribution check)
        if ($effectiveContribution <= 0) {
            return null;
        }

        $required = $goal['required_monthly_contribution'] ?? 0;

        // If pension contributions bring the effective contribution up to the required
        // amount, treat the goal as on-track regardless of the goal record's is_on_track
        if ($required > 0 && $effectiveContribution >= $required) {
            return null;
        }

        // Also skip if the goal itself reports on-track
        if ($goal['is_on_track'] ?? true) {
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
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress are below thresholds.
     */
    private function evaluateGoalDeadline(RetirementActionDefinition $definition, array $goal, array $config): ?array
    {
        // Only triggers for goals that are otherwise on-track (not caught by off-track check)
        if (! ($goal['is_on_track'] ?? true)) {
            return null;
        }

        $monthsRemaining = $goal['months_remaining'] ?? 0;
        $progress = $goal['progress_percentage'] ?? 0;
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);

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
