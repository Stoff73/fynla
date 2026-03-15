<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\ProtectionAgent;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use App\Services\Protection\ProtectionActionDefinitionService;

class ProtectionPlanService extends BasePlanService
{
    public function __construct(
        private readonly ProtectionAgent $protectionAgent,
        private readonly ComprehensiveProtectionPlanService $comprehensivePlanService,
        private readonly ProtectionActionDefinitionService $actionDefinitionService,
        private readonly DisposableIncomeAccessor $incomeAccessor
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        // Use the comprehensive plan service - the SAME engine the protection module uses
        try {
            $comprehensivePlan = $this->comprehensivePlanService->generateComprehensiveProtectionPlan($user);
        } catch (\Exception $e) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'protection', $completeness),
                'completeness_warning' => $this->buildCompletenessWarning($completeness),
                'executive_summary' => [
                    'greeting' => 'Complete your protection profile to receive a personalised protection analysis.',
                ],
                'personal_information' => null,
                'linked_goals' => [],
                'unlinked_goals' => [],
                'current_situation' => [],
                'actions' => [],
                'what_if' => [],
                'conclusion' => $this->generateDynamicConclusion([], [], 'protection'),
                'error' => $e->getMessage(),
            ];
        }

        $currentSituation = $this->buildCurrentSituation($comprehensivePlan);
        $recommendations = $this->actionDefinitionService->evaluateActions($comprehensivePlan);
        $actions = $this->structureActions($recommendations, 'protection');
        $actions = $this->applyActionFilter($actions, $options);
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();
        $disabledActions = collect($actions)->where('enabled', false)->values()->toArray();

        $whatIf = $this->buildWhatIfData($comprehensivePlan, $enabledActions);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'protection');

        $goals = $this->getGoalsForPlan($userId, 'protection');

        return [
            'metadata' => $this->buildPlanMetadata($user, 'protection', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $comprehensivePlan, $actions),
            'personal_information' => $this->buildPersonalInformation($user, $comprehensivePlan),
            'linked_goals' => $goals['linked'],
            'unlinked_goals' => $goals['unlinked'],
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    public function getRecommendations(int $userId, ?array $preComputedData = null): array
    {
        $user = User::findOrFail($userId);

        try {
            $comprehensivePlan = $this->comprehensivePlanService->generateComprehensiveProtectionPlan($user);

            return $this->actionDefinitionService->evaluateActions($comprehensivePlan);
        } catch (\Exception) {
            return [];
        }
    }

    public function checkDataCompleteness(int $userId): array
    {
        $user = User::with(['protectionProfile', 'lifeInsurancePolicies'])->find($userId);
        $missing = [];

        if (! $user) {
            return ['percentage' => 0, 'missing' => [['field' => 'user', 'label' => 'User profile']], 'complete' => false];
        }

        if (! $user->protectionProfile) {
            $missing[] = [
                'field' => 'protection_profile',
                'label' => 'Protection profile',
                'description' => 'Create a protection profile with your income, expenditure, and dependants.',
                'link' => '/protection',
            ];
        }

        $hasIncome = ($user->annual_employment_income ?? 0) > 0 ||
                     ($user->annual_self_employment_income ?? 0) > 0;
        if (! $hasIncome) {
            $missing[] = [
                'field' => 'income',
                'label' => 'Income details',
                'description' => 'Add your income to calculate accurate protection needs.',
                'link' => '/profile',
            ];
        }

        $hasPolicies = $user->lifeInsurancePolicies->isNotEmpty();
        if (! $hasPolicies) {
            $missing[] = [
                'field' => 'policies',
                'label' => 'Protection policies',
                'description' => 'Add any existing life insurance, critical illness, or income protection policies.',
                'link' => '/protection',
            ];
        }

        $total = 3;
        $present = $total - count($missing);

        return [
            'percentage' => $total > 0 ? round(($present / $total) * 100) : 0,
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    /**
     * Build structured executive summary matching investment/retirement pattern.
     */
    private function buildExecutiveSummary(User $user, array $comprehensivePlan, array $actions): array
    {
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $firstName = $this->getUserFirstName($user);

        $greeting = "Dear {$firstName},";
        $opening = 'Thank you for using Fynla. Here is your personalised Protection Plan based on the information you have provided.';
        $introduction = 'Below you will find a summary of your current protection coverage, any gaps we have identified, and the specific steps you can take to close them.';

        // Coverage summary table data
        $coverageSummary = [];
        $coverageTypes = [
            'life_insurance' => 'Life Insurance',
            'critical_illness' => 'Critical Illness',
            'income_protection' => 'Income Protection',
        ];

        foreach ($coverageTypes as $key => $name) {
            $data = $coverageAnalysis[$key] ?? [];
            $need = (float) ($data['need'] ?? 0);
            $coverage = (float) ($data['coverage'] ?? 0);
            $gap = (float) ($data['gap'] ?? 0);

            $coverageSummary[] = [
                'name' => $name,
                'need' => $need,
                'coverage' => $coverage,
                'gap' => $gap,
                'status' => $gap <= 0 ? 'Adequate' : 'Gap',
            ];
        }

        // Key actions summary (top 5)
        $enabledActions = collect($actions)->where('enabled', true)->values();
        $actionsSummary = $enabledActions->take(5)->map(fn ($a) => [
            'title' => $a['title'],
            'priority' => $a['priority'],
        ])->toArray();

        $hasGaps = collect($coverageSummary)->contains(fn ($c) => $c['gap'] > 0);
        $closing = $hasGaps
            ? 'The sections below break down exactly where your gaps are, how we calculated them, and the specific steps you can take to close them. You can toggle each recommendation on or off to see how it would change your position.'
            : 'Our analysis shows your current protection coverage is adequate across all areas. You can review the details below.';

        return [
            'greeting' => $greeting,
            'opening' => $opening,
            'introduction' => $introduction,
            'coverage_summary' => $coverageSummary,
            'actions_summary' => $actionsSummary,
            'total_actions' => count($actions),
            'closing' => $closing,
        ];
    }

    /**
     * Build personal information section for the plan.
     */
    private function buildPersonalInformation(User $user, array $comprehensivePlan): array
    {
        $profile = $comprehensivePlan['user_profile'] ?? [];

        $fullName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: ($user->name ?? "\u{2014}");
        $dob = $user->date_of_birth;
        $age = $dob ? (int) $dob->diffInYears(now()) : null;

        // Spouse
        $spouseName = null;
        if (in_array($user->marital_status, ['married', 'civil_partnership']) && $user->spouse) {
            $spouse = $user->spouse;
            $spouseName = trim(($spouse->first_name ?? '').' '.($spouse->surname ?? '')) ?: $spouse->name;
        }

        // Children
        $children = $user->familyMembers()
            ->where('relationship', 'child')
            ->get()
            ->map(fn ($child) => $child->name)
            ->toArray();

        // Income
        $grossIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);

        $incomeData = $this->incomeAccessor->getForUser($user);

        return [
            'full_name' => $fullName,
            'date_of_birth' => $dob?->toDateString(),
            'age' => $age,
            'marital_status' => $user->marital_status,
            'spouse_name' => $spouseName,
            'children' => $children,
            'gross_income' => $this->roundToPenny($grossIncome),
            'net_income' => $this->roundToPenny($incomeData['net_income']),
            'annual_expenditure' => $this->roundToPenny($incomeData['annual_expenditure']),
            'disposable_income' => $this->roundToPenny($incomeData['annual']),
            'monthly_disposable' => $this->roundToPenny($incomeData['monthly']),
            'occupation' => $profile['occupation'] ?? null,
            'smoker_status' => $profile['smoker_status'] ?? null,
            'health_status' => $profile['health_status'] ?? null,
            'retirement_age' => $profile['retirement_age'] ?? null,
        ];
    }

    private function buildCurrentSituation(array $comprehensivePlan): array
    {
        return [
            'profile' => $comprehensivePlan['user_profile'] ?? [],
            'financial_summary' => $comprehensivePlan['financial_summary'] ?? [],
            'needs' => $comprehensivePlan['protection_needs'] ?? [],
            'current_coverage' => $comprehensivePlan['current_coverage'] ?? [],
            'coverage_analysis' => $comprehensivePlan['coverage_analysis'] ?? [],
            'scenario_analysis' => $comprehensivePlan['scenario_analysis'] ?? [],
            'debt_breakdown' => $comprehensivePlan['financial_summary']['debt_breakdown'] ?? [],
        ];
    }

    private function buildWhatIfData(array $comprehensivePlan, array $enabledActions): array
    {
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];

        // Current gaps from coverage analysis
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $ciGap = (float) ($coverageAnalysis['critical_illness']['gap'] ?? 0);
        $ipGap = (float) ($coverageAnalysis['income_protection']['gap'] ?? 0);
        $totalGap = $lifeGap + $ciGap + ($ipGap * 12);

        // For each enabled action in a gap category, close that gap using the action's coverage amount
        $lifeReduction = 0;
        $ciReduction = 0;
        $ipReduction = 0;
        $additionalPremium = 0;

        foreach ($enabledActions as $action) {
            $category = strtolower($action['category'] ?? '');
            $coverageAmount = (float) ($action['impact_parameters']['coverage_amount'] ?? $action['estimated_impact'] ?? 0);
            $premium = (float) ($action['estimated_impact'] ?? 0);

            if (str_contains($category, 'life')) {
                // Use the action's coverage_amount if available, otherwise close the full gap
                $lifeReduction += $coverageAmount > 0 ? $coverageAmount : $lifeGap;
                $additionalPremium += $premium;
            } elseif (str_contains($category, 'critical')) {
                $ciReduction += $coverageAmount > 0 ? $coverageAmount : $ciGap;
                $additionalPremium += $premium;
            } elseif (str_contains($category, 'income')) {
                $ipReduction += $coverageAmount > 0 ? $coverageAmount : $ipGap;
                $additionalPremium += $premium;
            }
        }

        $projectedLife = max(0, $lifeGap - $lifeReduction);
        $projectedCi = max(0, $ciGap - $ciReduction);
        $projectedIp = max(0, $ipGap - $ipReduction);
        $projectedTotal = $projectedLife + $projectedCi + ($projectedIp * 12);

        // Coverage amounts for chart (these always have positive values on both sides)
        $lifeNeed = (float) ($coverageAnalysis['life_insurance']['need'] ?? 0);
        $lifeCoverage = (float) ($coverageAnalysis['life_insurance']['coverage'] ?? 0);
        $ciNeed = (float) ($coverageAnalysis['critical_illness']['need'] ?? 0);
        $ciCoverage = (float) ($coverageAnalysis['critical_illness']['coverage'] ?? 0);
        $ipNeed = (float) ($coverageAnalysis['income_protection']['need'] ?? 0);
        $ipCoverage = (float) ($coverageAnalysis['income_protection']['coverage'] ?? 0);

        return [
            'current_scenario' => [
                'total_coverage_gap' => $this->roundToPenny($totalGap),
                'life_insurance_gap' => $this->roundToPenny($lifeGap),
                'critical_illness_gap' => $this->roundToPenny($ciGap),
                'income_protection_gap' => $this->roundToPenny($ipGap),
                'life_insurance_coverage' => $this->roundToPenny($lifeCoverage),
                'critical_illness_coverage' => $this->roundToPenny($ciCoverage),
                'income_protection_coverage' => $this->roundToPenny($ipCoverage),
                'life_insurance_need' => $this->roundToPenny($lifeNeed),
                'critical_illness_need' => $this->roundToPenny($ciNeed),
                'income_protection_need' => $this->roundToPenny($ipNeed),
            ],
            'projected_scenario' => [
                'total_coverage_gap' => $this->roundToPenny($projectedTotal),
                'life_insurance_gap' => $this->roundToPenny($projectedLife),
                'critical_illness_gap' => $this->roundToPenny($projectedCi),
                'income_protection_gap' => $this->roundToPenny($projectedIp),
                'life_insurance_coverage' => $this->roundToPenny($lifeCoverage + $lifeReduction),
                'critical_illness_coverage' => $this->roundToPenny($ciCoverage + $ciReduction),
                'income_protection_coverage' => $this->roundToPenny($ipCoverage + $ipReduction),
                'life_insurance_need' => $this->roundToPenny($lifeNeed),
                'critical_illness_need' => $this->roundToPenny($ciNeed),
                'income_protection_need' => $this->roundToPenny($ipNeed),
                'estimated_additional_premium' => $this->roundToPenny($additionalPremium),
            ],
            'is_approximate' => true,
        ];
    }
}
