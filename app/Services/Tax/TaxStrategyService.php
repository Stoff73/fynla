<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\User;
use App\Services\Coordination\CompositePlanService;

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
        $affordable = round(max(0.0, 12 * (float) $this->composite->financials($user)['effective_surplus']), 2);

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
}
