<?php

declare(strict_types=1);

use App\Models\Estate\Trust;
use App\Models\Household;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\PersonalizedTrustStrategyService;
use App\Services\Estate\TrustService;
use App\Services\TaxConfigService;
use App\Services\Trust\IHTPeriodicChargeCalculator;
use Carbon\Carbon;

/**
 * Consolidation guard (jul6 audit, Fix 4): the 10-year periodic charge was
 * implemented three times — IHTPeriodicChargeCalculator (canonical, date-gated),
 * TrustService::calculatePeriodicCharge (always-on efficiency estimate) and
 * PersonalizedTrustStrategyService (projection on a hypothetical value). All
 * three reduce to (value − NRB) × 6%. They now share the single kernel
 * IHTPeriodicChargeCalculator::estimateChargeOnValue.
 *
 * These tests pin that the shared kernel produces exactly the numbers the three
 * call sites produced before, so the consolidation changed no output. The
 * scenarios still differ deliberately: the canonical is date-gated and reads
 * total_asset_value ?? current_value; TrustService is an always-on estimate on
 * current_value; PersonalizedTrustStrategyService estimates on a projected
 * float. They agree numerically only where those inputs coincide.
 */
beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->taxConfig = app(TaxConfigService::class);
    $this->calculator = app(IHTPeriodicChargeCalculator::class);
    $this->user = User::factory()->create();
    $this->household = Household::factory()->create();
});

it('kernel charges 6% of value above the nil-rate band', function () {
    // 500k − 325k NRB = 175k excess; 175k × 6% = 10,500.
    expect($this->calculator->estimateChargeOnValue(500000.0))->toBe(10500.0);
    // Below NRB → no charge.
    expect($this->calculator->estimateChargeOnValue(200000.0))->toBe(0.0);
});

it('TrustService periodic charge equals the canonical kernel and the former formula', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 500000,
    ]);

    $result = app(TrustService::class)->calculatePeriodicCharge($trust);

    $nrb = (float) $this->taxConfig->getInheritanceTax()['nil_rate_band'];
    $former = max(0.0, (float) $trust->current_value - $nrb) * 0.06; // pre-consolidation formula

    expect((float) $result['charge_amount'])->toBe($former)
        ->and((float) $result['charge_amount'])->toBe(10500.0)
        ->and((float) $result['charge_amount'])->toBe($this->calculator->estimateChargeOnValue((float) $trust->current_value))
        ->and((float) $result['effective_rate'])->toBe(round(10500.0 / 500000.0, 4)); // 0.021
});

it('canonical date-gated charge and always-on estimate agree when value fields coincide', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 600000,
        'total_asset_value' => 600000,
    ]);

    $canonical = $this->calculator->calculatePeriodicCharge($trust);
    $estimate = app(TrustService::class)->calculatePeriodicCharge($trust);

    expect($canonical['charge_applicable'])->toBeTrue()
        ->and((float) $canonical['charge_amount'])->toBe((float) $estimate['charge_amount'])
        ->and((float) $canonical['charge_amount'])->toBe(16500.0); // (600k − 325k) × 6%
});

it('projection kernel reproduces the former private projection formula', function () {
    // The deleted PersonalizedTrustStrategyService::calculatePeriodicCharge(float,float)
    // returned max(0, value − nrb) × 0.06. The projection now calls the shared kernel.
    $nrb = 325000.0;

    foreach ([300000.0, 400000.0, 651558.0, 1000000.0] as $projected) {
        $former = max(0.0, $projected - $nrb) * 0.06;
        expect($this->calculator->estimateChargeOnValue($projected, $nrb))->toBe($former);
    }
});

it('NRB avoidance projection wires the kernel and stays self-consistent', function () {
    $result = app(PersonalizedTrustStrategyService::class)
        ->calculateNRBAvoidanceProjection($this->user, 400000.0);

    if ($result['will_exceed_nrb']) {
        $expected = round(($result['projected_at_10_years'] - $result['nrb_threshold']) * 0.06, 2);
        expect($result['estimated_periodic_charge'])->toEqualWithDelta($expected, 0.01);
    } else {
        expect($result['estimated_periodic_charge'])->toBe(0.0);
    }
});
