<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Jobs\RunMonteCarloSimulation;
use App\Models\Investment\InvestmentScenario;
use Illuminate\Support\Str;

class ScenarioService
{
    // Illustrative amounts for pre-built scenario templates (not regulatory values)
    private const EXAMPLE_LUMP_SUM = 50000;

    private const EXAMPLE_EMERGENCY_WITHDRAWAL = 20000;

    /**
     * Get pre-built scenario templates
     */
    public function getTemplates(): array
    {
        return [
            [
                'id' => 'market_crash',
                'name' => 'Market Crash Recovery',
                'description' => 'Model a 30% market decline followed by gradual recovery over 5 years',
                'category' => 'market_conditions',
                'parameters' => [
                    'return_adjustment' => -30,
                    'recovery_period_years' => 5,
                    'volatility_increase' => 50,
                ],
            ],
            [
                'id' => 'early_retirement',
                'name' => 'Early Retirement at 55',
                'description' => 'Test portfolio sustainability if retiring 10 years early',
                'category' => 'retirement',
                'parameters' => [
                    'retirement_age' => 55,
                    'life_expectancy' => 90,
                    'withdrawal_rate' => 3.5,
                ],
            ],
            [
                'id' => 'increased_contributions',
                'name' => 'Double Monthly Contributions',
                'description' => 'Project growth with doubled monthly investment contributions',
                'category' => 'contributions',
                'parameters' => [
                    'contribution_multiplier' => 2.0,
                    'duration_years' => 10,
                ],
            ],
            [
                'id' => 'fee_reduction',
                'name' => 'Lower Fee Portfolio',
                'description' => 'Compare returns with 0.5% lower annual fees over 20 years',
                'category' => 'fees',
                'parameters' => [
                    'fee_reduction' => 0.5,
                    'projection_years' => 20,
                ],
            ],
            [
                'id' => 'aggressive_allocation',
                'name' => '100% Equities',
                'description' => 'Model returns and volatility with fully aggressive allocation',
                'category' => 'allocation',
                'parameters' => [
                    'equities_percentage' => 100,
                    'bonds_percentage' => 0,
                    'expected_return' => 8.0,
                    'volatility' => 18.0,
                ],
            ],
            [
                'id' => 'conservative_allocation',
                'name' => '60/40 Balanced',
                'description' => 'Model returns with conservative 60% equities / 40% bonds allocation',
                'category' => 'allocation',
                'parameters' => [
                    'equities_percentage' => 60,
                    'bonds_percentage' => 40,
                    'expected_return' => 6.0,
                    'volatility' => 10.0,
                ],
            ],
            [
                'id' => 'lump_sum_contribution',
                'name' => 'Large One-Time Investment',
                'description' => 'Add £50,000 lump sum and project long-term impact',
                'category' => 'contributions',
                'parameters' => [
                    'lump_sum_amount' => self::EXAMPLE_LUMP_SUM,
                    'timing_year' => 0,
                ],
            ],
            [
                'id' => 'emergency_withdrawal',
                'name' => 'Emergency £20k Withdrawal',
                'description' => 'Model impact of withdrawing £20,000 in year 5',
                'category' => 'withdrawals',
                'parameters' => [
                    'withdrawal_amount' => self::EXAMPLE_EMERGENCY_WITHDRAWAL,
                    'withdrawal_year' => 5,
                ],
            ],
        ];
    }

