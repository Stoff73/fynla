<?php

declare(strict_types=1);

namespace App\Services\WhatIf;

use App\Agents\EstateAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\User;
use App\Models\WhatIfScenario;

class WhatIfScenarioService
{
    /**
     * Parameter-to-module mapping.
     * Determines which modules are affected by each parameter type.
     */
    private const PARAMETER_MODULE_MAP = [
        'retirement_age' => ['retirement', 'investment', 'estate'],
        'pension_contribution' => ['retirement', 'investment', 'estate'],
        'sell_property' => ['property', 'estate', 'savings', 'retirement'],
        'buy_property' => ['property', 'estate', 'savings', 'retirement'],
        'divorce' => ['protection', 'estate', 'retirement', 'savings'],
        'marriage' => ['protection', 'estate', 'retirement', 'savings'],
        'new_child' => ['protection', 'savings', 'estate'],
        'income_change' => ['savings', 'protection', 'retirement'],
        'job_loss' => ['savings', 'protection', 'retirement'],
        'inheritance' => ['estate', 'savings', 'investment'],
    ];

    /**
     * Module metrics extracted from analysis results.
     */
    private const MODULE_METRICS = [
        'retirement' => ['projected_income', 'capital_at_retirement', 'years_income_lasts'],
        'investment' => ['portfolio_value', 'projected_growth'],
        'estate' => ['taxable_estate', 'iht_liability', 'effective_iht_rate'],
        'protection' => ['total_coverage', 'coverage_gaps_count', 'monthly_premiums'],
        'savings' => ['total_savings', 'emergency_fund_runway'],
    ];

    public function __construct(
        private readonly RetirementAgent $retirementAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly ProtectionAgent $protectionAgent,
        private readonly EstateAgent $estateAgent,
        private readonly InvestmentAgent $investmentAgent
    ) {}

    /**
     * Determine which modules are affected by the given parameters.
     *
     * @param  array<string, mixed>  $parameters  Scenario parameter key-value pairs
     * @return array<int, string> List of affected module names
     */
    public function detectAffectedModules(array $parameters): array
    {
        $modules = [];
        foreach ($parameters as $key => $value) {
            if (isset(self::PARAMETER_MODULE_MAP[$key])) {
                $modules = array_merge($modules, self::PARAMETER_MODULE_MAP[$key]);
            }
        }

        return array_values(array_unique($modules));
    }

