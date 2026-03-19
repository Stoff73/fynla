<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenditureProfile;
use App\Models\Investment\RiskProfile;
use App\Models\User;

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
     * Protection blocking: date_of_birth, income, marital_status
     * Source: ProtectionDataReadinessService::blockingChecks()
     */
    public function canAnalyseProtection(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        if ($this->calculateTotalIncome($user) <= 0) {
            $missing[] = 'annual income';
            $actions[] = ['label' => 'Add your income details', 'route' => '/valuable-info?section=income'];
        }

        if (! $user->marital_status) {
            $missing[] = 'marital status';
            $actions[] = ['label' => 'Set your marital status', 'route' => '/profile'];
        }

        return $this->gate($missing, $actions, 'protection');
    }

    /**
     * Savings blocking: date_of_birth, income, expenditure (3 sources)
     * Source: SavingsDataReadinessService blocking checks
     * Expenditure resolved via: ExpenditureProfile > User.monthly_expenditure > User.annual_expenditure
     */
    public function canAnalyseSavings(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        if ($this->calculateTotalIncome($user) <= 0) {
            $missing[] = 'annual income';
            $actions[] = ['label' => 'Add your income details', 'route' => '/valuable-info?section=income'];
        }

        if (! $this->hasExpenditure($user)) {
            $missing[] = 'monthly or annual expenditure';
            $actions[] = ['label' => 'Add your expenditure', 'route' => '/valuable-info?section=expenditure'];
        }

        return $this->gate($missing, $actions, 'savings');
    }

    /**
     * Retirement blocking: date_of_birth, marital_status, risk_profile
     * Source: RetirementDataReadinessService blocking checks (date_of_birth, marital_status)
     * Plus risk_profile added per product requirement for retirement analysis.
     *
     * Note: target_retirement_age and pensions are WARNING level in the agent —
     * they do not block analysis, the agent uses defaults (State Pension age).
     */
    public function canAnalyseRetirement(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        if (! $user->marital_status) {
            $missing[] = 'marital status';
            $actions[] = ['label' => 'Set your marital status', 'route' => '/profile'];
        }

        if (! RiskProfile::where('user_id', $user->id)->exists()) {
            $missing[] = 'completed risk profile';
            $actions[] = ['label' => 'Complete your risk profile', 'route' => '/risk-profile'];
        }

        return $this->gate($missing, $actions, 'retirement');
    }

    /**
     * Investment blocking: date_of_birth, income, risk_profile, expenditure
     * Source: InvestmentDataReadinessService blocking checks
     */
    public function canAnalyseInvestment(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        if ($this->calculateTotalIncome($user) <= 0) {
            $missing[] = 'annual income';
            $actions[] = ['label' => 'Add your income details', 'route' => '/valuable-info?section=income'];
        }

        if (! RiskProfile::where('user_id', $user->id)->exists()) {
            $missing[] = 'completed risk profile';
            $actions[] = ['label' => 'Complete your risk profile', 'route' => '/risk-profile'];
        }

        if (! $this->hasExpenditure($user)) {
            $missing[] = 'monthly or annual expenditure';
            $actions[] = ['label' => 'Add your expenditure', 'route' => '/valuable-info?section=expenditure'];
        }

        return $this->gate($missing, $actions, 'investment');
    }

    /**
     * Estate blocking: date_of_birth, marital_status, at_least_one_asset
     * Source: EstateDataReadinessService blocking checks
     */
    public function canAnalyseEstate(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        if (! $user->marital_status) {
            $missing[] = 'marital status';
            $actions[] = ['label' => 'Set your marital status', 'route' => '/profile'];
        }

        $hasAssets = $user->properties()->exists()
            || $user->investmentAccounts()->exists()
            || $user->savingsAccounts()->exists();
        if (! $hasAssets) {
            $missing[] = 'at least one asset (property, investment, or savings account)';
            $actions[] = ['label' => 'Add an asset', 'route' => '/net-worth/wealth-summary'];
        }

        return $this->gate($missing, $actions, 'estate');
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
            'create_goal', 'create_life_event', 'create_savings_account',
            'create_investment_account', 'create_pension', 'create_property',
            'create_mortgage', 'create_protection_policy', 'create_estate_asset',
            'create_estate_liability', 'create_estate_gift',
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
        $modules = [
            'Protection' => $this->canAnalyseProtection($user),
            'Savings' => $this->canAnalyseSavings($user),
            'Retirement' => $this->canAnalyseRetirement($user),
            'Investment' => $this->canAnalyseInvestment($user),
            'Estate' => $this->canAnalyseEstate($user),
            'Goals' => $this->canAnalyseGoals($user),
            'Tax Optimisation' => $this->canAnalyseTax($user),
        ];

        $lines = [];
        foreach ($modules as $name => $gate) {
            if ($gate['can_proceed']) {
                $lines[] = "- {$name}: READY (all prerequisites met)";
            } else {
                $missingList = implode(', ', $gate['missing']);
                $route = $gate['required_actions'][0]['route'] ?? '/profile';
                $lines[] = "- {$name}: BLOCKED -- missing: {$missingList} -- navigate user to: {$route}";
            }
        }

        return implode("\n", $lines);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

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

    /**
     * Check if user has expenditure data from any of the 3 sources.
     * Mirrors the ResolvesExpenditure trait priority chain:
     * 1. ExpenditureProfile.total_monthly_expenditure
     * 2. User.monthly_expenditure
     * 3. User.annual_expenditure
     */
    private function hasExpenditure(User $user): bool
    {
        $expenditureProfile = ExpenditureProfile::where('user_id', $user->id)->first();
        if ($expenditureProfile && $expenditureProfile->total_monthly_expenditure > 0) {
            return true;
        }

        if ($user->monthly_expenditure && $user->monthly_expenditure > 0) {
            return true;
        }

        if ($user->annual_expenditure && $user->annual_expenditure > 0) {
            return true;
        }

        return false;
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