    /**
     * Get template by ID
     */
    public function getTemplate(string $templateId): ?array
    {
        $templates = $this->getTemplates();
        foreach ($templates as $template) {
            if ($template['id'] === $templateId) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Create a new scenario
     */
    public function createScenario(
        int $userId,
        string $scenarioName,
        ?string $description,
        string $scenarioType,
        ?string $templateName,
        array $parameters
    ): InvestmentScenario {
        return InvestmentScenario::create([
            'user_id' => $userId,
            'scenario_name' => $scenarioName,
            'description' => $description,
            'scenario_type' => $scenarioType,
            'template_name' => $templateName,
            'parameters' => $parameters,
            'status' => 'draft',
        ]);
    }

    /**
     * Run scenario simulation (launch Monte Carlo)
     */
    public function runScenario(InvestmentScenario $scenario): string
    {
        // Generate unique job ID
        $jobId = Str::uuid()->toString();

        // Update scenario with job ID and status
        $scenario->update([
            'status' => 'running',
            'monte_carlo_job_id' => $jobId,
        ]);

        // Dispatch Monte Carlo job with scenario parameters
        RunMonteCarloSimulation::dispatch($jobId, [
            'user_id' => $scenario->user_id,
            'scenario_id' => $scenario->id,
            'parameters' => $scenario->parameters,
            'iterations' => 1000,
        ]);

        return $jobId;
    }

    /**
     * Compare multiple scenarios
     */
    public function compareScenarios(int $userId, array $scenarioIds): array
    {
        $scenarios = InvestmentScenario::where('user_id', $userId)
            ->whereIn('id', $scenarioIds)
            ->where('status', 'completed')
            ->get();

        if ($scenarios->count() < 2) {
            throw new \Exception('At least 2 completed scenarios are required for comparison');
        }

        $comparison = [
            'scenarios' => [],
            'metrics_comparison' => [],
        ];

        foreach ($scenarios as $scenario) {
            $results = $scenario->results;

            $comparison['scenarios'][] = [
                'id' => $scenario->id,
                'name' => $scenario->scenario_name,
                'description' => $scenario->description,
                'type' => $scenario->scenario_type,
                'template' => $scenario->template_name,
            ];

            // Extract key metrics for comparison
            if ($results) {
                $comparison['metrics_comparison'][] = [
                    'scenario_id' => $scenario->id,
                    'median_final_value' => $results['percentiles']['p50'] ?? null,
                    'success_rate' => $results['success_rate'] ?? null,
                    'expected_return' => $results['expected_return'] ?? null,
                    'risk_volatility' => $results['volatility'] ?? null,
                    'best_case' => $results['percentiles']['p90'] ?? null,
                    'worst_case' => $results['percentiles']['p20'] ?? null,
                ];
            }
        }

        // Calculate relative differences
        if (count($comparison['metrics_comparison']) >= 2) {
            $baseline = $comparison['metrics_comparison'][0];
            $comparison['relative_differences'] = [];

            for ($i = 1; $i < count($comparison['metrics_comparison']); $i++) {
                $compared = $comparison['metrics_comparison'][$i];
                $comparison['relative_differences'][] = [
                    'scenario_id' => $compared['scenario_id'],
                    'median_value_difference' => $compared['median_final_value'] - $baseline['median_final_value'],
                    'median_value_difference_pct' => (($compared['median_final_value'] - $baseline['median_final_value']) / $baseline['median_final_value']) * 100,
                    'success_rate_difference' => $compared['success_rate'] - $baseline['success_rate'],
                ];
            }
        }

        return $comparison;
    }

    /**
     * Save/bookmark a scenario
     */
    public function saveScenario(InvestmentScenario $scenario): void
    {
        $scenario->update(['is_saved' => true]);
    }

    /**
     * Unsave/unbookmark a scenario
     */
    public function unsaveScenario(InvestmentScenario $scenario): void
    {
        $scenario->update(['is_saved' => false]);
    }

    /**
     * Delete a scenario
     */
    public function deleteScenario(InvestmentScenario $scenario): void
    {
        $scenario->delete();
    }

    /**
     * Get scenarios for a user with optional filters
     */
    public function getUserScenarios(
        int $userId,
        ?string $status = null,
        ?string $type = null,
        ?bool $savedOnly = null
    ): array {
        $query = InvestmentScenario::where('user_id', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('scenario_type', $type);
        }

        if ($savedOnly) {
            $query->where('is_saved', true);
        }

        $scenarios = $query->orderBy('created_at', 'desc')->get();

        return [
            'scenarios' => $scenarios,
            'stats' => [
                'total' => $scenarios->count(),
                'draft' => $scenarios->where('status', 'draft')->count(),
                'running' => $scenarios->where('status', 'running')->count(),
                'completed' => $scenarios->where('status', 'completed')->count(),
                'failed' => $scenarios->where('status', 'failed')->count(),
                'saved' => $scenarios->where('is_saved', true)->count(),
            ],
        ];
    }
}
