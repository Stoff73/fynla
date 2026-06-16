<?php

declare(strict_types=1);

use App\Services\Coordination\CompositePlanService;

/**
 * The affordability ranker is the pure heart of the cross-module composite plan:
 * rank by impact, walk a finite monthly surplus, annotate each item
 * fits / partially_fits / beyond_current_surplus with the running surplus
 * consumed — and NEVER drop an item (mirrors the tax "locked, never silently
 * skipped" principle).
 */
it('ranks by impact, walks the surplus, and annotates without dropping anything', function (): void {
    $items = [
        ['type' => 'c_low', 'estimated_annual_tax_saved' => 600.0, 'priority' => 'medium', 'required_monthly_cost' => 400.0],
        ['type' => 'a_high', 'estimated_annual_tax_saved' => 1000.0, 'priority' => 'high', 'required_monthly_cost' => 200.0],
        ['type' => 'b_mid', 'estimated_annual_tax_saved' => 800.0, 'priority' => 'high', 'required_monthly_cost' => 300.0],
    ];

    $ranked = app(CompositePlanService::class)->annotateAffordability($items, 400.0);

    // Ranked by saving desc: a_high, b_mid, c_low. Nothing dropped.
    expect($ranked)->toHaveCount(3)
        ->and(array_column($ranked, 'type'))->toBe(['a_high', 'b_mid', 'c_low']);

    // a_high: 200 <= 400 → fits, consumed 200.
    expect($ranked[0]['affordability'])->toBe('fits')
        ->and($ranked[0]['surplus_consumed_to_here'])->toBe(200.0);

    // b_mid: 300 > 200 remaining but 200 > 0 → partially_fits, consumed 400.
    expect($ranked[1]['affordability'])->toBe('partially_fits')
        ->and($ranked[1]['surplus_consumed_to_here'])->toBe(400.0);

    // c_low: 0 remaining → beyond_current_surplus, consumed 400 (unchanged).
    expect($ranked[2]['affordability'])->toBe('beyond_current_surplus')
        ->and($ranked[2]['surplus_consumed_to_here'])->toBe(400.0);
});

it('treats a null-cost item as informational — fits and consumes nothing', function (): void {
    $items = [
        ['type' => 'informational', 'estimated_annual_tax_saved' => 5000.0, 'priority' => 'high'], // no required_monthly_cost
        ['type' => 'costed', 'estimated_annual_tax_saved' => 100.0, 'priority' => 'low', 'required_monthly_cost' => 150.0],
    ];

    $ranked = app(CompositePlanService::class)->annotateAffordability($items, 200.0);

    expect($ranked[0]['type'])->toBe('informational')
        ->and($ranked[0]['affordability'])->toBe('fits')
        ->and($ranked[0]['surplus_consumed_to_here'])->toBe(0.0)
        // the costed item still sees the full surplus (informational consumed nothing)
        ->and($ranked[1]['affordability'])->toBe('fits')
        ->and($ranked[1]['surplus_consumed_to_here'])->toBe(150.0);
});

it('annotates everything beyond surplus when there is no surplus, dropping nothing', function (): void {
    $items = [
        ['type' => 'x', 'estimated_annual_tax_saved' => 100.0, 'priority' => 'high', 'required_monthly_cost' => 50.0],
        ['type' => 'y', 'estimated_annual_tax_saved' => 90.0, 'priority' => 'high', 'required_monthly_cost' => 50.0],
    ];

    $ranked = app(CompositePlanService::class)->annotateAffordability($items, 0.0);

    expect($ranked)->toHaveCount(2)
        ->and($ranked[0]['affordability'])->toBe('beyond_current_surplus')
        ->and($ranked[1]['affordability'])->toBe('beyond_current_surplus');
});
