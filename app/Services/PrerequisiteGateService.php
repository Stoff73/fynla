<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Estate\EstateDataReadinessService;
use App\Services\Investment\Recommendation\DataReadinessService as InvestmentDataReadinessService;
use App\Services\Protection\ProtectionDataReadinessService;
use App\Services\Retirement\RetirementDataReadinessService;
use App\Services\Savings\SavingsDataReadinessService;

/**
 * Centralised prerequisite enforcement for all module analysis, tool execution,
 * and advice generation. Physically blocks execution until required data exists.
 *
 * IMPORTANT: Every check in this service is verified against the corresponding
 * module's DataReadinessService blocking checks. Only fields that the agent
 * actually blocks on are checked here. See recon.md for the full audit.
 *
 * Data sources verified against database schema (2026-03-16):
 * - User fields: users table columns
 * - ExpenditureProfile: expenditure_profiles.total_monthly_expenditure
 * - RiskProfile: risk_profiles table (user_id)
 * - RetirementProfile: retirement_profiles.target_retirement_age
 * - Retirement target: users.retirement_date, users.target_retirement_age, retirement_profiles.target_retirement_age
 * - Income: users.annual_employment_income + annual_self_employment_income + annual_rental_income
 *           + annual_dividend_income + annual_interest_income + annual_other_income + annual_trust_income
 */
class PrerequisiteGateService
{
    public function __construct(
        private readonly ProtectionDataReadinessService $protectionReadiness,
        private readonly SavingsDataReadinessService $savingsReadiness,
        private readonly RetirementDataReadinessService $retirementReadiness,
        private readonly InvestmentDataReadinessService $investmentReadiness,
        private readonly EstateDataReadinessService $estateReadiness,
    ) {}

    /**
     * Enforce prerequisites for a named action.
     *
     * @return array{can_proceed: bool, missing: array, guidance: string, required_actions: array}
     */
    public function enforce(string $action, User $user): array
    {
        return match ($action) {
            'protection' => $this->canAnalyseProtection($user),
            'savings' => $this->canAnalyseSavings($user),
            'retirement' => $this->canAnalyseRetirement($user),
            'investment' => $this->canAnalyseInvestment($user),
            'estate' => $this->canAnalyseEstate($user),
            'goals' => $this->canAnalyseGoals($user),
            'tax_optimisation' => $this->canAnalyseTax($user),
            'holistic_plan' => $this->canGenerateHolisticPlan($user),
            default => $this->pass(),
        };
    }

    // ─── Module-level gates ──────────────────────────────────────────
    // Each gate mirrors the BLOCKING checks from the corresponding
    // module's DataReadinessService. Warning-level checks are not gated.

    /**
     * Protection: delegates to ProtectionDataReadinessService
     */
    public function canAnalyseProtection(User $user): array
    {
        return $this->delegateToReadiness($this->protectionReadiness->assess($user), 'protection');
    }

    /**
     * Savings: delegates to SavingsDataReadinessService
     */
    public function canAnalyseSavings(User $user): array
    {
        return $this->delegateToReadiness($this->savingsReadiness->assess($user), 'savings');
    }

    /**
     * Retirement: delegates to RetirementDataReadinessService
     */
    public function canAnalyseRetirement(User $user): array
    {
        return $this->delegateToReadiness($this->retirementReadiness->assess($user), 'retirement');
    }

    /**
     * Investment: delegates to InvestmentDataReadinessService
     */
    public function canAnalyseInvestment(User $user): array
    {
        return $this->delegateToReadiness($this->investmentReadiness->assess($user), 'investment');
    }

    /**
     * Estate: delegates to EstateDataReadinessService
     */
    public function canAnalyseEstate(User $user): array
    {
        return $this->delegateToReadiness($this->estateReadiness->assess($user), 'estate');
    }

    /**
     * Goals: at least one goal must exist.
     * No DataReadinessService exists for goals — GoalsAgent checks has_goals directly.
     */
    public function canAnalyseGoals(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->goals()->exists()) {
            $missing[] = 'at least one goal';
            $actions[] = ['label' => 'Create a goal', 'route' => '/goals'];
        }

