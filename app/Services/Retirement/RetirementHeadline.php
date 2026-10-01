<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;

/**
 * The retirement figures every surface shows, computed once (CSJ 2026-10-01:
 * every figure is computed on the server and fetched by web, /m, iOS, cards
 * and Fyn, so no two can differ; audit docs/audits/2026-10-01-one-figure-every-surface.md
 * items 1-8).
 *
 * - The projection is the planning contract (RetirementProjectionContractService,
 *   plan 2026-08-10: the primary projection; Monte Carlo bands are the separate
 *   uncertainty view).
 * - The target is RequiredCapitalCalculator's (the stated target, or the one
 *   worked out from income when none is stated), with where it came from.
 * - The gap is signed: positive is short, negative is over.
 *
 * Clients render these as sent; they never add, subtract or fall back.
 */
class RetirementHeadline
{
    public function __construct(
        private readonly RetirementProjectionContractService $contract,
        private readonly RequiredCapitalCalculator $requiredCapital,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $plan = $this->contract->build($user, withUncertainty: false);
        $products = collect($plan['products']);
        $dc = $products->where('resource_type', 'dc_pension');

        $projected = (float) $plan['planning_total_at_target_age'];
        $guaranteed = (float) $products->where('resource_type', '!=', 'dc_pension')->sum('annual_income');
        $dcToday = (float) $dc->sum('current_value');

        $required = $this->requiredCapital->calculate($user->id);
        $target = (float) ($required['required_income'] ?? 0);
        $hasTarget = $target > 0;

        // A household with no pot shows the income already secured, a yearly
        // figure, never a balance (retirementHeadline.js rule, now server-side).
        $kind = $dc->isNotEmpty() ? 'projected' : ($guaranteed > 0 ? 'guaranteed' : 'none');
        $currentAge = $user->date_of_birth?->age;

        return [
            'kind' => $kind,
            'value' => round($kind === 'guaranteed' ? $guaranteed : $projected, 2),
            'projected_income' => round($projected, 2),
            'guaranteed_income' => round($guaranteed, 2),
            'target_income' => $hasTarget ? round($target, 2) : null,
            'target_source' => $hasTarget ? (string) ($required['income_source'] ?? 'calculated') : null,
            'income_gap' => $hasTarget ? round($target - $projected, 2) : null,
            'progress_percent' => $hasTarget ? (int) round(min(999, $projected / $target * 100)) : null,
            'target_age' => (int) $plan['target_retirement_age'],
            'years_to_retirement' => $currentAge === null ? null : max(0, (int) $plan['target_retirement_age'] - $currentAge),
            'dc_value_today' => round($dcToday, 2),
            'dc_value_at_retirement' => round((float) $dc->sum('projected_value'), 2),
            'required_capital' => isset($required['required_capital_at_retirement']) ? round((float) $required['required_capital_at_retirement'], 2) : null,
        ];
    }
}
