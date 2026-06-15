<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\User;
use App\Services\Coordination\PlanSources\EstateStrategySource;
use App\Services\Coordination\PlanSources\InvestmentStrategySource;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;
use App\Services\Coordination\PlanSources\RetirementStrategySource;
use App\Services\Coordination\PlanSources\SavingsStrategySource;
use App\Services\Coordination\PlanSources\TaxStrategySource;
use App\Services\Goals\GoalAffordabilityService;

/**
 * The cross-module composite plan: gathers every module's composed plan, ranks
 * the combined item set against a finite monthly surplus, and annotates each
 * item's affordability (fits / partially_fits / beyond_current_surplus) with the
 * running surplus consumed. Nothing is ever dropped — an item beyond the current
 * surplus is surfaced as such, mirroring the tax "locked, never silently skipped"
 * principle. This is read-side only (composing a plan never writes).
 */
final class CompositePlanService
{
    private const PRIORITY_WEIGHT = ['high' => 0, 'medium' => 1, 'low' => 2];

    public function __construct(
        private readonly ComposedModulePlanService $plans,
        private readonly CashFlowCoordinator $cashFlow,
        private readonly GoalAffordabilityService $goalAffordability,
        private readonly TaxStrategySource $tax,
        private readonly RetirementStrategySource $retirement,
        private readonly SavingsStrategySource $savings,
        private readonly InvestmentStrategySource $investment,
        private readonly ProtectionStrategySource $protection,
        private readonly EstateStrategySource $estate,
    ) {}

    /**
     * @return array{items: list<array<string,mixed>>, by_module: array<string, array<string,mixed>>, locked: list<array<string,mixed>>, available_monthly_surplus: float, goal_commitments: float, effective_surplus: float, goals: array<string,mixed>}
     */
    public function compose(User $user): array
    {
        $byModule = [];
        $items = [];
        $locked = [];

        foreach ($this->sources() as $source) {
            $module = $source->moduleKey();
            $plan = $this->plans->forSource($source, $user);
            $byModule[$module] = $plan;

            foreach ($plan['items'] as $item) {
                $item['module'] = $module;
                $items[] = $item;
            }
            foreach ($plan['locked'] as $lock) {
                $lock['module'] = $module;
                $locked[] = $lock;
            }
        }

        $financials = $this->financials($user);

        return array_merge([
            'items' => $this->annotateAffordability($items, $financials['effective_surplus']),
            'by_module' => $byModule,
            'locked' => $locked,
        ], $financials);
    }

    /**
     * The household money picture the strategy ranking competes for: the monthly
     * surplus, less active-goal contributions (committed demands the strategies
     * must give way to), floored at zero. Goals enter the composite as demands —
     * a strategy is ranked against what is left after the user's own goals.
     *
     * @return array{available_monthly_surplus: float, goal_commitments: float, effective_surplus: float, goals: array<string,mixed>}
     */
    public function financials(User $user): array
    {
        $surplus = (float) $this->cashFlow->calculateAvailableSurplus($user->id);
        $goals = $this->goalAffordability->analyzeAllGoals($user);
        $goalCommitments = (float) ($goals['total_goal_commitments'] ?? 0);

        return [
            'available_monthly_surplus' => $surplus,
            'goal_commitments' => $goalCommitments,
            'effective_surplus' => max(0.0, $surplus - $goalCommitments),
            'goals' => $goals,
        ];
    }

    /**
     * Pure: rank by impact, walk the running surplus, annotate each item's
     * affordability + cumulative surplus consumed. Never drops an item.
     *
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    public function annotateAffordability(array $items, float $surplus): array
    {
        usort($items, function (array $a, array $b): int {
            $savingCmp = ((float) ($b['estimated_annual_tax_saved'] ?? 0))
                <=> ((float) ($a['estimated_annual_tax_saved'] ?? 0));
            if ($savingCmp !== 0) {
                return $savingCmp;
            }

            return (self::PRIORITY_WEIGHT[$a['priority'] ?? 'medium'] ?? 1)
                <=> (self::PRIORITY_WEIGHT[$b['priority'] ?? 'medium'] ?? 1);
        });

        $remaining = $surplus;

        foreach ($items as $i => $item) {
            $cost = array_key_exists('required_monthly_cost', $item)
                ? (float) $item['required_monthly_cost']
                : null;

            if ($cost === null) {
                $affordability = 'fits'; // informational — consumes no surplus
            } elseif ($cost <= $remaining) {
                $affordability = 'fits';
                $remaining -= $cost;
            } elseif ($remaining > 0) {
                $affordability = 'partially_fits';
                $remaining = 0.0;
            } else {
                $affordability = 'beyond_current_surplus';
            }

            $items[$i]['affordability'] = $affordability;
            $items[$i]['surplus_consumed_to_here'] = round($surplus - $remaining, 2);
        }

        return $items;
    }

    /** @return list<ModuleStrategySource> */
    private function sources(): array
    {
        return [
            $this->tax,
            $this->retirement,
            $this->savings,
            $this->investment,
            $this->protection,
            $this->estate,
        ];
    }
}
