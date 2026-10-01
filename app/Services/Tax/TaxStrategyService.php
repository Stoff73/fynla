<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\User;
use App\Services\Coordination\CompositePlanService;
use App\Services\TaxConfigService;

/**
 * Orchestrates the /tax-strategy dashboard payload + slider recalculations.
 *
 * - getDashboardPayload(User) — initial GET, returns the calculator output
 *   keyed for the frontend dashboard.
 * - recalculate(User, OverridesDTO) — POST /calculate, returns the calculator
 *   output with overrides applied (no DB writes).
 */
final class TaxStrategyService
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
        private readonly CompositePlanService $composite,
        private readonly TaxConfigService $taxConfig,
        private readonly PensionAffordability $pensionAffordability,
    ) {}

    public function getDashboardPayload(User $user): array
    {
        return $this->withAffordablePensionHeadroom($user, $this->calculator->calculate($user)->toArray());
    }

    public function recalculate(User $user, TaxStrategyOverridesDTO $overrides): array
    {
        return $this->withAffordablePensionHeadroom($user, $this->calculator->calculate($user, $overrides)->toArray());
    }

    /**
     * Pension headroom the user cannot fund is not headroom (CSJ 2026-09-25).
     * The tile's remaining figure is capped at a year of the surplus the app's
     * affordability calculator reports: CompositePlanService::financials()
     * effective_surplus, which is monthly disposable income less committed
     * contributions and goal commitments. Kept out of the calculator, which
     * reprices on every slider move and must stay fast.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withAffordablePensionHeadroom(User $user, array $payload): array
    {
        // The one affordability figure every pension suggestion is capped by
        // (PensionAffordability): the gross a relief-at-source payment of the
        // money buys. With no spending recorded it is not known yet, and the
        // tile keeps a year of the unchecked surplus, as the plan's own card
        // does, until CSJ decides that case (spec 2026-09-30, 4.5).
        $money = $this->pensionAffordability->moneyThisYear($user);
        $basicRelief = (float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'];
        $affordable = $money !== null && $basicRelief < 1
            ? round($money / (1 - $basicRelief), 2)
            : round(max(0.0, 12 * (float) $this->composite->financials($user)['effective_surplus']), 2);

        foreach ($payload['user_allowances'] ?? [] as $i => $position) {
            if (($position['key'] ?? null) !== 'pension_annual_allowance'
                || ($position['available'] ?? true) === false
                || ($position['known'] ?? true) === false) {
                continue;
            }
            $payload['user_allowances'][$i]['remaining'] = round(min((float) $position['remaining'], $affordable), 2);
            $payload['user_allowances'][$i]['affordable_this_year'] = $affordable;
        }

        return $payload;
    }

    /**
     * What the Tax Strategy screens decide from the figures, decided once here
     * (CSJ 2026-10-01: one figure, every surface). Web, /m and iOS each worked
     * these out, and iOS missed the budget cap, so a capped pension tile read
     * "Fully used".
     *
     * - Each allowance gets `tile_state`: unavailable, unconfirmed,
     *   budget_capped (brought to £0 by what is affordable, not by use), full or
     *   open, and `budget_limited` when affordability lowered the figure. Each
     *   surface words the state in its own approved copy.
     * - `summary`: the saving the header leads with (the composed plan's total,
     *   which leaves out the smaller of each conflicting pair; the plain sum of
     *   the recommendations only when no composed plan is attached), the counts
     *   of actions and warnings, and how many allowances have headroom. No total
     *   of headroom: allowances of different kinds cannot be added.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withDisplayState(array $payload): array
    {
        foreach (['user_allowances', 'spouse_allowances'] as $list) {
            foreach ((array) ($payload[$list] ?? []) as $i => $a) {
                $payload[$list][$i] = array_merge($a, $this->tileState($a));
            }
        }

        $recommendations = (array) ($payload['recommendations'] ?? []);
        $actions = array_filter($recommendations, fn ($r): bool => ($r['category'] ?? null) !== 'warning');
        $composed = $payload['composed_plan']['combined_annual_saving'] ?? null;

        $payload['summary'] = [
            'total_saving' => round((float) ($composed ?? array_sum(array_map(
                fn ($r): float => (float) ($r['estimated_annual_tax_saved'] ?? 0),
                $actions,
            ))), 2),
            'actionable_count' => count(array_filter($actions, fn ($r): bool => (float) ($r['estimated_annual_tax_saved'] ?? 0) > 0)),
            'warning_count' => count($recommendations) - count($actions),
            'headroom_count' => count(array_filter(
                (array) ($payload['user_allowances'] ?? []),
                fn ($a): bool => ($a['tile_state'] ?? null) === 'open',
            )),
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array{tile_state: string, budget_limited: bool}
     */
    private function tileState(array $a): array
    {
        $remaining = (float) ($a['remaining'] ?? 0);
        $budgetLimited = array_key_exists('affordable_this_year', $a)
            && $remaining < (float) ($a['amount'] ?? 0) - (float) ($a['used'] ?? 0) - 0.5;

        $state = match (true) {
            ($a['available'] ?? true) === false => 'unavailable',
            ($a['known'] ?? true) === false => 'unconfirmed',
            $budgetLimited && $remaining <= 0 => 'budget_capped',
            (float) ($a['utilisation_pct'] ?? 0) >= 100 || $remaining <= 0 => 'full',
            default => 'open',
        };

        return ['tile_state' => $state, 'budget_limited' => $budgetLimited];
    }
}
