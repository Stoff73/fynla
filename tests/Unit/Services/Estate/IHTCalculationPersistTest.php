<?php

declare(strict_types=1);

use App\Models\Estate\IHTCalculation;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Wave 2.4 — REVIEW §4 High #22.
 *
 * `IHTCalculationService::calculate()` used to unconditionally write a
 * row into `iht_calculations` on every call, including from read-only
 * flows (dashboards, advice queries, chat tool calls). Persistence is
 * now opt-in via `persist: true`.
 */
it('does NOT write to iht_calculations on the default read-only path', function () {
    $user = User::factory()->create();

    $before = IHTCalculation::where('user_id', $user->id)->count();

    app(IHTCalculationService::class)->calculate($user);

    $after = IHTCalculation::where('user_id', $user->id)->count();

    expect($after)->toBe($before);
});

it('writes one iht_calculations row when persist: true is opted in', function () {
    $user = User::factory()->create();

    expect(IHTCalculation::where('user_id', $user->id)->count())->toBe(0);

    app(IHTCalculationService::class)->calculate(
        $user,
        null,
        false,
        persist: true,
    );

    expect(IHTCalculation::where('user_id', $user->id)->count())->toBe(1);
});

/**
 * W-0136. The cache hashes fingerprint the DATA, never the code that produced the
 * row, so a result persisted by an older build passes every hash check and is
 * served whole — including to consumers reading figures it does not contain.
 */
it('recomputes rather than serving a result persisted before the projected allowances existed', function () {
    $user = User::factory()->create();
    $service = app(IHTCalculationService::class);

    $service->calculate($user, null, false, persist: true);

    // Strip the projected allowance block, exactly as a pre-W-0136 row would be,
    // and poison the tax so a cache hit is unmistakable.
    $row = IHTCalculation::where('user_id', $user->id)->latest('calculation_date')->firstOrFail();
    $stale = $row->result_json;
    unset($stale['projected_total_allowances'], $stale['projected_charitable_deduction'], $stale['projected_rnrb_status']);
    $stale['iht_liability'] = 999_999.99;
    $row->result_json = $stale;
    $row->save();

    $result = $service->calculate($user);

    expect($result['iht_liability'])->not->toBe(999_999.99)
        ->and($result)->toHaveKey('projected_total_allowances')
        ->and($result)->toHaveKey('projected_charitable_deduction')
        ->and($result)->toHaveKey('projected_rnrb_status');
});
