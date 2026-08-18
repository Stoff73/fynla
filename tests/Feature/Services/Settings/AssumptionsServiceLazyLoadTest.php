<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Retirement\RetirementProjectionContractService;
use App\Services\Settings\AssumptionsService;
use Laravel\Sanctum\Sanctum;

// TestCase and RefreshDatabase are applied to all of tests/Feature by Pest.php.

/**
 * BUG-02 defect 1 (CSJ 2026-08-17).
 *
 * `GET /api/retirement/projections` returned HTTP 500 for any user with two or
 * more funded DC pensions: `AssumptionsService::calculateWeightedFees()` read
 * `$pension->holdings` without eager-loading it, and `AppServiceProvider` enables
 * `Model::preventLazyLoading()` outside production. Staging therefore threw while
 * production silently degraded to an N+1 — so the native app, which reads csjones,
 * rendered a projected retirement income of £0.
 *
 * Both branches of the method had the flaw, so investment accounts were affected
 * on the same terms as pensions.
 *
 * Two funded records is the trigger; one, or several unfunded ones, was not
 * enough to reproduce it — hence the counts below.
 */
it('computes weighted pension fees with two funded pensions without lazy loading', function (): void {
    $user = User::factory()->create();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 45000]);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 20000]);

    $fees = app(AssumptionsService::class)->calculateWeightedFees($user->fresh(), 'pensions');

    expect($fees)->toHaveKeys(['platform', 'ocf', 'advisory', 'total']);
});

it('computes weighted investment fees with two funded accounts without lazy loading', function (): void {
    $user = User::factory()->create();
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'current_value' => 45000]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'current_value' => 20000]);

    $fees = app(AssumptionsService::class)->calculateWeightedFees($user->fresh(), 'investments');

    expect($fees)->toHaveKeys(['platform', 'ocf', 'advisory', 'total']);
});

it('builds the retirement projection contract with two funded pensions', function (): void {
    $user = User::factory()->create(['date_of_birth' => '1985-01-01']);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 45000]);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 20000]);

    $contract = app(RetirementProjectionContractService::class)->build($user->fresh());

    // The projection must be a real figure, not the £0 the native app showed.
    expect($contract['planning_total_at_target_age'])->toBeGreaterThan(0);
    expect($contract['products'])->toHaveCount(2);
});

it('serves the projections endpoint with two funded pensions', function (): void {
    $user = User::factory()->create(['date_of_birth' => '1985-01-01']);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 45000]);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 20000]);

    Sanctum::actingAs($user);

    $this->getJson('/api/retirement/projections')
        ->assertOk()
        ->assertJsonPath('data.planning_projection.target_retirement_age', fn ($age) => $age > 0);
});
