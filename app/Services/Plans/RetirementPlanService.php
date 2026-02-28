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
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

class RetirementPlanService extends BasePlanService
{
    public function __construct(
        private readonly RetirementAgent $retirementAgent,
        private readonly PensionProjector $projector,
        private readonly TaxConfigService $taxConfig
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
                'error' => $analysis['message'] ?? 'Unable to generate retirement analysis.',
            ];
        }

        $currentSituation = $this->buildCurrentSituation($data, $userId);
        $recommendations = $this->getRecommendations($userId);
        $actions = $this->structureActions($recommendations, 'retirement');
        $actions = $this->applyActionFilter($actions, $options);
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();

        // Compute years to retirement for projections
        $profile = RetirementProfile::where('user_id', $userId)->first();
        $yearsToRetirement = $profile ? max(0, $profile->target_retirement_age - $profile->current_age) : 0;

        $dcPensions = DCPension::where('user_id', $userId)->get();
        $pensionProjections = $this->buildPensionGrowthProjections($actions, $dcPensions, $yearsToRetirement);

        $whatIf = $this->buildWhatIfData($data, $currentSituation, $enabledActions, $yearsToRetirement);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'retirement');

        return [
            'metadata' => $this->buildPlanMetadata($user, 'retirement', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $data, $userId),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'pension_projections' => $pensionProjections,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    public function getRecommendations(int $userId): array
    {
        $analysis = $this->retirementAgent->analyze($userId);
        $data = $analysis['data'] ?? [];

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

    private function buildExecutiveSummary(User $user, array $data, int $userId): array
    {
        $firstName = $user->first_name ?? explode(' ', $user->name)[0] ?? 'there';
        $summary = $data['summary'] ?? [];
        $projectedIncome = $summary['projected_retirement_income'] ?? 0;
        $targetIncome = $summary['target_retirement_income'] ?? 0;
        $incomeGap = $summary['income_gap'] ?? 0;
        $yearsToRetirement = $summary['years_to_retirement'] ?? 0;
        $retirementAge = $summary['target_retirement_age'] ?? 0;
        $currentDcValue = $summary['current_dc_value'] ?? 0;

        $dcPensions = DCPension::where('user_id', $userId)->get();
        $dbPensions = DBPension::where('user_id', $userId)->get();
        $statePension = StatePension::where('user_id', $userId)->first();
        $retirementProfile = RetirementProfile::where('user_id', $userId)->first();

        $lines = [];
        $lines[] = "Dear {$firstName},";
        $lines[] = '';
        $lines[] = 'Thank you for using Fynla. Here is your personalised Retirement Plan based on your pension arrangements and retirement goals.';
        $lines[] = '';

        // Retirement timeline
        if ($yearsToRetirement > 0) {
            $age = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->age : null;
            if ($age) {
                $lines[] = sprintf(
                    'At %d years old, you have %d years until your target retirement age of %d.',
                    $age,
                    $yearsToRetirement,
                    $retirementAge
                );
            } else {
                $lines[] = sprintf('You have %d years until your target retirement age of %d.', $yearsToRetirement, $retirementAge);
            }
        } else {
            $lines[] = 'You have reached or passed your target retirement age, so planning for income in retirement is particularly important.';
        }

        // Pension arrangements
        $pensionDescriptions = [];
        if ($dcPensions->isNotEmpty()) {
            foreach ($dcPensions as $dc) {
                $name = $dc->scheme_name ?: ($dc->provider ?: 'a pension');
                $value = $this->formatCurrency((float) $dc->current_fund_value);
                $pensionDescriptions[] = "{$name} (valued at {$value})";
            }
        }
        if ($dbPensions->isNotEmpty()) {
            foreach ($dbPensions as $db) {
                $name = $db->scheme_name ?: 'a defined benefit pension';
                $annual = $this->formatCurrency((float) ($db->projected_annual_pension ?? 0));
                $pensionDescriptions[] = "{$name} (projected at {$annual} per year)";
            }
        }

        if (! empty($pensionDescriptions)) {
            $pensionList = count($pensionDescriptions) === 1
                ? $pensionDescriptions[0]
                : implode(', ', array_slice($pensionDescriptions, 0, -1)).' and '.end($pensionDescriptions);
            $lines[] = "Your pension arrangements include {$pensionList}.";
        }

        // State pension
        $forecastAnnual = (float) ($statePension->state_pension_forecast_annual ?? 0);
        if ($statePension && $forecastAnnual > 0) {
            $weeklyAmount = $this->formatCurrency(round($forecastAnnual / 52, 2));
            $niYears = $statePension->ni_years_completed ?? 0;
            $lines[] = sprintf(
                'Your State Pension is projected at %s per week based on %d qualifying years of National Insurance contributions.',
                $weeklyAmount,
                $niYears
            );
        }

        // Total current pension pot
        if ($currentDcValue > 0) {
            $lines[] = sprintf(
                'Your total pension pot currently stands at %s.',
                $this->formatCurrency($currentDcValue)
            );
        }

        // Income target and gap
        $retiresBeforeSPA = $summary['retires_before_spa'] ?? false;
        $statePensionAge = $summary['state_pension_age'] ?? 67;

        $lines[] = '';
        if ($targetIncome > 0) {
            $lines[] = sprintf(
                'You have set a target retirement income of %s per year.',
                $this->formatCurrency($targetIncome)
            );

            if ($incomeGap <= 0) {
                $lines[] = sprintf(
                    'Based on your current pension arrangements, your projected retirement income of %s per year meets this target. Your retirement planning is on track.',
                    $this->formatCurrency($projectedIncome)
                );
            } else {
                if ($retiresBeforeSPA) {
                    $incomeGapAfterSPA = $summary['income_gap_after_spa'] ?? 0;
                    $incomeAfterSPA = $summary['income_after_spa'] ?? 0;
                    $spIncome = $summary['state_pension_income'] ?? 0;

                    $lines[] = sprintf(
                        'Because you plan to retire at %d, which is %d years before your State Pension begins at age %d, your projected income at retirement is %s per year from your pensions alone, leaving a shortfall of %s per year.',
                        $retirementAge,
                        $statePensionAge - $retirementAge,
                        $statePensionAge,
                        $this->formatCurrency($projectedIncome),
                        $this->formatCurrency($incomeGap)
                    );

                    if ($incomeGapAfterSPA > 0) {
                        $lines[] = sprintf(
                            'Once your State Pension of %s per year begins at age %d, your total income rises to %s, reducing the shortfall to %s per year.',
                            $this->formatCurrency($spIncome),
                            $statePensionAge,
                            $this->formatCurrency($incomeAfterSPA),
                            $this->formatCurrency($incomeGapAfterSPA)
                        );
                    } else {
                        $lines[] = sprintf(
                            'Once your State Pension of %s per year begins at age %d, your total income rises to %s, which meets your target.',
                            $this->formatCurrency($spIncome),
                            $statePensionAge,
                            $this->formatCurrency($incomeAfterSPA)
                        );
                    }
                } else {
                    $lines[] = sprintf(
                        'Based on your current arrangements, your projected retirement income is %s per year, leaving a shortfall of %s per year against your target. The recommendations below outline steps to close this gap.',
                        $this->formatCurrency($projectedIncome),
                        $this->formatCurrency($incomeGap)
                    );
                }
            }

            $lines[] = 'The recommendations below outline steps to close this gap.';
        }

        // Employer contributions
        $totalMonthlyEmployer = $dcPensions->sum(fn ($p) => $this->calculateMonthlyEmployerContribution($p));
        if ($totalMonthlyEmployer > 0) {
            $lines[] = sprintf(
                'Your employer currently contributes %s per month towards your pension, which is factored into our projections.',
                $this->formatCurrency(round($totalMonthlyEmployer, 2))
            );
        }

        $lines[] = '';
        $lines[] = 'The sections below detail your current pension arrangements, projected retirement income, and specific actions you can take to improve your retirement position.';

        return [
            'narrative' => implode("\n", $lines),
            'key_metrics' => [],
            'on_track' => $incomeGap <= 0,
        ];
    }

    private function buildEmptyExecutiveSummary(): array
    {
        return [
            'narrative' => 'Set up your retirement profile and add your pensions to receive a personalised retirement plan.',
            'key_metrics' => [],
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

    private function buildWhatIfData(array $data, array $currentSituation, array $enabledActions, int $yearsToRetirement = 0): array
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

        $additionalContribution = 0;
        $incomeImprovement = 0;

        foreach ($enabledActions as $action) {
            $category = strtolower($action['category'] ?? '');
            if (str_contains($category, 'contribution') || str_contains($category, 'start_contribution')) {
                $additionalContribution += 200; // £200/month increase estimate
                $incomeImprovement += $this->estimateIncomeFromContribution(200, $yearsToRetirement);
            } elseif (str_contains($category, 'consolid')) {
                $incomeImprovement += $projectedIncome * 0.02; // 2% efficiency gain
            } elseif (str_contains($category, 'tax') || str_contains($category, 'allowance')) {
                $incomeImprovement += $projectedIncome * 0.03; // 3% from tax optimisation
            } else {
                $incomeImprovement += $projectedIncome * 0.01;
            }
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
                'growth_rate' => 0.05,
                'years' => $yearsToRetirement,
                'annuity_rate' => 0.04,
            ],
        ];
    }

    /**
     * Build per-pension growth projection series for each DC pension.
     */
    private function buildPensionGrowthProjections(array $actions, Collection $dcPensions, int $yearsToRetirement): array
    {
        if ($yearsToRetirement <= 0 || $dcPensions->isEmpty()) {
            return [];
        }

        $projections = [];

        foreach ($dcPensions as $pension) {
            $currentValue = (float) $pension->current_fund_value;
            $platformFee = (float) ($pension->platform_fee_percent ?? 0);
            $netGrowthRate = 0.05 - ($platformFee / 100);

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
            $accountActions = array_filter($actions, fn ($a) =>
                ($a['scope'] ?? '') === 'account'
                && ($a['account_id'] ?? null) == $pension->id
                && ($a['enabled'] ?? false)
            );
            $additionalAnnual = count($accountActions) * 2400; // £200/month per action estimate

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
     * Project DC pension value: FV = PV*(1+r)^n + PMT*((1+r)^n - 1)/r
     */
    private function projectDcValue(float $currentValue, float $monthlyExtra, int $years): float
    {
        if ($years <= 0) {
            return $currentValue;
        }

        $monthlyRate = 0.05 / 12;
        $months = $years * 12;
        $fv = $currentValue * pow(1 + $monthlyRate, $months);
        if ($monthlyExtra > 0 && $monthlyRate > 0) {
            $fv += $monthlyExtra * ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
        }

        return $fv;
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

        // Assume 4% sustainable withdrawal rate
        return $fundAtRetirement * 0.04;
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
