<?php

declare(strict_types=1);

namespace App\Services\Investment\ModelPortfolio;

/**
 * Asset Allocation Optimizer
 * Optimizes asset allocation based on goals, constraints, and preferences
 *
 * Optimization Approaches:
 * - Age-based (100 minus age rule variants)
 * - Goal-based (time horizon driven)
 * - Constrained optimization (user preferences)
 * - Risk-adjusted allocation
 */
class AssetAllocationOptimizer
{
    /**
     * Optimize allocation based on age
     *
     * @param  int  $age  Current age
     * @param  string  $rule  Rule to use (100_minus_age, 110_minus_age, 120_minus_age)
     * @return array Optimized allocation
     */
    public function optimizeByAge(int $age, string $rule = '110_minus_age'): array
    {
        $equityPercent = match ($rule) {
            '100_minus_age' => max(20, 100 - $age),
            '110_minus_age' => max(20, 110 - $age),
            '120_minus_age' => max(20, 120 - $age),
            default => max(20, 110 - $age),
        };

        $bondPercent = 100 - $equityPercent;

        return [
            'rule' => $rule,
            'age' => $age,
            'allocation' => [
                'equities' => $equityPercent,
                'bonds' => $bondPercent,
                'cash' => 0,
                'alternatives' => 0,
            ],
            'rationale' => sprintf(
                '%s rule suggests %d%% equities for age %d',
                str_replace('_', ' ', $rule),
                $equityPercent,
                $age
            ),
        ];
    }

    /**
     * Optimize allocation based on time horizon
     *
     * @param  int  $years  Years to goal
     * @param  float  $targetValue  Target value
     * @param  float  $currentValue  Current value
     * @return array Optimized allocation
     */
    public function optimizeByTimeHorizon(int $years, float $targetValue, float $currentValue): array
    {
        // Calculate required return
        $requiredReturn = $currentValue > 0
            ? (pow($targetValue / $currentValue, 1 / $years) - 1) * 100
            : 6.0;

        // Determine allocation based on time and required return
        if ($years >= 15 && $requiredReturn <= 8) {
            $allocation = ['equities' => 85, 'bonds' => 10, 'cash' => 0, 'alternatives' => 5];
            $profile = 'Aggressive';
        } elseif ($years >= 10 && $requiredReturn <= 7) {
            $allocation = ['equities' => 70, 'bonds' => 25, 'cash' => 0, 'alternatives' => 5];
            $profile = 'Growth';
        } elseif ($years >= 5 && $requiredReturn <= 6) {
            $allocation = ['equities' => 50, 'bonds' => 40, 'cash' => 5, 'alternatives' => 5];
            $profile = 'Moderate';
        } elseif ($years >= 3) {
            $allocation = ['equities' => 30, 'bonds' => 55, 'cash' => 10, 'alternatives' => 5];
            $profile = 'Conservative';
        } else {
            $allocation = ['equities' => 10, 'bonds' => 70, 'cash' => 20, 'alternatives' => 0];
            $profile = 'Very Conservative';
        }

        return [
            'years_to_goal' => $years,
            'required_annual_return' => round($requiredReturn, 2),
            'profile' => $profile,
            'allocation' => $allocation,
            'rationale' => sprintf(
                '%d years to goal requiring %.1f%% annual return suggests %s allocation',
                $years,
                $requiredReturn,
                $profile
            ),
        ];
    }

