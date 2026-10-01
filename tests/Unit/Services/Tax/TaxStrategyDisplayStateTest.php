<?php

declare(strict_types=1);

use App\Services\Tax\TaxStrategyService;

// One figure, every surface (CSJ 2026-10-01; audit items 39-41): the Tax
// Strategy tile states and header figures are decided here, once.

beforeEach(function () {
    $this->service = app(TaxStrategyService::class);
});

it('gives each tile one state the surfaces word in their own copy', function () {
    $payload = $this->service->withDisplayState(['user_allowances' => [
        ['key' => 'isa', 'amount' => 20000, 'used' => 8000, 'remaining' => 12000, 'utilisation_pct' => 40],
        ['key' => 'cgt', 'amount' => 3000, 'used' => 3000, 'remaining' => 0, 'utilisation_pct' => 100],
        ['key' => 'pension_annual_allowance', 'amount' => 60000, 'used' => 7500, 'remaining' => 0, 'utilisation_pct' => 12.5, 'affordable_this_year' => 0],
        ['key' => 'psa', 'available' => false, 'remaining' => 0],
        ['key' => 'dividend', 'known' => false, 'remaining' => 500],
    ]]);
    $states = array_column($payload['user_allowances'], 'tile_state', 'key');

    expect($states)->toBe([
        'isa' => 'open',
        'cgt' => 'full',
        // £7,500 of £60,000 brought to £0 by what is affordable, not by use
        // (fynla.org 2026-09-30 read "Fully used").
        'pension_annual_allowance' => 'budget_capped',
        'psa' => 'unavailable',
        'dividend' => 'unconfirmed',
    ])->and($payload['user_allowances'][2]['budget_limited'])->toBeTrue()
        ->and($payload['user_allowances'][0]['budget_limited'])->toBeFalse();
});

it('leads the header with the composed plan, counts actions and warnings, and never totals headroom', function () {
    $payload = $this->service->withDisplayState([
        'user_allowances' => [
            ['key' => 'isa', 'amount' => 20000, 'used' => 0, 'remaining' => 20000, 'utilisation_pct' => 0],
            ['key' => 'cgt', 'amount' => 3000, 'used' => 0, 'remaining' => 3000, 'utilisation_pct' => 0],
        ],
        'recommendations' => [
            ['category' => 'allowance', 'estimated_annual_tax_saved' => 900],
            ['category' => 'household', 'estimated_annual_tax_saved' => 600],
            ['category' => 'warning', 'estimated_annual_tax_saved' => 0],
        ],
        'composed_plan' => ['combined_annual_saving' => 1100],
    ]);

    expect($payload['summary'])->toBe([
        'total_saving' => 1100.0,
        'actionable_count' => 2,
        'warning_count' => 1,
        'headroom_count' => 2,
    ]);
});

it('sums the actions only when no composed plan is attached', function () {
    $payload = $this->service->withDisplayState(['recommendations' => [
        ['category' => 'allowance', 'estimated_annual_tax_saved' => 900],
        ['category' => 'warning', 'estimated_annual_tax_saved' => 300],
    ]]);

    expect($payload['summary']['total_saving'])->toBe(900.0);
});
