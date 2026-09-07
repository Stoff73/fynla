<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Property\PropertyService;
use App\Services\Tax\IncomeDefinitionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * W-0175 — the income page stated one person's rental income two ways: the tax
 * computation used the property-business profit while the allowance panel
 * re-derived a gross figure of its own, so a single screen taxed one income and
 * tested the £100,000 Personal Allowance taper against another.
 *
 * There is now one definition with one home — PropertyService::
 * annualRentalTaxPosition() — and these tests hold every consumer to it,
 * including when the underlying property record moves.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    // Arranging property fixtures only; the recalculation observers are
    // irrelevant to the figures under test.
    $this->modelEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    $this->owner = User::factory()->create(['annual_employment_income' => 120_000]);
    $this->jointOwner = User::factory()->create();

    // £1,800/month let, half-owned. Allowable letting expenses of £420/month:
    // buildings insurance £35, service charge £285 and a £100/month maintenance
    // reserve, which W-0178 made deductible on CSJ's ruling.
    $this->property = Property::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->jointOwner->id,
        'property_type' => 'buy_to_let',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'monthly_rental_income' => 1_800,
        'monthly_building_insurance' => 35,
        'monthly_service_charge' => 285,
        'monthly_maintenance_reserve' => 100,
    ]);
});

afterEach(function () {
    Model::setEventDispatcher($this->modelEventDispatcher);
});

describe('one rental figure, one home', function () {
    it('gives the income definitions the same figure the tax position does', function () {
        $fromPropertyService = app(PropertyService::class)->annualRentalTaxPosition($this->owner)['total'];
        $definitions = app(IncomeDefinitionsService::class)->calculate($this->owner->id);

        // (£21,600 rent − £5,040 allowable) × 50% = £8,280. NOT £10,800 gross.
        expect($fromPropertyService)->toBe(8_280.0)
            ->and($definitions['components']['rental'])->toBe(8_280.0);
    });

    it('carries that figure into total, adjusted net and threshold income alike', function () {
        $definitions = app(IncomeDefinitionsService::class)->calculate($this->owner->id);

        expect($definitions['total_income'])->toBe(128_280.0)
            ->and($definitions['adjusted_net_income'])->toBe(128_280.0)
            ->and($definitions['threshold_income'])->toBe(128_280.0);
    });

    it('gives the joint owner the other half from the same definition', function () {
        $definitions = app(IncomeDefinitionsService::class)->calculate($this->jointOwner->id);

        expect(app(PropertyService::class)->annualRentalTaxPosition($this->jointOwner)['total'])->toBe(8_280.0)
            ->and($definitions['components']['rental'])->toBe(8_280.0);
    });
});

describe('the figure is the property-business profit, and it moves with the record', function () {
    it('deducts allowable letting expenses', function () {
        $this->property->update(['monthly_service_charge' => 385]);

        // Another £100/month off the rent, halved: £8,280 − £600 = £7,680.
        $definitions = app(IncomeDefinitionsService::class)->calculate($this->owner->id);

        expect(app(PropertyService::class)->annualRentalTaxPosition($this->owner)['total'])->toBe(7_680.0)
            ->and($definitions['components']['rental'])->toBe(7_680.0);
    });

    // This asserted the opposite until 2026-09-01: it pinned the reserve as NOT
    // deducted, which was the behaviour of the day rather than a rule of law. CSJ
    // ruled on W-0178 that a rental profit which ignores what the landlord spends
    // is not a profit figure, so the assertion is inverted rather than deleted —
    // the figure still has to move with the record, just in the other direction.
    it('deducts the maintenance reserve (W-0178)', function () {
        $this->property->update(['monthly_maintenance_reserve' => 500]);

        // £400/month more expense, £4,800/year, halved: £8,280 − £2,400 = £5,880.
        expect(app(IncomeDefinitionsService::class)->calculate($this->owner->id)['components']['rental'])
            ->toBe(5_880.0);
    });

    it('deducts the uncategorised other monthly costs too (W-0178)', function () {
        $this->property->update(['other_monthly_costs' => 60]);

        // £720/year more expense, halved: £8,280 − £360 = £7,920.
        expect(app(IncomeDefinitionsService::class)->calculate($this->owner->id)['components']['rental'])
            ->toBe(7_920.0);
    });

    it('follows the rent', function () {
        $this->property->update(['monthly_rental_income' => 2_000]);

        // (£24,000 − £5,040) × 50% = £9,480.
        $definitions = app(IncomeDefinitionsService::class)->calculate($this->owner->id);

        expect(app(PropertyService::class)->annualRentalTaxPosition($this->owner)['total'])->toBe(9_480.0)
            ->and($definitions['components']['rental'])->toBe(9_480.0);
    });

    it('follows the ownership split on both sides at once', function () {
        $this->property->update(['ownership_percentage' => 40.00]);

        // £16,560 profit: 40% to the primary owner, 60% to the joint owner.
        expect(app(IncomeDefinitionsService::class)->calculate($this->owner->id)['components']['rental'])->toBe(6_624.0)
            ->and(app(IncomeDefinitionsService::class)->calculate($this->jointOwner->id)['components']['rental'])->toBe(9_936.0);
    });
});