        return $this->gate($missing, $actions, 'goals');
    }

    /**
     * Tax: income and employment_status.
     * No DataReadinessService exists for tax — TaxOptimisationAgent requires income for band determination.
     */
    public function canAnalyseTax(User $user): array
    {
        $missing = [];
        $actions = [];

        if ($this->calculateTotalIncome($user) <= 0) {
            $missing[] = 'annual income';
            $actions[] = ['label' => 'Add your income details', 'route' => '/valuable-info?section=income'];
        }

        if (! $user->employment_status) {
            $missing[] = 'employment status';
            $actions[] = ['label' => 'Set your employment status', 'route' => '/profile'];
        }

        return $this->gate($missing, $actions, 'tax optimisation');
    }

    // ─── Holistic plan gate ──────────────────────────────────────────

    public function canGenerateHolisticPlan(User $user): array
    {
        $allMissing = [];
        $allActions = [];
        $blockedModules = [];

        $modules = [
            'protection' => $this->canAnalyseProtection($user),
            'savings' => $this->canAnalyseSavings($user),
            'retirement' => $this->canAnalyseRetirement($user),
            'investment' => $this->canAnalyseInvestment($user),
            'estate' => $this->canAnalyseEstate($user),
        ];

        foreach ($modules as $module => $gate) {
            if (! $gate['can_proceed']) {
                $blockedModules[] = $module;
                foreach ($gate['missing'] as $item) {
                    if (! in_array($item, $allMissing)) {
                        $allMissing[] = $item;
                    }
                }
                foreach ($gate['required_actions'] as $action) {
                    $allActions[] = $action;
                }
            }
        }

        if (! empty($blockedModules)) {
            $moduleList = implode(', ', $blockedModules);

            return [
                'can_proceed' => false,
                'missing' => $allMissing,
                'guidance' => "A holistic financial plan requires data across all modules. The following modules are missing data: {$moduleList}. Please complete the missing information first.",
                'required_actions' => $this->deduplicateActions($allActions),
                'blocked_modules' => $blockedModules,
            ];
        }

        return $this->pass();
    }

    // ─── Tool execution gates ────────────────────────────────────────

    public function canExecuteTool(string $toolName, array $input, User $user): array
    {
        return match ($toolName) {
            'get_module_analysis' => $this->enforce($input['module'] ?? '', $user),
            'run_what_if_scenario' => $this->canRunScenario($input['module'] ?? '', $user),
            'get_recommendations' => $this->canGetRecommendations($user),
            'generate_financial_plan' => $this->canGenerateHolisticPlan($user),
            'get_tax_information' => $this->pass(),
            'navigate_to_page' => $this->pass(),
            'list_goals' => $this->pass(),
            'list_life_events' => $this->pass(),
            'create_goal', 'create_life_event', 'create_savings_account',
            'create_investment_account', 'create_pension', 'create_property',
            'create_mortgage', 'create_protection_policy', 'create_asset',
            'create_liability', 'create_estate_gift',
            'create_family_member', 'create_trust', 'create_business_interest', 'create_chattel',
            'update_record', 'delete_record', 'update_profile' => $this->pass(),
            default => $this->pass(),
        };
    }

    public function canRunScenario(string $module, User $user): array
    {
        return $this->enforce($module, $user);
    }

    public function canGetRecommendations(User $user): array
    {
        $modules = ['protection', 'savings', 'retirement', 'investment', 'estate', 'goals', 'tax_optimisation'];
        $readyModules = [];

        foreach ($modules as $module) {
            $gate = $this->enforce($module, $user);
            if ($gate['can_proceed']) {
                $readyModules[] = $module;
            }
        }

        if (empty($readyModules)) {
            return [
                'can_proceed' => false,
                'missing' => ['sufficient data in at least one financial module'],
                'guidance' => 'Recommendations require data in at least one area of your financial plan. Please add some financial information first.',
                'required_actions' => [['label' => 'Add financial data', 'route' => '/dashboard']],
            ];
        }

        return $this->pass();
    }

    // ─── Advice-level gates ──────────────────────────────────────────

    public function canAdviseOn(string $topic, User $user): array
    {
        $moduleMap = [
            'protection' => 'protection',
            'life_insurance' => 'protection',
            'income_protection' => 'protection',
            'critical_illness' => 'protection',
            'savings' => 'savings',
            'emergency_fund' => 'savings',
            'isa' => 'savings',
            'retirement' => 'retirement',
            'pension' => 'retirement',
            'investment' => 'investment',
            'portfolio' => 'investment',
            'estate' => 'estate',
            'inheritance_tax' => 'estate',
            'will' => 'estate',
            'goals' => 'goals',
            'tax' => 'tax_optimisation',
        ];

        $module = $moduleMap[$topic] ?? null;

        if ($module) {
            return $this->enforce($module, $user);
        }

        return $this->pass();
    }

    // ─── Data completeness summary for AI prompt ─────────────────────

    public function buildCompletenessContext(User $user): string
    {
        $assessments = $this->assessAll($user);
        $gates = [
            'Protection' => $this->canAnalyseProtection($user),
            'Savings' => $this->canAnalyseSavings($user),
            'Retirement' => $this->canAnalyseRetirement($user),
            'Investment' => $this->canAnalyseInvestment($user),
            'Estate' => $this->canAnalyseEstate($user),
            'Goals' => $this->canAnalyseGoals($user),
            'Tax Optimisation' => $this->canAnalyseTax($user),
        ];

        $lines = [];
        foreach ($gates as $name => $gate) {
            $key = strtolower(str_replace(' ', '_', $name));
            $assessment = $assessments[$key] ?? null;

            if ($gate['can_proceed']) {
                $pct = $assessment['completeness_percent'] ?? 100;
                $warnings = count($assessment['warnings'] ?? []);
                $suffix = $warnings > 0 ? " ({$warnings} optional fields missing)" : '';
                $lines[] = "- {$name}: READY ({$pct}% complete{$suffix})";
            } else {
                $blockingItems = [];
                foreach (($assessment['blocking'] ?? []) as $check) {
                    if (! $check['passed']) {
                        $blockingItems[] = $check['key'].': '.$check['message'];
                    }
                }
                $blockingList = ! empty($blockingItems) ? implode('; ', $blockingItems) : implode(', ', $gate['missing']);
                $route = $gate['required_actions'][0]['route'] ?? '/profile';
                $lines[] = "- {$name}: BLOCKED -- {$blockingList} -- navigate user to: {$route}";
            }
        }

        return implode("\n", $lines);
    }

    // ─── Full assessment for enriched completeness ──────────────────

    /**
     * Return the full DataReadiness assessment for all 5 modules.
     * Used by the completeness endpoint and AI prompt context.
     */
    public function assessAll(User $user): array
    {
        return [
            'protection' => $this->normaliseAssessment($this->protectionReadiness->assess($user)),
            'savings' => $this->normaliseAssessment($this->savingsReadiness->assess($user)),
            'retirement' => $this->normaliseAssessment($this->retirementReadiness->assess($user)),
            'investment' => $this->normaliseAssessment($this->investmentReadiness->assess($user)),
            'estate' => $this->normaliseAssessment($this->estateReadiness->assess($user)),
        ];
    }

    /**
     * Normalise DataReadiness assessment to a consistent shape.
     * Different services use slightly different key names.
     */
    private function normaliseAssessment(array $assessment): array
    {
        $totalChecks = $assessment['total_checks'] ?? count($assessment['checks'] ?? []);
        $passedChecks = $assessment['passed_checks']
            ?? count(array_filter($assessment['checks'] ?? [], fn ($c) => $c['passed'] ?? false));

        $assessment['total_checks'] = $totalChecks;
        $assessment['passed_checks'] = $passedChecks;
        $assessment['completeness_percent'] = $assessment['completeness_percent']
            ?? ($totalChecks > 0 ? (int) round(($passedChecks / $totalChecks) * 100) : 0);

        return $assessment;
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Convert a DataReadinessService assessment into a gate response.
     * Extracts blocking checks as the missing items with form links as actions.
     */
    private function delegateToReadiness(array $assessment, string $moduleName): array
    {
        if ($assessment['can_proceed']) {
            return $this->pass();
        }

        $missing = [];
        $actions = [];

        foreach ($assessment['blocking'] as $check) {
            // Only include checks that FAILED (some services include passed checks too)
            if (! $check['passed']) {
                $missing[] = $check['message'];
                $actions[] = [
                    'label' => $check['message'],
                    'route' => $check['form_link'] ?? '/profile',
                ];
            }
        }

        return $this->gate($missing, $actions, $moduleName);
    }

    private function gate(array $missing, array $actions, string $moduleName): array
    {
        if (empty($missing)) {
            return $this->pass();
        }

        $missingList = implode(', ', $missing);

        return [
            'can_proceed' => false,
            'missing' => $missing,
            'guidance' => "To analyse your {$moduleName}, the following information is needed: {$missingList}.",
            'required_actions' => $this->deduplicateActions($actions),
        ];
    }

    private function pass(): array
    {
        return [
            'can_proceed' => true,
            'missing' => [],
            'guidance' => '',
            'required_actions' => [],
        ];
    }

    /**
     * Calculate total annual income from all sources on users table.
     * Fields: annual_employment_income, annual_self_employment_income, annual_rental_income,
     *         annual_dividend_income, annual_interest_income, annual_other_income, annual_trust_income
     */
    private function calculateTotalIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }

    private function deduplicateActions(array $actions): array
    {
        $seen = [];
        $unique = [];

        foreach ($actions as $action) {
            $key = $action['route'] ?? '';
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $action;
            }
        }

        return $unique;
    }
}
