<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\RetirementAgent;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Retirement\PensionProjector;
use App\Services\Retirement\RetirementActionDefinitionService;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

class RetirementPlanService extends BasePlanService
{
    public function __construct(
        private readonly RetirementAgent $retirementAgent,
        private readonly PensionProjector $projector,
        private readonly TaxConfigService $taxConfig,
        private readonly PlanConfigService $planConfig,
        private readonly DisposableIncomeAccessor $incomeAccessor,
        private readonly RetirementActionDefinitionService $actionDefinitionService
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        $analysis = $this->retirementAgent->analyze($userId);
        $data = $analysis['data'] ?? [];

        if (! ($analysis['success'] ?? false)) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'retirement', $completeness),
                'completeness_warning' => $this->buildCompletenessWarning($completeness),
                'executive_summary' => $this->buildEmptyExecutiveSummary(),
                'current_situation' => [],
                'actions' => [],
                'pension_projections' => [],
                'what_if' => [],
                'conclusion' => $this->generateDynamicConclusion([], [], 'retirement'),
                'linked_goals' => [],
                'unlinked_goals' => [],
                'error' => $analysis['message'] ?? 'Unable to generate retirement analysis.',
            ];
        }

        $currentSituation = $this->buildCurrentSituation($data, $userId);
        $recommendations = $this->getRecommendations($userId, $data);
        $dcPensions = DCPension::where('user_id', $userId)->get();
        $goals = $this->getGoalsForPlan($userId, 'retirement');
        $goalRecommendations = $this->actionDefinitionService->evaluateGoalActions($goals['linked'], $dcPensions);
        $allRecs = array_merge($goalRecommendations, $recommendations);
        ['actions' => $actions, 'enabledActions' => $enabledActions] = $this->prepareActions($allRecs, 'retirement', $options);

        // Use years_to_retirement from analysis (computed from live date_of_birth, not stale stored age)
        $yearsToRetirement = max(0, (int) ($data['summary']['years_to_retirement'] ?? 0));
        $pensionProjections = $this->buildPensionGrowthProjections($user, $actions, $dcPensions, $yearsToRetirement);

        $whatIf = $this->buildWhatIfData($user, $data, $currentSituation, $enabledActions, $yearsToRetirement);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'retirement');

        return [
            'metadata' => $this->buildPlanMetadata($user, 'retirement', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $data, $userId, $goals, $actions),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'pension_projections' => $pensionProjections,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
            'linked_goals' => $goals['linked'],
            'unlinked_goals' => $goals['unlinked'],
        ];
    }

    public function getRecommendations(int $userId, ?array $preComputedData = null): array
    {
        $data = $preComputedData;

        if ($data === null) {
            $analysis = $this->retirementAgent->analyze($userId);
            $data = $analysis['data'] ?? [];
        }

        if (empty($data)) {
            return [];
        }

        $result = $this->retirementAgent->generateRecommendations($data);

        return $result['data']['recommendations'] ?? $result['recommendations'] ?? [];
    }

    public function checkDataCompleteness(int $userId): array
    {
        $missing = [];

        $hasProfile = RetirementProfile::where('user_id', $userId)->exists();
        if (! $hasProfile) {
            $missing[] = [
                'field' => 'retirement_profile',
                'label' => 'Retirement profile',
                'description' => 'Set your target retirement age and income goals.',
                'link' => '/retirement',
            ];
        }

        $hasPension = DCPension::where('user_id', $userId)->exists() ||
                      DBPension::where('user_id', $userId)->exists();
        if (! $hasPension) {
            $missing[] = [
                'field' => 'pensions',
                'label' => 'Pension details',
                'description' => 'Add your workplace, personal, or defined benefit pensions.',
                'link' => '/retirement',
            ];
        }

        $user = User::find($userId);
        $hasIncome = $user && (
            ($user->annual_employment_income ?? 0) > 0 ||
            ($user->annual_self_employment_income ?? 0) > 0
        );
        if (! $hasIncome) {
            $missing[] = [
                'field' => 'income',
                'label' => 'Income details',
                'description' => 'Add your income to calculate contribution capacity and tax relief.',
                'link' => '/profile',
            ];
        }

        $hasTargetIncome = $hasProfile && RetirementProfile::where('user_id', $userId)
            ->where('target_retirement_income', '>', 0)
            ->exists();
        if (! $hasTargetIncome && $hasProfile) {
            $missing[] = [
                'field' => 'target_income',
                'label' => 'Target retirement income',
                'description' => 'Set your desired annual income in retirement.',
                'link' => '/retirement',
            ];
        }

        $total = 4;
        $present = $total - count($missing);

        return [
            'percentage' => round(($present / $total) * 100),
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    private function buildExecutiveSummary(User $user, array $data, int $userId, array $goals = [], array $actions = []): array
    {
        $firstName = $this->getUserFirstName($user);
        $summary = $data['summary'] ?? [];
        $targetIncome = $summary['target_retirement_income'] ?? 0;
        $incomeGap = $summary['income_gap'] ?? 0;
        $retirementAge = $summary['target_retirement_age'] ?? 0;

        // Monthly target after tax (approximate: annual target / 12)
        $monthlyTarget = $targetIncome > 0 ? round($targetIncome / 12) : 0;

        // Introduction sentence
        $introduction = sprintf(
            'This plan aims to show you how you can achieve retirement at age %d with %s per month after tax, so you can enjoy your retirement.',
            $retirementAge,
            $this->formatCurrency($monthlyTarget)
        );

        // Goals summary for the table
        $allGoals = array_merge($goals['linked'] ?? [], $goals['unlinked'] ?? []);
        $goalsSummary = [];
        foreach ($allGoals as $goal) {
            $goalsSummary[] = [
                'name' => $goal['name'] ?? 'Unnamed goal',
                'target' => $goal['target_amount'] ?? 0,
                'progress' => $goal['progress_percentage'] ?? 0,
                'on_track' => $goal['is_on_track'] ?? false,
            ];
        }

        // Actions summary for the table (top priority actions, max 5)
        $actionsSummary = [];
        $count = 0;
        foreach ($actions as $action) {
            if ($count >= 5) {
                break;
            }
            $actionsSummary[] = [
                'title' => $action['title'] ?? 'Recommendation',
                'priority' => $action['priority'] ?? 'medium',
            ];
            $count++;
        }
        $totalActions = count($actions);

        // Closing statement
        $closing = $incomeGap <= 0
            ? 'Your current pension arrangements are projected to meet your retirement income target. The details below confirm your position and highlight opportunities to strengthen it further.'
            : 'The solutions and recommendations outlined below are achievable steps that can bring you closer to your desired retirement income.';

        return [
            'opening' => 'Thank you for using Fynla. Here is your personalised Retirement Plan based on your pensions and retirement goals.',
            'greeting' => "Dear {$firstName},",
            'introduction' => $introduction,
            'goals_summary' => $goalsSummary,
            'actions_summary' => $actionsSummary,
            'total_actions' => $totalActions,
            'closing' => $closing,
            'on_track' => $incomeGap <= 0,
        ];
    }

    private function buildEmptyExecutiveSummary(): array
    {
        return [
            'greeting' => 'Dear User,',
            'introduction' => 'Set up your retirement profile and add your pensions to receive a personalised retirement plan.',
            'goals_summary' => [],
            'actions_summary' => [],
            'total_actions' => 0,
            'closing' => '',
            'on_track' => null,
        ];
    }

    private function buildCurrentSituation(array $data, int $userId): array
    {
        $summary = $data['summary'] ?? [];
        $breakdown = $data['breakdown'] ?? [];
        $allowance = $data['annual_allowance'] ?? [];
        $incomeProjection = $data['income_projection'] ?? [];

        $dcPensions = DCPension::where('user_id', $userId)->get();
        $dbPensions = DBPension::where('user_id', $userId)->get();
        $statePension = StatePension::where('user_id', $userId)->first();

        return [
            'summary' => $summary,
            'dc_pensions' => $dcPensions->map(function ($pension) {
                return [
                    'id' => $pension->id,
                    'scheme_name' => $pension->scheme_name,
                    'provider' => $pension->provider,
                    'current_value' => $this->roundToPenny((float) $pension->current_fund_value),
                    'monthly_contribution' => $this->roundToPenny($this->calculateMonthlyEmployeeContribution($pension)),
                    'employer_contribution' => $this->roundToPenny($this->calculateMonthlyEmployerContribution($pension)),
                    'pension_type' => $pension->pension_type,
                ];
            })->toArray(),
            'db_pensions' => $dbPensions->map(function ($pension) {
                return [
                    'id' => $pension->id,
                    'scheme_name' => $pension->scheme_name,
                    'projected_annual_pension' => $this->roundToPenny((float) ($pension->projected_annual_pension ?? 0)),
                    'normal_retirement_age' => $pension->normal_retirement_age ?? null,
                ];
            })->toArray(),
            'state_pension' => $statePension ? [
                'weekly_amount' => $this->roundToPenny(round((float) ($statePension->state_pension_forecast_annual ?? 0) / 52, 2)),
                'annual_amount' => $this->roundToPenny((float) ($statePension->state_pension_forecast_annual ?? 0)),
                'ni_years' => $statePension->ni_years_completed ?? 0,
                'state_pension_age' => $statePension->state_pension_age ?? null,
            ] : null,
            'income_projection' => $incomeProjection,
            'annual_allowance' => $allowance,
            'breakdown' => $breakdown,
        ];
    }

    private function buildWhatIfData(User $user, array $data, array $currentSituation, array $enabledActions, int $yearsToRetirement = 0): array
    {
        $summary = $data['summary'] ?? [];
        $projectedIncome = $summary['projected_retirement_income'] ?? 0;
        $targetIncome = $summary['target_retirement_income'] ?? 0;
        $incomeGap = max(0, $summary['income_gap'] ?? 0);
        $currentDcValue = $summary['current_dc_value'] ?? 0;
        $projectedDcValueAtRetirement = $summary['total_dc_value'] ?? 0;
        if ($yearsToRetirement === 0) {
            $yearsToRetirement = $summary['years_to_retirement'] ?? 0;
        }

        // Use disposable income via DistributionAccount instead of hardcoded amounts
        $monthlyDisposable = $this->incomeAccessor->getMonthlyForUser($user);
        $budget = new DistributionAccount($monthlyDisposable);

        $additionalContribution = 0;
        $incomeImprovement = 0;

        foreach ($enabledActions as $action) {
            $impactType = $this->actionDefinitionService->getWhatIfImpactType($action['category'] ?? '');

            match ($impactType) {
                'contribution' => (function () use ($action, $budget, $monthlyDisposable, $yearsToRetirement, &$additionalContribution, &$incomeImprovement) {
                    $allocated = $budget->allocate($action['id'] ?? 'contribution', $monthlyDisposable * 0.3);
                    $additionalContribution += $allocated;
                    $incomeImprovement += $this->estimateIncomeFromContribution($allocated, $yearsToRetirement);
                })(),
                'consolidation' => $incomeImprovement += $projectedIncome * $this->planConfig->getConsolidationEfficiencyGain(),
                'tax_optimisation' => $incomeImprovement += $projectedIncome * $this->planConfig->getTaxOptimisationGain(),
                default => $incomeImprovement += $projectedIncome * $this->planConfig->getDefaultActionGain(),
            };
        }

        $projectedWithActions = $projectedIncome + $incomeImprovement;
        $projectedGap = max(0, $targetIncome - $projectedWithActions);

        // Add FV of additional contributions on top of the already-projected value
        // (which already includes existing contributions)
        $additionalFv = $this->projectDcValue(0, $additionalContribution, $yearsToRetirement);
        $projectedDcWithActions = $projectedDcValueAtRetirement + $additionalFv;

        return [
            'current_scenario' => [
                'projected_annual_income' => $this->roundToPenny($projectedIncome),
                'income_gap' => $this->roundToPenny($incomeGap),
                'total_dc_value' => $this->roundToPenny($currentDcValue),
                'dc_value_at_retirement' => $this->roundToPenny($projectedDcValueAtRetirement),
            ],
            'projected_scenario' => [
                'projected_annual_income' => $this->roundToPenny($projectedWithActions),
                'income_gap' => $this->roundToPenny($projectedGap),
                'total_dc_value' => $this->roundToPenny($currentDcValue),
                'dc_value_at_retirement' => $this->roundToPenny($projectedDcWithActions),
                'additional_monthly_contribution' => $this->roundToPenny($additionalContribution),
            ],
            'is_approximate' => true,
            'frontend_calc_params' => [
                'current_dc_value' => $currentDcValue,
                'growth_rate' => $this->planConfig->getDefaultGrowthRate(),
                'years' => $yearsToRetirement,
                'annuity_rate' => $this->planConfig->getWithdrawalRate(),
            ],
        ];
    }

    /**
     * Build per-pension growth projection series for each DC pension.
     */
    private function buildPensionGrowthProjections(User $user, array $actions, Collection $dcPensions, int $yearsToRetirement): array
    {
        if ($yearsToRetirement <= 0 || $dcPensions->isEmpty()) {
            return [];
        }

        // Use disposable income for additional contribution estimates
        $monthlyDisposable = $this->incomeAccessor->getMonthlyForUser($user);
        $budget = new DistributionAccount($monthlyDisposable);

        $projections = [];

        foreach ($dcPensions as $pension) {
            $currentValue = (float) $pension->current_fund_value;
            $platformFee = (float) ($pension->platform_fee_percent ?? 0);
            $netGrowthRate = $this->planConfig->getDefaultGrowthRate() - ($platformFee / 100);

            // Compute annual contribution
            $monthlyContribution = (float) ($pension->monthly_contribution_amount ?? 0);
            if ($monthlyContribution > 0) {
                $annualContribution = $monthlyContribution * 12;
            } else {
                $salary = (float) ($pension->annual_salary ?? 0);
                $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
                $employerPct = (float) ($pension->employer_contribution_percent ?? 0);
                $annualContribution = $salary * ($employeePct + $employerPct) / 100;
            }

            // Estimate additional annual contribution from enabled account-level actions
            $accountActions = array_filter($actions, fn ($a) => ($a['scope'] ?? '') === 'account'
                && ($a['account_id'] ?? null) == $pension->id
                && ($a['enabled'] ?? false)
            );
            $perActionMonthly = $budget->allocate("pension_{$pension->id}", $monthlyDisposable * 0.3);
            $additionalAnnual = count($accountActions) > 0 ? $perActionMonthly * 12 : 0;

            // Build two series: current trajectory and with-actions
            $currentSeries = [];
            $withActionsSeries = [];

            for ($y = 0; $y <= $yearsToRetirement; $y++) {
                if ($y === 0) {
                    $currentSeries[] = round($currentValue);
                    $withActionsSeries[] = round($currentValue);
                } else {
                    // Current: compound growth with current contributions
                    $prevCurrent = $currentSeries[$y - 1];
                    $currentSeries[] = round(($prevCurrent + $annualContribution) * (1 + $netGrowthRate));

                    // With actions: add estimated additional contributions
                    $prevActions = $withActionsSeries[$y - 1];
                    $withActionsSeries[] = round(($prevActions + $annualContribution + $additionalAnnual) * (1 + $netGrowthRate));
                }
            }

            $projections[] = [
                'pension_id' => $pension->id,
                'pension_name' => $pension->scheme_name ?: ($pension->provider ?: 'Pension'),
                'pension_type' => $pension->scheme_type ?? $pension->pension_type ?? 'dc',
                'current_value' => $this->roundToPenny($currentValue),
                'annual_contribution' => $this->roundToPenny($annualContribution),
                'growth_rate' => round($netGrowthRate, 4),
                'years' => $yearsToRetirement,
                'projection_label' => 'to retirement',
                'current_series' => $currentSeries,
                'with_actions_series' => $withActionsSeries,
                'projection_difference' => max(0, end($withActionsSeries) - end($currentSeries)),
            ];
        }

        return $projections;
    }

    /**
     * Project DC pension value using the shared FV calculation.
     */
    private function projectDcValue(float $currentValue, float $monthlyExtra, int $years): float
    {
        return $this->projectFutureValue($currentValue, $this->planConfig->getDefaultGrowthRate(), $years, $monthlyExtra);
    }

    /**
     * Estimate additional annual retirement income from extra monthly contributions.
     */
    private function estimateIncomeFromContribution(float $monthlyContribution, int $years): float
    {
        if ($years <= 0 || $monthlyContribution <= 0) {
            return 0;
        }

        $fundAtRetirement = $this->projectDcValue(0, $monthlyContribution, $years);

        return $fundAtRetirement * $this->planConfig->getWithdrawalRate();
    }

    /**
     * Calculate monthly employee contribution (fixed amount or percentage-based).
     */
    private function calculateMonthlyEmployeeContribution(DCPension $pension): float
    {
        $monthly = (float) ($pension->monthly_contribution_amount ?? 0);
        if ($monthly > 0) {
            return $monthly;
        }

        $salary = (float) ($pension->annual_salary ?? 0);
        $employeePct = (float) ($pension->employee_contribution_percent ?? 0);

        if ($salary > 0 && $employeePct > 0) {
            return ($salary * $employeePct / 100) / 12;
        }

        return 0;
    }

    /**
     * Calculate monthly employer contribution from percentage-based data.
     */
    private function calculateMonthlyEmployerContribution(DCPension $pension): float
    {
        $salary = (float) ($pension->annual_salary ?? 0);
        $employerPct = (float) ($pension->employer_contribution_percent ?? 0);

        if ($salary > 0 && $employerPct > 0) {
            return ($salary * $employerPct / 100) / 12;
        }

        return 0;
    }
}
