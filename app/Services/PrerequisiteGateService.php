<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Centralised prerequisite enforcement for all module analysis, tool execution,
 * and advice generation. Physically blocks execution until required data exists.
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

    public function canAnalyseProtection(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        $totalIncome = $this->calculateTotalIncome($user);
        if ($totalIncome <= 0 && ! $user->employment_status) {
            $missing[] = 'annual income or employment status';
            $actions[] = ['label' => 'Add your income details', 'route' => '/profile'];
        }

        if (! $user->marital_status) {
            $missing[] = 'marital status';
            $actions[] = ['label' => 'Set your marital status', 'route' => '/profile'];
        }

        $dependants = $user->familyMembers()->where('relationship', 'child')->count();
        if ($dependants === 0 && ! $user->marital_status) {
            // Only flag dependants if marital status also missing — single people may legitimately have no dependants
        }

        return $this->gate($missing, $actions, 'protection');
    }

    public function canAnalyseSavings(User $user): array
    {
        $missing = [];
        $actions = [];

        $hasExpenditure = ($user->monthly_expenditure && $user->monthly_expenditure > 0)
            || ($user->annual_expenditure && $user->annual_expenditure > 0);

        if (! $hasExpenditure) {
            $missing[] = 'monthly or annual expenditure';
            $actions[] = ['label' => 'Add your expenditure', 'route' => '/profile'];
        }

        return $this->gate($missing, $actions, 'savings');
    }

    public function canAnalyseRetirement(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        if (! $user->retirement_date) {
            $missing[] = 'target retirement date';
            $actions[] = ['label' => 'Set your retirement date', 'route' => '/profile'];
        }

        $totalIncome = $this->calculateTotalIncome($user);
        if ($totalIncome <= 0) {
            $missing[] = 'annual income';
            $actions[] = ['label' => 'Add your income details', 'route' => '/profile'];
        }

        $hasPensions = $user->dcPensions()->exists() || $user->dbPensions()->exists();
        if (! $hasPensions) {
            $missing[] = 'at least one pension record';
            $actions[] = ['label' => 'Add a pension', 'route' => '/net-worth/retirement'];
        }

        return $this->gate($missing, $actions, 'retirement');
    }

    public function canAnalyseInvestment(User $user): array
    {
        $missing = [];
        $actions = [];

        if (! $user->date_of_birth) {
            $missing[] = 'date of birth';
            $actions[] = ['label' => 'Complete your profile', 'route' => '/profile'];
        }

        $hasInvestments = $user->investmentAccounts()->exists();
        if (! $hasInvestments) {
            $missing[] = 'at least one investment account';
            $actions[] = ['label' => 'Add an investment account', 'route' => '/net-worth/investments'];
        }

        return $this->gate($missing, $actions, 'investment');
    }

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

    public function canAnalyseGoals(User $user): array
    {
        $missing = [];
        $actions = [];

        $hasGoals = $user->goals()->exists();
        if (! $hasGoals) {
            $missing[] = 'at least one goal';
            $actions[] = ['label' => 'Create a goal', 'route' => '/goals'];
        }

        return $this->gate($missing, $actions, 'goals');
    }

    public function canAnalyseTax(User $user): array
    {
        $missing = [];
        $actions = [];

        $totalIncome = $this->calculateTotalIncome($user);
        if ($totalIncome <= 0) {
            $missing[] = 'annual income';
            $actions[] = ['label' => 'Add your income details', 'route' => '/profile'];
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

    /**
     * Check if a tool can be executed given the user's data state.
     */
    public function canExecuteTool(string $toolName, array $input, User $user): array
    {
        return match ($toolName) {
            'get_module_analysis' => $this->enforce($input['module'] ?? '', $user),
            'run_what_if_scenario' => $this->canRunScenario($input['module'] ?? '', $user),
            'get_recommendations' => $this->canGetRecommendations($user),
            'generate_financial_plan' => $this->canGenerateHolisticPlan($user),
            'get_tax_information' => $this->pass(), // Tax lookups always allowed
            'navigate_to_page' => $this->pass(), // Navigation always allowed
            // Entity creation tools — always allowed (no prereqs for creation)
            'create_goal', 'create_life_event', 'create_savings_account',
            'create_investment_account', 'create_pension', 'create_property',
            'create_mortgage', 'create_protection_policy', 'create_estate_asset',
            'create_estate_liability', 'create_estate_gift' => $this->pass(),
            default => $this->pass(),
        };
    }

    /**
     * Check if a scenario can be run (requires base analysis to exist).
     */
    public function canRunScenario(string $module, User $user): array
    {
        return $this->enforce($module, $user);
    }

    /**
     * Check if recommendations can be generated (at least one module must be ready).
     */
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

    /**
     * Check if the AI can advise on a particular topic.
     */
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

        // General topics are always allowed
        return $this->pass();
    }

    // ─── Data completeness summary for AI prompt ─────────────────────

    /**
     * Build a data completeness summary for inclusion in the AI system prompt.
     * Shows which modules are READY vs BLOCKED and what data is missing.
     */
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

    /**
     * Build a gate response.
     */
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

    /**
     * Return a passing gate.
     */
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
     * Calculate total annual income from all sources.
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
     * Deduplicate actions by route.
     */
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