    /**
     * Calculate a live comparison between current state and what-if scenario.
     *
     * Runs agent analysis on the real user for the "Now" column,
     * then applies parameter overrides to the user model, re-runs analysis
     * for the "What If" column, and calculates deltas.
     *
     * @param  WhatIfScenario  $scenario  The scenario to calculate
     * @return array{scenario: array, affected_modules: array, current: array, what_if: array, deltas: array, ai_narrative: ?string}
     */
    public function calculateComparison(WhatIfScenario $scenario): array
    {
        $user = User::findOrFail($scenario->user_id);
        $affectedModules = $scenario->affected_modules;
        $parameters = $scenario->parameters;

        $current = [];
        $whatIf = [];
        $deltas = [];

        foreach ($affectedModules as $module) {
            $agent = $this->getAgentForModule($module);
            if (! $agent) {
                continue;
            }

            // Current state — real user data
            $currentAnalysis = $this->extractMetrics($agent->analyze($user->id), $module);
            $current[$module] = $currentAnalysis;

            // What-if state — apply parameter overrides to user, analyze, then revert
            $originalValues = $this->applyOverrides($user, $parameters);
            $user->save();

            try {
                // Clear any cached analysis so the agent re-calculates with overrides
                $agent->invalidateUserCache($user->id);
                $whatIfAnalysis = $this->extractMetrics($agent->analyze($user->id), $module);
                $whatIf[$module] = $whatIfAnalysis;
            } finally {
                // Always revert the user model regardless of success or failure
                $this->revertOverrides($user, $originalValues);
                $user->save();
                $agent->invalidateUserCache($user->id);
            }

            // Calculate deltas between current and what-if values
            $deltas[$module] = [];
            foreach ($currentAnalysis as $key => $currentValue) {
                if (is_numeric($currentValue) && is_numeric($whatIf[$module][$key] ?? null)) {
                    $deltas[$module][$key] = round((float) $whatIf[$module][$key] - (float) $currentValue, 2);
                }
            }
        }

        return [
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'type' => $scenario->scenario_type,
                'created_at' => $scenario->created_at->toIso8601String(),
            ],
            'affected_modules' => $affectedModules,
            'current' => $current,
            'what_if' => $whatIf,
            'deltas' => $deltas,
            'ai_narrative' => $scenario->ai_narrative,
        ];
    }

    /**
     * Create a new scenario and return its comparison.
     *
     * @param  User  $user  The user creating the scenario
     * @param  array{name: string, scenario_type?: string, parameters: array, affected_modules?: array, created_via?: string, ai_narrative?: string}  $data
     * @return array Comparison result with scenario_id
     */
    public function createScenario(User $user, array $data): array
    {
        $affectedModules = $data['affected_modules'] ?? $this->detectAffectedModules($data['parameters']);

        $scenario = WhatIfScenario::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'scenario_type' => $data['scenario_type'] ?? 'custom',
            'parameters' => $data['parameters'],
            'affected_modules' => $affectedModules,
            'created_via' => $data['created_via'] ?? 'manual',
            'ai_narrative' => $data['ai_narrative'] ?? null,
        ]);

        $comparison = $this->calculateComparison($scenario);

        return array_merge($comparison, ['scenario_id' => $scenario->id]);
    }

    /**
     * Get the appropriate agent for a module.
     */
    private function getAgentForModule(string $module): ?object
    {
        return match ($module) {
            'retirement' => $this->retirementAgent,
            'savings' => $this->savingsAgent,
            'protection' => $this->protectionAgent,
            'estate' => $this->estateAgent,
            'investment' => $this->investmentAgent,
            default => null,
        };
    }

    /**
     * Extract key metrics from an analysis result for comparison display.
     *
     * Navigates the nested analysis response to pull out the specific
     * metrics defined in MODULE_METRICS for side-by-side comparison.
     *
     * @param  array  $analysis  Raw agent analysis result
     * @param  string  $module  Module name
     * @return array<string, mixed> Extracted metric key-value pairs
     */
    private function extractMetrics(array $analysis, string $module): array
    {
        $data = $analysis['data'] ?? $analysis;
        $metrics = [];

        switch ($module) {
            case 'retirement':
                $metrics = [
                    'projected_income' => $data['income_projection']['annual_income'] ?? $data['summary']['projected_income'] ?? 0,
                    'capital_at_retirement' => $data['summary']['projected_fund_value'] ?? $data['income_projection']['fund_at_retirement'] ?? 0,
                ];
                break;
            case 'estate':
                $metrics = [
                    'taxable_estate' => $data['iht']['taxable_estate'] ?? $data['summary']['estate_value'] ?? 0,
                    'iht_liability' => $data['iht']['tax_liability'] ?? $data['summary']['iht_liability'] ?? 0,
                ];
                break;
            case 'savings':
                $metrics = [
                    'total_savings' => $data['summary']['total_savings'] ?? 0,
                    'emergency_fund_runway' => $data['emergency_fund']['runway_months'] ?? 0,
                ];
                break;
            case 'protection':
                $metrics = [
                    'total_coverage' => $data['coverage']['total_life_cover'] ?? $data['summary']['total_coverage'] ?? 0,
                ];
                break;
            case 'investment':
                $metrics = [
                    'portfolio_value' => $data['summary']['total_portfolio_value'] ?? $data['summary']['total_value'] ?? 0,
                ];
                break;
        }

        return $metrics;
    }

    /**
     * Apply parameter overrides to a user model and return original values for rollback.
     *
     * @param  User  $user  The user model to modify
     * @param  array<string, mixed>  $parameters  Override parameters
     * @return array<string, mixed> Original values keyed by field name
     */
    private function applyOverrides(User $user, array $parameters): array
    {
        $originals = [];

        if (isset($parameters['retirement_age'])) {
            $originals['target_retirement_age'] = $user->target_retirement_age;
            $user->target_retirement_age = (int) $parameters['retirement_age'];
        }

        if (isset($parameters['income_change'])) {
            $originals['annual_employment_income'] = $user->annual_employment_income;
            $user->annual_employment_income = (float) $parameters['income_change'];
        }

        if (isset($parameters['pension_contribution'])) {
            $originals['pension_contribution_override'] = null;
            // Pension contributions are on DC pension models, not user — handle via cache
        }

        return $originals;
    }

    /**
     * Revert parameter overrides on a user model.
     *
     * @param  User  $user  The user model to revert
     * @param  array<string, mixed>  $originals  Original values to restore
     */
    private function revertOverrides(User $user, array $originals): void
    {
        foreach ($originals as $field => $value) {
            if (property_exists($user, $field) || $user->isFillable($field)) {
                $user->{$field} = $value;
            }
        }
    }
}
