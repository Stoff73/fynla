<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\ProtectionAgent;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;

class ProtectionPlanService extends BasePlanService
{
    public function __construct(
        private readonly ProtectionAgent $protectionAgent,
        private readonly ComprehensiveProtectionPlanService $comprehensivePlanService
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
                'executive_summary' => $this->buildEmptyExecutiveSummary(),
                'current_situation' => [],
                'actions' => [],
                'what_if' => [],
                'conclusion' => $this->generateDynamicConclusion([], [], 'protection'),
                'error' => $e->getMessage(),
            ];
        }

        $currentSituation = $this->buildCurrentSituation($comprehensivePlan);
        $recommendations = $this->extractRecommendations($comprehensivePlan);
        $actions = $this->structureActions($recommendations, 'protection');
        $actions = $this->applyActionFilter($actions, $options);
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();

        $whatIf = $this->buildWhatIfData($comprehensivePlan, $enabledActions);
        $disabledActions = collect($actions)->where('enabled', false)->values()->toArray();
        $conclusion = $this->buildProtectionConclusion($enabledActions, $disabledActions, $whatIf);

        return [
            'metadata' => $this->buildPlanMetadata($user, 'protection', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $comprehensivePlan),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    /**
     * Extract actionable recommendations from the comprehensive plan's optimized strategy.
     * Each recommendation has specific amounts, costs, and timeframes.
     */
    private function extractRecommendations(array $comprehensivePlan): array
    {
        $strategy = $comprehensivePlan['optimized_strategy'] ?? [];
        $strategyRecs = $strategy['recommendations'] ?? [];
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];

        $recommendations = [];

        // Pull from optimized strategy recommendations (have exact amounts and costs)
        foreach ($strategyRecs as $rec) {
            $category = $rec['category'] ?? 'Protection';
            $coverageAmount = $rec['coverage_amount'] ?? $rec['monthly_benefit'] ?? 0;
            $monthlyCost = $rec['estimated_monthly_cost'] ?? 0;

            $recommendations[] = [
                'priority' => $rec['priority'] ?? 3,
                'category' => $category,
                'action' => $rec['action'] ?? 'Review coverage',
                'rationale' => $rec['details'] ?? '',
                'impact' => $rec['importance'] ?? 'Medium',
                'estimated_cost' => round($monthlyCost, 2),
                'impact_parameters' => ['coverage_amount' => $coverageAmount],
                'timeframe' => $rec['timeframe'] ?? 'Within 3 months',
            ];
        }

        // Ensure every non-zero gap has at least one action
        $this->ensureGapActions($recommendations, $coverageAnalysis);

        // Sort by priority
        usort($recommendations, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $recommendations;
    }

    /**
     * Ensure there is at least one action for each non-zero coverage gap.
     * The optimized strategy has high thresholds, so gaps can exist without actions.
     */
    private function ensureGapActions(array &$recommendations, array $coverageAnalysis): void
    {
        $hasLifeRec = collect($recommendations)->contains(fn ($r) => str_contains(strtolower($r['category'] ?? ''), 'life'));
        $hasCiRec = collect($recommendations)->contains(fn ($r) => str_contains(strtolower($r['category'] ?? ''), 'critical'));
        $hasIpRec = collect($recommendations)->contains(fn ($r) => str_contains(strtolower($r['category'] ?? ''), 'income'));

        // Life insurance gap
        $lifeGap = $coverageAnalysis['life_insurance']['gap'] ?? 0;
        if ($lifeGap > 0 && ! $hasLifeRec) {
            $recommendations[] = [
                'priority' => 1,
                'category' => 'Life Insurance',
                'action' => sprintf('Increase life insurance cover by %s', $this->formatCurrency($lifeGap)),
                'rationale' => sprintf(
                    'Your current life insurance falls short of your calculated need by %s. Closing this gap would protect your dependants financially.',
                    $this->formatCurrency($lifeGap)
                ),
                'impact' => 'Critical',
                'estimated_cost' => 0,
                'impact_parameters' => ['coverage_amount' => $lifeGap],
            ];
        }

        // Critical illness gap
        $ciGap = $coverageAnalysis['critical_illness']['gap'] ?? 0;
        if ($ciGap > 0 && ! $hasCiRec) {
            $ciCoverage = $coverageAnalysis['critical_illness']['coverage'] ?? 0;
            $rationale = $ciCoverage > 0
                ? sprintf('Your critical illness cover of %s leaves a shortfall of %s against your calculated need.', $this->formatCurrency($ciCoverage), $this->formatCurrency($ciGap))
                : 'You have no critical illness cover. A serious diagnosis could leave you unable to meet financial commitments.';

            $recommendations[] = [
                'priority' => 2,
                'category' => 'Critical Illness',
                'action' => sprintf('Add critical illness cover for %s', $this->formatCurrency($ciGap)),
                'rationale' => $rationale,
                'impact' => 'High',
                'estimated_cost' => 0,
                'impact_parameters' => ['coverage_amount' => $ciGap],
            ];
        }

        // Income protection gap
        $ipGap = $coverageAnalysis['income_protection']['gap'] ?? 0;
        if ($ipGap > 0 && ! $hasIpRec) {
            $ipCoverage = $coverageAnalysis['income_protection']['coverage'] ?? 0;
            $rationale = $ipCoverage > 0
                ? sprintf('Your income protection of %s per month leaves a shortfall of %s per month.', $this->formatCurrency($ipCoverage), $this->formatCurrency($ipGap))
                : 'You have no income protection. If illness or injury prevented you from working, you would have no replacement income.';

            $recommendations[] = [
                'priority' => 2,
                'category' => 'Income Protection',
                'action' => sprintf('Add income protection for %s per month', $this->formatCurrency($ipGap)),
                'rationale' => $rationale,
                'impact' => 'High',
                'estimated_cost' => 0,
                'impact_parameters' => ['coverage_amount' => $ipGap],
            ];
        }
    }

    public function getRecommendations(int $userId): array
    {
        $user = User::findOrFail($userId);

        try {
            $comprehensivePlan = $this->comprehensivePlanService->generateComprehensiveProtectionPlan($user);

            return $this->extractRecommendations($comprehensivePlan);
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

    private function buildExecutiveSummary(User $user, array $comprehensivePlan): array
    {
        $profile = $comprehensivePlan['user_profile'] ?? [];
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];

        $familyMembers = \App\Models\FamilyMember::where('user_id', $user->id)
            ->orderByRaw("FIELD(relationship, 'spouse', 'partner', 'child', 'parent', 'sibling', 'other')")
            ->get();

        $firstName = $user->first_name ?? explode(' ', $user->name)[0] ?? 'there';
        $age = $profile['age'] ?? null;
        $occupation = $profile['occupation'] ?? null;
        $maritalStatus = strtolower($profile['marital_status'] ?? 'single');
        $retirementAge = $profile['retirement_age'] ?? 65;
        $smokerStatus = $profile['smoker_status'] ?? null;
        $healthStatus = $profile['health_status'] ?? null;

        // Greeting
        $lines = [];
        $lines[] = "Dear {$firstName},";
        $lines[] = '';
        $lines[] = 'Thank you for using Fynla. Here is your personalised Protection Plan based on the information you have provided.';

        // Personal context paragraph
        $contextParts = [];
        if (is_numeric($age)) {
            $contextParts[] = "you are {$age} years old";
        }
        if ($occupation && $occupation !== 'Not specified') {
            $contextParts[] = "working as {$this->prefixWithArticle($occupation)}";
        }

        // Spouse
        $spouse = $familyMembers->whereIn('relationship', ['spouse', 'partner'])->first();
        if ($spouse) {
            $spouseName = $spouse->first_name ?: $spouse->name;
            $spouseRelationship = strtolower($spouse->relationship ?? 'partner');
            if (in_array($maritalStatus, ['married', 'civil partnership'])) {
                $contextParts[] = "married to {$spouseName}";
            } else {
                $contextParts[] = "in a relationship with {$spouseName} (your {$spouseRelationship})";
            }
        }

        if (! empty($contextParts)) {
            $lines[] = '';
            $lines[] = 'Based on your profile, '.implode(', ', $contextParts).'.';
        }

        // Dependants
        $dependants = $familyMembers->where('is_dependent', true);
        if ($dependants->isNotEmpty()) {
            $depParts = [];
            foreach ($dependants as $dep) {
                $depName = $dep->first_name ?: $dep->name;
                $depRelationship = strtolower($dep->relationship ?? 'dependant');
                $depAge = $dep->date_of_birth ? \Carbon\Carbon::parse($dep->date_of_birth)->age : null;
                $depParts[] = $depAge !== null
                    ? "{$depName} (your {$depRelationship}, age {$depAge})"
                    : "{$depName} (your {$depRelationship})";
            }

            $depCount = count($depParts);
            $depList = $depCount === 1
                ? $depParts[0]
                : implode(', ', array_slice($depParts, 0, -1)).' and '.end($depParts);

            $lines[] = $depCount === 1
                ? "You have one dependant, {$depList}, who relies on your income for their financial security."
                : "You have {$depCount} dependants — {$depList} — who rely on your income for their financial security.";
        }

        // Income and employment
        $grossIncome = $financialSummary['annual_income'] ?? 0;
        if ($grossIncome > 0) {
            $incomeBreakdown = $financialSummary['income_breakdown'] ?? [];
            $employmentIncome = $incomeBreakdown['employment_income'] ?? 0;
            $selfEmploymentIncome = $incomeBreakdown['self_employment_income'] ?? 0;

            $incomeDesc = $this->formatCurrency($grossIncome);
            if ($selfEmploymentIncome > 0 && $employmentIncome > 0) {
                $lines[] = "Your combined gross income is {$incomeDesc} per year from both employment and self-employment.";
            } elseif ($selfEmploymentIncome > 0) {
                $lines[] = "As a self-employed individual, your gross income is {$incomeDesc} per year.";
            } else {
                $lines[] = "Your gross employment income is {$incomeDesc} per year.";
            }

            if (is_numeric($age) && $age < $retirementAge) {
                $yearsToRetirement = $retirementAge - $age;
                $lines[] = "With {$yearsToRetirement} years until your planned retirement at age {$retirementAge}, protecting this income is essential.";
            }
        }

        // Health and lifestyle factors
        $healthParts = [];
        if ($smokerStatus && strtolower($smokerStatus) !== 'non-smoker') {
            $healthParts[] = 'your smoking status';
        }
        if ($healthStatus && strtolower($healthStatus) !== 'good') {
            $healthParts[] = 'pre-existing health conditions';
        }
        if (! empty($healthParts)) {
            $lines[] = 'We have also factored in '.implode(' and ', $healthParts).', which may affect premium costs.';
        }

        // What we found
        $lines[] = '';
        $lifePolicyCount = $user->lifeInsurancePolicies()->count();
        $ciPolicyCount = $user->criticalIllnessPolicies()->count();
        $ipPolicyCount = $user->incomeProtectionPolicies()->count();
        $policyCount = $lifePolicyCount + $ciPolicyCount + $ipPolicyCount;

        $lifeGap = $coverageAnalysis['life_insurance']['gap'] ?? 0;
        $ciGap = $coverageAnalysis['critical_illness']['gap'] ?? 0;
        $ipGap = $coverageAnalysis['income_protection']['gap'] ?? 0;
        $hasGaps = $lifeGap > 0 || $ciGap > 0 || $ipGap > 0;

        $gapAreas = [];
        if ($lifeGap > 0) {
            $gapAreas[] = 'life insurance';
        }
        if ($ciGap > 0) {
            $gapAreas[] = 'critical illness';
        }
        if ($ipGap > 0) {
            $gapAreas[] = 'income protection';
        }

        if ($policyCount === 0 && $hasGaps) {
            $familyRef = $dependants->isNotEmpty()
                ? ($spouse ? "{$spouse->first_name} and your ".($dependants->count() === 1 ? 'child' : 'children') : 'your family')
                : ($spouse ? $spouse->first_name : 'you');
            $lines[] = "Our analysis shows that you currently have no protection policies in place. This means if something were to happen to you, {$familyRef} would have no financial safety net.";
        } elseif ($policyCount > 0 && $hasGaps) {
            $policyTypes = [];
            if ($lifePolicyCount > 0) {
                $policyTypes[] = 'life insurance';
            }
            if ($ciPolicyCount > 0) {
                $policyTypes[] = 'critical illness';
            }
            if ($ipPolicyCount > 0) {
                $policyTypes[] = 'income protection';
            }
            $lines[] = sprintf(
                'You currently have %d %s in place covering %s. However, our analysis has identified shortfalls in your %s coverage that leave gaps in your protection.',
                $policyCount,
                $policyCount === 1 ? 'policy' : 'policies',
                implode(' and ', $policyTypes),
                implode(' and ', $gapAreas)
            );
        } elseif ($policyCount > 0 && ! $hasGaps) {
            $lines[] = "You currently have {$policyCount} protection ".($policyCount === 1 ? 'policy' : 'policies').' in place, and our analysis shows your coverage is adequate across all areas.';
        }

        // Debt context
        $debtTotal = $financialSummary['debt_breakdown']['total'] ?? 0;
        if ($debtTotal > 0) {
            $mortgageDebt = $financialSummary['debt_breakdown']['mortgage'] ?? 0;
            if ($mortgageDebt > 0) {
                $lines[] = sprintf(
                    'Your outstanding mortgage of %s has been included in our calculations, as this debt would need to be covered in the event of your death or a serious illness.',
                    $this->formatCurrency($mortgageDebt)
                );
            }
        }

        // Closing - point to the detail below
        if ($hasGaps) {
            $lines[] = '';
            $lines[] = 'The sections below break down exactly where your gaps are, how we calculated them, and the specific steps you can take to close them. You can toggle each recommendation on or off to see how it would change your position.';
        }

        return [
            'narrative' => implode("\n", $lines),
            'key_metrics' => [],
        ];
    }

    /**
     * Prefix a word with "a" or "an" based on its first letter.
     */
    private function prefixWithArticle(string $word): string
    {
        $firstChar = strtolower(substr(trim($word), 0, 1));

        return in_array($firstChar, ['a', 'e', 'i', 'o', 'u']) ? "an {$word}" : "a {$word}";
    }

    /**
     * Build a protection-specific conclusion that references the actual actions and their impact.
     */
    private function buildProtectionConclusion(array $enabledActions, array $disabledActions, array $whatIf): array
    {
        $current = $whatIf['current_scenario'] ?? [];
        $projected = $whatIf['projected_scenario'] ?? [];
        $totalActions = count($enabledActions) + count($disabledActions);

        $currentTotalGap = $current['total_coverage_gap'] ?? 0;
        $projectedTotalGap = $projected['total_coverage_gap'] ?? 0;
        $additionalPremium = $projected['estimated_additional_premium'] ?? 0;

        $lines = [];

        // No actions at all (no gaps identified)
        if ($totalActions === 0) {
            $lines[] = 'Our analysis has not identified any coverage gaps. Your existing protection appears adequate for your current circumstances.';

            return [
                'summary_text' => implode(' ', $lines),
                'total_actions' => 0,
                'critical_actions' => 0,
                'high_priority_actions' => 0,
                'detailed_breakdown' => [],
            ];
        }

        // All actions disabled
        if (empty($enabledActions)) {
            $lines[] = 'You have chosen not to enable any of the recommended actions.';

            $gapDescriptions = $this->describeActions($disabledActions, 'unaddressed');
            if (! empty($gapDescriptions)) {
                $lines[] = 'This means the following gaps remain: ' . implode('; ', $gapDescriptions) . '.';
            }

            $lines[] = sprintf(
                'Your total coverage gap remains at %s. Consider enabling the actions above to see how they would improve your position.',
                $this->formatCurrency($currentTotalGap)
            );

            return [
                'summary_text' => implode(' ', $lines),
                'total_actions' => 0,
                'critical_actions' => 0,
                'high_priority_actions' => 0,
                'detailed_breakdown' => [],
            ];
        }

        // Some or all actions enabled
        $enabledCount = count($enabledActions);
        $highPriority = collect($enabledActions)->where('priority', 'high')->count();
        $critical = collect($enabledActions)->where('priority', 'critical')->count();

        // Describe what enabled actions do
        $enabledDescriptions = $this->describeActions($enabledActions, 'enabled');
        if (! empty($enabledDescriptions)) {
            $lines[] = sprintf(
                'By implementing the %d recommended %s — %s —',
                $enabledCount,
                $enabledCount === 1 ? 'action' : 'actions',
                implode(', and ', $enabledDescriptions)
            );
        }

        // Impact summary
        if ($projectedTotalGap <= 0) {
            $lines[] = 'your total coverage gap would be fully closed.';
        } else {
            $lines[] = sprintf(
                'your total coverage gap would reduce from %s to %s.',
                $this->formatCurrency($currentTotalGap),
                $this->formatCurrency($projectedTotalGap)
            );
        }

        // Additional premium
        if ($additionalPremium > 0) {
            $lines[] = sprintf(
                'The estimated additional monthly premium for this cover would be %s.',
                $this->formatCurrency($additionalPremium)
            );
        }

        // Disabled actions (remaining gaps)
        if (! empty($disabledActions)) {
            $disabledDescriptions = $this->describeActions($disabledActions, 'unaddressed');
            if (! empty($disabledDescriptions)) {
                $lines[] = sprintf(
                    'You have chosen not to act on %d %s, leaving the following gaps: %s.',
                    count($disabledActions),
                    count($disabledActions) === 1 ? 'recommendation' : 'recommendations',
                    implode('; ', $disabledDescriptions)
                );
            }
        }

        return [
            'summary_text' => implode(' ', $lines),
            'total_actions' => $enabledCount,
            'critical_actions' => $critical,
            'high_priority_actions' => $highPriority,
            'detailed_breakdown' => $this->buildDetailedBreakdown($enabledActions),
        ];
    }

    /**
     * Build human-readable descriptions of actions for the conclusion.
     */
    private function describeActions(array $actions, string $context): array
    {
        $descriptions = [];

        foreach ($actions as $action) {
            $category = strtolower($action['category'] ?? '');
            $coverageAmount = (float) ($action['impact_parameters']['coverage_amount'] ?? 0);

            if (str_contains($category, 'life')) {
                $descriptions[] = $context === 'enabled'
                    ? sprintf('adding %s life insurance cover', $this->formatCurrency($coverageAmount))
                    : sprintf('a life insurance shortfall of %s', $this->formatCurrency($coverageAmount));
            } elseif (str_contains($category, 'critical')) {
                $descriptions[] = $context === 'enabled'
                    ? sprintf('adding %s critical illness cover', $this->formatCurrency($coverageAmount))
                    : sprintf('a critical illness shortfall of %s', $this->formatCurrency($coverageAmount));
            } elseif (str_contains($category, 'income')) {
                $descriptions[] = $context === 'enabled'
                    ? sprintf('adding %s per month income protection', $this->formatCurrency($coverageAmount))
                    : sprintf('an income protection shortfall of %s per month', $this->formatCurrency($coverageAmount));
            }
        }

        return $descriptions;
    }

    private function buildEmptyExecutiveSummary(): array
    {
        return [
            'narrative' => 'Complete your protection profile to receive a personalised protection analysis.',
            'key_metrics' => [],
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
