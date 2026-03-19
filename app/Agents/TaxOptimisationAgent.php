<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\User;
use App\Services\Tax\TaxOptimisationService;
use App\Services\TaxConfigService;

/**
 * Tax Optimisation Agent
 *
 * Orchestrates cross-module tax analysis covering ISA allowance usage,
 * pension Annual Allowance and carry forward, Capital Gains Tax planning,
 * and spousal optimisation. Generates actionable, prioritised strategies.
 */
class TaxOptimisationAgent extends BaseAgent
{
    public function __construct(
        private readonly TaxOptimisationService $optimisationService,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Analyse user's tax position across all modules.
     */
    public function analyze(int $userId): array
    {
        return $this->rememberForUser($userId, 'analysis', function () use ($userId) {
            $user = User::find($userId);

            if (! $user) {
                return $this->response(false, 'User not found');
            }

            $allowanceUsage = $this->optimisationService->analyzeAllowanceUsage($user);
            $strategies = $this->optimisationService->generateStrategies($user);

            return $this->response(true, 'Tax optimisation analysis completed', [
                'tax_year' => $this->taxConfig->getTaxYear(),
                'allowance_usage' => $allowanceUsage,
                'strategies' => $strategies['strategies'],
                'total_estimated_saving' => $strategies['total_estimated_saving'],
                'strategy_count' => $strategies['strategy_count'],
            ]);
        });
    }

    /**
     * Generate prioritised tax recommendations from analysis data.
     */
    public function generateRecommendations(array $analysisData): array
    {
        $data = $analysisData['data'] ?? $analysisData;
        $strategies = $data['strategies'] ?? [];

        $recommendations = [];
        foreach ($strategies as $strategy) {
            $recommendations[] = [
                'module' => 'tax_optimisation',
                'type' => $strategy['type'],
                'priority' => $strategy['priority'],
                'title' => $strategy['title'],
                'description' => $strategy['description'],
                'action' => $strategy['action'],
                'estimated_saving' => $strategy['estimated_annual_saving'] ?? 0,
                'urgency_score' => $this->mapPriorityToUrgency($strategy['priority']),
            ];
        }

        return [
            'recommendation_count' => count($recommendations),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Build what-if scenarios for tax planning.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $user = User::find($userId);

        if (! $user) {
            return $this->response(false, 'User not found');
        }

        $scenarios = [];
        $allowanceUsage = $this->optimisationService->analyzeAllowanceUsage($user);
        $isaRemaining = $allowanceUsage['isa']['remaining'];
        $pensionRemaining = $allowanceUsage['pension_annual_allowance']['remaining_allowance']
            + $allowanceUsage['pension_annual_allowance']['carry_forward_available'];

        // Scenario 1: Max ISA usage
        if ($isaRemaining > 0) {
            $scenarios[] = [
                'name' => 'Maximise ISA Contributions',
                'description' => sprintf('Invest the full remaining %s ISA allowance', '£'.number_format($isaRemaining, 0)),
                'potential_tax_saved' => round($isaRemaining * ($this->taxConfig->get('assumptions.investment_growth.balanced_portfolio', 0.04)) * ($this->taxConfig->get('income_tax.bands.0.rate', 0.20)), 2),
                'action_required' => 'Fund ISA before end of tax year',
            ];
        }

        // Scenario 2: Max pension contributions
        if ($pensionRemaining > 0) {
            $scenarios[] = [
                'name' => 'Maximise Pension Contributions',
                'description' => sprintf('Use the remaining %s pension Annual Allowance', '£'.number_format($pensionRemaining, 0)),
                'potential_tax_saved' => round($pensionRemaining * ($this->taxConfig->get('income_tax.bands.1.rate', 0.40)), 2),
                'action_required' => 'Increase pension contributions',
            ];
        }

        // Scenario 3: Staged CGT realisation
        $cgtExcess = $allowanceUsage['capital_gains']['excess_gains'];
        if ($cgtExcess > 0) {
            $scenarios[] = [
                'name' => 'Staged Capital Gains Realisation',
                'description' => 'Realise gains up to the annual exempt amount each tax year',
                'potential_tax_saved' => round($allowanceUsage['capital_gains']['annual_exempt_amount'] * ($this->taxConfig->get('capital_gains_tax.higher_rate', 0.20)), 2),
                'action_required' => 'Sell and repurchase holdings within CGT allowance',
            ];
        }

        return $this->response(true, 'Tax scenarios generated', [
            'scenario_count' => count($scenarios),
            'scenarios' => $scenarios,
        ]);
    }

    /**
     * Map strategy priority to a numeric urgency score for the conflict resolver.
     */
    private function mapPriorityToUrgency(string $priority): int
    {
        return match ($priority) {
            'high' => 80,
            'medium' => 60,
            'low' => 40,
            default => 50,
        };
    }
}
