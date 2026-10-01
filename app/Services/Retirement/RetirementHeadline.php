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
 * - Someone drawing (RetirementDrawdownPosition::isDrawing) is `kind` "drawing":
 *   the value is this year's income, the Retirement page's own figure, so the
 *   dashboard card and the page cannot differ (CSJ 2026-10-01).
 *
 * Clients render these as sent; they never add, subtract or fall back.
 */
class RetirementHeadline
{
    public function __construct(
        private readonly RetirementProjectionContractService $contract,
        private readonly RequiredCapitalCalculator $requiredCapital,
        private readonly RetirementDrawdownPosition $drawdownPosition,
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

        $drawing = $this->drawdownPosition->for($user);
        if ($drawing !== null) {
            $kind = 'drawing';
        }

        return [
            'kind' => $kind,
            'value' => round(match ($kind) {
                'drawing' => (float) $drawing['income']['total'],
                'guaranteed' => $guaranteed,
                default => $projected,
            }, 2),
            // The drawing view's own figures, for someone drawing; null otherwise.
            'drawing_income' => $drawing !== null ? round((float) $drawing['income']['total'], 2) : null,
            'drawing_lasts_to_age' => $drawing['pot']['lasts_to_age']['middle'] ?? null,
            'drawing_lasts_label' => $drawing['pot']['lasts_labels']['middle'] ?? null,
            'drawing_pot_end_age' => $drawing['pot']['end_age'] ?? null,
            'drawing_per_year' => isset($drawing['pot']) ? round((float) $drawing['pot']['drawing_per_year'], 2) : null,
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
