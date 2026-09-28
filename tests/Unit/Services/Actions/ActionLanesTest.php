<?php

declare(strict_types=1);

use App\Services\Actions\ActionLanes;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * Design B, deadline lanes ("Fynla Actions Layouts", CSJ 2026-09-17): every
 * open action sits in one lane, decided once on the server.
 */
beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));
afterEach(fn () => Carbon::setTestNow());

it('puts a per-tax-year allowance before the tax year end, other advice in soon, and data we need in waiting', function () {
    expect(ActionLanes::laneFor(['id' => 'tax_isa_topup_vs_psa', 'type' => 'recommendation']))->toBe(ActionLanes::BEFORE_TAX_YEAR_END)
        ->and(ActionLanes::laneFor(['id' => 'tax_marriage_allowance_transfer', 'type' => 'recommendation']))->toBe(ActionLanes::SOON)
        ->and(ActionLanes::laneFor(['id' => 'protection_life_cover', 'type' => 'recommendation']))->toBe(ActionLanes::SOON)
        ->and(ActionLanes::laneFor(['id' => 'savings_excess_cash_isa_available', 'type' => 'recommendation', 'card' => ['definition_key' => 'excess_cash_isa_available']]))->toBe(ActionLanes::BEFORE_TAX_YEAR_END)
        ->and(ActionLanes::laneFor(['id' => 'strategy_unlock:marriage_allowance_transfer', 'type' => 'unlock']))->toBe(ActionLanes::WAITING)
        ->and(ActionLanes::laneFor(['id' => 'unlock:retirement', 'type' => 'unlock']))->toBe(ActionLanes::WAITING);
});

it('sends the lane headings in page order, counts them, and leaves out empty lanes', function () {
    Carbon::setTestNow('2026-09-28');

    $grouped = app(ActionLanes::class)->group([
        ['id' => 'tax_isa_topup_vs_psa', 'type' => 'recommendation'],
        ['id' => 'unlock:retirement', 'type' => 'unlock'],
        ['id' => 'strategy_unlock:bed_and_isa', 'type' => 'unlock'],
    ]);

    expect(array_column($grouped['lanes'], 'key'))->toBe([ActionLanes::BEFORE_TAX_YEAR_END, ActionLanes::WAITING])
        ->and($grouped['lanes'][0]['title'])->toBe('Before 5 April')
        ->and($grouped['lanes'][0]['sub'])->toBe('189 days — these do not carry over')
        ->and($grouped['lanes'][1]['count'])->toBe(2)
        ->and($grouped['items'][0]['closes'])->toBe('Closes 5 April')
        ->and($grouped['items'][1]['closes'])->toBeNull();
});
