<?php

declare(strict_types=1);

use App\Services\Goals\GoalAssignmentService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Golden tests for SDLT calculation on property-purchase goals.
 *
 * Pre-fix state: GoalAssignmentService::calculateSDLT had three compounding bugs —
 *   1. FTB key mismatch: 'first_time_buyer' (singular) vs seeder 'first_time_buyers'
 *   2. Standard band unwrap missed the nested 'bands' key
 *   3. Rate divided by 100 when seeder already stores decimals (off-by-100x)
 *   4. Wrong band semantics (band[i].rate applied to (prev_threshold, threshold] instead of
 *      (threshold, next_threshold])
 *
 * The fix delegates to the canonical PropertyTaxService::calculateSDLT which already
 * has the correct semantics. These tests pin the correct numbers against the audit's
 * specified Tier 1 golden cases.
 */
describe('GoalAssignmentService — SDLT calculation', function () {
    it('charges £0 SDLT for a £300,000 first-time-buyer purchase (within FTB nil-rate)', function () {
        $svc = app(GoalAssignmentService::class);

        $result = $svc->calculatePropertyCosts([
            'estimated_property_price' => 300_000,
            'is_first_time_buyer' => true,
        ]);

        expect($result['stamp_duty'])->toBe(0.0);
    });

    it('charges £5,000 SDLT for a £400,000 first-time-buyer purchase (5% on £100k above £300k)', function () {
        $svc = app(GoalAssignmentService::class);

        $result = $svc->calculatePropertyCosts([
            'estimated_property_price' => 400_000,
            'is_first_time_buyer' => true,
        ]);

        // FTB bands: 0% to £300k, then 5% above. £400k → 5% × £100k = £5,000.
        expect($result['stamp_duty'])->toBe(5_000.0);
    });

    it('denies FTB relief when price exceeds the max FTB property value (£500,000)', function () {
        $svc = app(GoalAssignmentService::class);

        // £600k FTB: above max_property_value (£500k) → falls back to standard rates.
        $result = $svc->calculatePropertyCosts([
            'estimated_property_price' => 600_000,
            'is_first_time_buyer' => true,
        ]);

        // Standard bands 2026/27: 0% to £125k, 2% to £250k, 5% to £925k.
        // 2% × £125k = £2,500. 5% × £350k = £17,500. Total = £20,000.
        expect($result['stamp_duty'])->toBe(20_000.0);
    });

    it('uses standard residential rates for non-FTB purchases', function () {
        $svc = app(GoalAssignmentService::class);

        // £300k standard: 0% to £125k, 2% × £125k = £2,500, 5% × £50k = £2,500. Total £5,000.
        $result = $svc->calculatePropertyCosts([
            'estimated_property_price' => 300_000,
            'is_first_time_buyer' => false,
        ]);

        expect($result['stamp_duty'])->toBe(5_000.0);
    });

    it('returns zero costs when property price is zero or missing', function () {
        $svc = app(GoalAssignmentService::class);

        $result = $svc->calculatePropertyCosts([
            'estimated_property_price' => 0,
            'is_first_time_buyer' => true,
        ]);

        expect($result['stamp_duty'])->toBe(0)
            ->and($result['total_upfront'])->toBe(0);
    });
});
