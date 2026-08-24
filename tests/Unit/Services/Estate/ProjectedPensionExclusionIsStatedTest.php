<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0363 acceptance 3 — the exclusion is STATED, not silent.
 *
 * The projected column models a death decades away. From the configured effective date
 * unused defined contribution pensions form part of the estate, and this projection
 * does not include them, so its figure is understated for any household holding one.
 *
 * **Why the figure is not simply corrected here.** Proper inclusion is the UNUSED fund
 * at death — the pot after drawdown. Adding the pot at today's value would DOUBLE
 * COUNT, because `HouseholdCashFlowProjector` already turns that pension into income
 * and carries it in `projected_cash`. The residual is computable —
 * `RetirementProjectionService::projectIncomeDrawdown()` produces `remaining_fund` per
 * year — and wiring it in is W-0482, which carries the tax gate. Until then
 * `05-perimeter.md` §4 applies: where Fynla knows its picture is incomplete, it says so
 * at the point the affected figure is shown.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('states the exclusion for a household holding a defined contribution pension', function () {
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1980-01-01',
        'gender' => 'female',
    ]);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 400_000]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);

    expect($result['projected_pension_exclusion_caveat'])->toBeString();
    expect($result['projected_pension_exclusion_caveat'])
        ->toContain('does not include your defined contribution pension');
    // Rule 2 — the date is configuration, and the sentence prints the configured one.
    expect($result['projected_pension_exclusion_caveat'])->toContain('2027');
});

it('says nothing to a household with no pension to exclude', function () {
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1980-01-01',
        'gender' => 'female',
    ]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);

    // A caveat shown to everyone is noise, and noise is what makes real ones ignored.
    expect($result['projected_pension_exclusion_caveat'])->toBeNull();
});
