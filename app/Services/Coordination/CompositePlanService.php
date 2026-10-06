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
use App\Services\Goals\LifeEventService;
use App\Services\Mobile\NextActionsService;

/**
 * The cross-module composite plan: gathers every module's composed plan, lists
 * the items still open in the actions list's order (inActionsListOrder), walks
 * them against a finite monthly surplus, and annotates each item's
 * affordability (fits / partially_fits / beyond_current_surplus) with the
 * running surplus consumed. An open item beyond the current surplus is
 * surfaced as such, mirroring the tax "locked, never silently skipped"
 * principle. This is read-side only (composing a plan never writes).
 */
final class CompositePlanService
{
    public function __construct(
        private readonly ComposedModulePlanService $plans,
        private readonly CashFlowCoordinator $cashFlow,
        private readonly GoalAffordabilityService $goalAffordability,
        private readonly LifeEventService $lifeEvents,
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
            'items' => $this->annotateAffordability($this->inActionsListOrder($user, $items), $financials['effective_surplus']),
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

        // Near-term (next 12 months) net life-event capital — a positive figure
        // is incoming capital (e.g. an inheritance) that can fund a lump-sum
        // strategy beyond the monthly surplus; a negative figure is a near-term
        // drain the plan should respect. Summed by expected_date so imminent
        // (this-year) events count — the year-bucketed cash-flow map treats them
        // as "year 0" and would miss them. Surfaced as context, not folded into
        // the monthly surplus (a one-off lump sum is not a monthly figure).
        $horizon = now()->addYear();
        $nearTermCapital = (float) $this->lifeEvents->getActiveEventsForProjection($user->id)
            ->filter(fn ($event) => $event->expected_date !== null && $event->expected_date->lte($horizon))
            ->sum(fn ($event) => $event->impact_type === 'expense'
                ? -(float) $event->amount
                : (float) $event->amount);

        return [
            'available_monthly_surplus' => $surplus,
            'goal_commitments' => $goalCommitments,
            'effective_surplus' => max(0.0, $surplus - $goalCommitments),
            'near_term_capital' => $nearTermCapital,
            'goals' => $goals,
        ];
    }

    /**
     * The plan's items as the actions list holds them (CSJ 2026-10-01, one list
     * on every surface): each item carries its action id (the aggregator's,
     * RecommendationsAggregatorService::composeId), only the actions still open
     * are listed (done and dismissed ones are not), and they come in the
     * actions list's order (NextActionsService, ranked by PriorityRanker: the
     * seeded priority wins, CSJ 2026-09-09).
     *
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    private function inActionsListOrder(User $user, array $items): array
    {
        $byModule = [];
        foreach ($items as $i => $item) {
            $byModule[$item['module']][$i] = [
                'recommendation_id' => $item['module'] === 'tax'
                    ? 'tax_'.($item['type'] ?? '')
                    : RecommendationsAggregatorService::composeId($item['module'], $item),
                'recommendation_text' => trim(($item['title'] ?? '').' — '.($item['description'] ?? ''), ' —'),
            ];
        }
        foreach ($byModule as $module => $recs) {
            foreach ($module === 'tax' ? $recs : RecommendationsAggregatorService::disambiguate($recs) as $i => $rec) {
                $items[$i]['id'] = $rec['recommendation_id'];
            }
        }

        $position = array_flip(array_column(app(NextActionsService::class)->buildAll($user->id), 'id'));
        $open = array_values(array_filter($items, fn (array $item): bool => isset($position[$item['id']])));
        usort($open, fn (array $a, array $b): int => $position[$a['id']] <=> $position[$b['id']]);

        return $open;
    }

    /**
     * Pure: walk the running surplus in the order given, annotate each item's
     * affordability + cumulative surplus consumed. Never drops an item.
     *
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    public function annotateAffordability(array $items, float $surplus): array
    {
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
