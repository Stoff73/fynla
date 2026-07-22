<?php

declare(strict_types=1);

use App\Models\Estate\IHTProfile;
use App\Models\FamilyMember;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pins three IHT calculation-logic fixes in IHTCalculationService:
 *
 *  1. RNRB direct-descendant gate (IHTA 1984 s8E/s8K). The Residence Nil Rate
 *     Band only applies where the residence is "closely inherited" by direct
 *     descendants. A childless homeowner gets £0 RNRB — previously the condition
 *     lived only in a message string and was never enforced.
 *
 *  2. RNRB residence-value cap (IHTA 1984 s8E(2)). The RNRB is the LOWER of the
 *     maximum allowance (£175k / £350k combined) and the net-of-mortgage,
 *     ownership-share-adjusted value of the home. A £200k home caps a married
 *     couple's RNRB at £200k, not £350k.
 *
 *  3. Charitable-legacy exemption (IHTA 1984 s23). Charity gifts leave the
 *     taxable estate entirely (taxable = net − NRB − RNRB − charity), in addition
 *     to any 36% reduced rate. Previously only the rate was reduced; the gift was
 *     never removed from the taxable estate.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(IHTCalculationService::class);
});

describe('RNRB direct-descendant gate (Fix 1)', function () {
    it('gives a childless homeowner £0 RNRB', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 300_000,
        ]);

        // No FamilyMember rows at all → no direct descendants.
        $result = $this->service->calculate($user);

        expect($result['rnrb_available'])->toBe(0.0)
            ->and($result['rnrb_status'])->toBe('none')
            ->and($result['rnrb_message'])->toContain('direct descendants');
    });

    it('still gives £0 RNRB when the only relatives are non-descendants', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 300_000,
        ]);

        // A parent is not a direct descendant (nor are siblings, nieces, etc.).
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'parent',
        ]);

        $result = $this->service->calculate($user);

        expect($result['rnrb_available'])->toBe(0.0)
            ->and($result['rnrb_status'])->toBe('none');
    });

    it('grants RNRB once a direct descendant (child) exists', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 300_000,
        ]);

        FamilyMember::factory()->child()->create(['user_id' => $user->id]);

        $result = $this->service->calculate($user);

        // £300k home ≥ £175k single allowance → full single RNRB, no residence cap.
        expect($result['rnrb_available'])->toBe(175_000.0)
            ->and($result['rnrb_status'])->toBe('full');
    });
});

describe('RNRB residence-value cap (Fix 2)', function () {
    it('caps a married couple\'s RNRB at a £200k home value (not £350k)', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $spouse = User::factory()->create();

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 200_000,
        ]);

        FamilyMember::factory()->child()->create(['user_id' => $user->id]);

        $result = $this->service->calculate($user, $spouse);

        expect($result['rnrb_available'])->toBe(200_000.0)
            ->and($result['rnrb_available'])->not->toBe(350_000.0)
            ->and($result['rnrb_status'])->toBe('residence_capped')
            ->and($result['rnrb_message'])->toContain('capped');
    });

    it('does not cap when the home value exceeds the maximum allowance', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $spouse = User::factory()->create();

        // £500k home ≥ £350k combined maximum → full £350k, cap does not bind.
        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 500_000,
        ]);

        FamilyMember::factory()->child()->create(['user_id' => $user->id]);

        $result = $this->service->calculate($user, $spouse);

        expect($result['rnrb_available'])->toBe(350_000.0)
            ->and($result['rnrb_status'])->toBe('full');
    });

    it('caps at the net-of-mortgage value of the residence', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        $spouse = User::factory()->create();

        // £400k home carrying a £250k mortgage → net £150k caps the RNRB.
        $property = Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 400_000,
        ]);

        Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'outstanding_balance' => 250_000,
        ]);

        FamilyMember::factory()->child()->create(['user_id' => $user->id]);

        $result = $this->service->calculate($user, $spouse);

        expect($result['rnrb_available'])->toBe(150_000.0)
            ->and($result['rnrb_status'])->toBe('residence_capped');
    });
});

describe('Charitable legacy exemption (Fix 3)', function () {
    it('excludes a qualifying charity gift from the taxable estate', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        // £1,000,000 cash estate, no property (so RNRB = £0 and the maths is clean).
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        // 10% of the net estate to charity → qualifies for the 36% reduced rate AND
        // is fully exempt from the estate.
        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => 'single',
            'charitable_giving_percent' => 10,
        ]);

        $result = $this->service->calculate($user);

        $charitableAmount = 100_000.0; // 10% of £1,000,000

        expect($result['total_net_estate'])->toBe(1_000_000.0)
            ->and($result['charitable_deduction'])->toBe($charitableAmount)
            // 36% reduced rate applies for a qualifying donor.
            ->and($result['iht_rate'])->toBe(0.36)
            // Charity gift is removed from the taxable estate (in addition to the NRB).
            ->and($result['taxable_estate'])->toBe(
                round($result['total_net_estate'] - $result['total_allowances'] - $charitableAmount, 2)
            )
            // £1,000,000 − £325,000 NRB − £100,000 charity = £575,000.
            ->and($result['taxable_estate'])->toBe(575_000.0)
            // IHT = £575,000 × 36% = £207,000.
            ->and($result['iht_liability'])->toBe(207_000.0);
    });

    it('deducts a below-threshold charity gift too (exempt even without the 36% rate)', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        // 5% to charity → below the 10% threshold (standard 40% rate) but the gift is
        // still fully exempt under s23.
        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => 'single',
            'charitable_giving_percent' => 5,
        ]);

        $result = $this->service->calculate($user);

        expect($result['charitable_deduction'])->toBe(50_000.0)
            ->and($result['iht_rate'])->toBe(0.40)
            ->and($result['taxable_estate'])->toBe(
                round($result['total_net_estate'] - $result['total_allowances'] - 50_000.0, 2)
            );
    });
});