    /**
     * Optimize with constraints
     *
     * @param  int  $baseRiskLevel  Base risk level (1-5)
     * @param  array  $constraints  User constraints
     * @return array Optimized allocation
     */
    public function optimizeWithConstraints(int $baseRiskLevel, array $constraints = []): array
    {
        $modelBuilder = new ModelPortfolioBuilder;
        $baseAllocation = $modelBuilder->getModelPortfolio($baseRiskLevel)['asset_allocation'];

        // Apply constraints
        if (isset($constraints['min_equities'])) {
            $baseAllocation['equities'] = max($baseAllocation['equities'], $constraints['min_equities']);
        }

        if (isset($constraints['max_equities'])) {
            $baseAllocation['equities'] = min($baseAllocation['equities'], $constraints['max_equities']);
        }

        if (isset($constraints['min_bonds'])) {
            $baseAllocation['bonds'] = max($baseAllocation['bonds'], $constraints['min_bonds']);
        }

        if (isset($constraints['max_bonds'])) {
            $baseAllocation['bonds'] = min($baseAllocation['bonds'], $constraints['max_bonds']);
        }

        if (isset($constraints['min_cash'])) {
            $baseAllocation['cash'] = max($baseAllocation['cash'], $constraints['min_cash']);
        }

        // Normalize to 100%
        $total = array_sum($baseAllocation);
        if ($total > 0 && $total != 100) {
            foreach ($baseAllocation as $key => $value) {
                $baseAllocation[$key] = round(($value / $total) * 100, 1);
            }
        }

        return [
            'base_risk_level' => $baseRiskLevel,
            'constraints_applied' => $constraints,
            'allocation' => $baseAllocation,
            'total' => array_sum($baseAllocation),
        ];
    }

    /**
     * Get glide path allocation (lifecycle strategy)
     *
     * @param  int  $yearsToRetirement  Years to retirement
     * @return array Glide path allocation
     */
    public function getGlidePathAllocation(int $yearsToRetirement): array
    {
        if ($yearsToRetirement > 30) {
            return [
                'equities' => 90,
                'bonds' => 10,
                'cash' => 0,
                'alternatives' => 0,
                'phase' => 'Accumulation - Maximum Growth',
            ];
        } elseif ($yearsToRetirement > 20) {
            return [
                'equities' => 80,
                'bonds' => 15,
                'cash' => 0,
                'alternatives' => 5,
                'phase' => 'Accumulation - Growth',
            ];
        } elseif ($yearsToRetirement > 10) {
            return [
                'equities' => 65,
                'bonds' => 30,
                'cash' => 0,
                'alternatives' => 5,
                'phase' => 'Pre-Retirement - Balanced',
            ];
        } elseif ($yearsToRetirement > 5) {
            return [
                'equities' => 45,
                'bonds' => 50,
                'cash' => 5,
                'alternatives' => 0,
                'phase' => 'Near Retirement - Conservative',
            ];
        } else {
            return [
                'equities' => 25,
                'bonds' => 65,
                'cash' => 10,
                'alternatives' => 0,
                'phase' => 'At Retirement - Capital Preservation',
            ];
        }
    }

    /**
     * Calculate optimal allocation for multiple goals
     *
     * @param  array  $goals  Array of goals with time horizons
     * @param  float  $totalPortfolioValue  Total portfolio value
     * @return array Blended allocation
     */
    public function optimizeForMultipleGoals(array $goals, float $totalPortfolioValue): array
    {
        if (empty($goals) || $totalPortfolioValue == 0) {
            return [
                'success' => false,
                'message' => 'No goals or zero portfolio value',
            ];
        }

        $weightedAllocation = [
            'equities' => 0,
            'bonds' => 0,
            'cash' => 0,
            'alternatives' => 0,
        ];

        $totalWeight = 0;

        foreach ($goals as $goal) {
            $weight = $totalPortfolioValue > 0 ? $goal['target_value'] / $totalPortfolioValue : 0;
            $goalAllocation = $this->optimizeByTimeHorizon(
                $goal['years_to_goal'],
                $goal['target_value'],
                $goal['current_value'] ?? 0
            );

            foreach ($goalAllocation['allocation'] as $assetClass => $percent) {
                $weightedAllocation[$assetClass] += $percent * $weight;
            }

            $totalWeight += $weight;
        }

        // Normalize
        if ($totalWeight > 0) {
            foreach ($weightedAllocation as $key => $value) {
                $weightedAllocation[$key] = round($value / $totalWeight, 1);
            }
        }

        return [
            'success' => true,
            'goals_count' => count($goals),
            'blended_allocation' => $weightedAllocation,
            'method' => 'Goal-weighted average',
        ];
    }
}
