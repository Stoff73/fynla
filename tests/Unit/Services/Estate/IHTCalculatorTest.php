<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Services\Estate\IHTCalculator;
use App\Services\TaxConfigService;
use Carbon\Carbon;

beforeEach(function () {
    // Mock TaxConfigService
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('getInheritanceTax')
        ->andReturn([
            'nil_rate_band' => 325000,
            'residence_nil_rate_band' => 175000,
            'rnrb_taper_threshold' => 2000000,
            'rnrb_taper_rate' => 0.5,
            'standard_rate' => 0.40,
            'reduced_rate_charity' => 0.36,
            'potentially_exempt_transfers' => [
                'years_to_exemption' => 7,
                'taper_relief' => [
                    ['years' => 3, 'rate' => 0.40],
                    ['years' => 4, 'rate' => 0.32],
                    ['years' => 5, 'rate' => 0.24],
                    ['years' => 6, 'rate' => 0.16],
                    ['years' => 7, 'rate' => 0.08],
                ],
            ],
            'chargeable_lifetime_transfers' => [
                'rate' => 0.20,
            ],
        ]);

    // Inject the mocked service
    $this->calculator = new IHTCalculator($mockTaxConfig);
});

describe('calculateIHTLiability', function () {
    it('calculates IHT liability with NRB only', function () {
        $assets = collect([
            new Asset(['current_value' => 500000]),
        ]);

        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'own_home' => false,
            'home_value' => 0,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->calculator->calculateIHTLiability($assets, $profile);

        expect($result['gross_estate_value'])->toBe(500000.0)
            ->and($result['nrb'])->toBe(325000.0)
            ->and($result['total_nrb'])->toBe(325000.0)
            ->and($result['rnrb'])->toBe(0.0)
            ->and($result['total_allowance'])->toBe(325000.0)
            ->and($result['taxable_estate'])->toBe(175000.0)
            ->and($result['iht_rate'])->toBe(0.40)
            ->and($result['iht_liability'])->toBe(70000.0);
    });

    it('calculates IHT liability with NRB and RNRB', function () {
        $assets = collect([
            new Asset(['current_value' => 800000]),
        ]);

        $profile = new IHTProfile([
            'marital_status' => 'married',
            'has_spouse' => true,
            'own_home' => true,
            'home_value' => 400000,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->calculator->calculateIHTLiability($assets, $profile);

        expect($result['gross_estate_value'])->toBe(800000.0)
            ->and($result['nrb'])->toBe(325000.0)
            ->and($result['rnrb'])->toBe(175000.0)
            ->and($result['rnrb_eligible'])->toBe(true)
            ->and($result['total_allowance'])->toBe(500000.0)
            ->and($result['taxable_estate'])->toBe(300000.0)
            ->and($result['iht_liability'])->toBe(120000.0);
    });

    it('applies spouse NRB transfer', function () {
        $assets = collect([
            new Asset(['current_value' => 700000]),
        ]);

        $profile = new IHTProfile([
            'marital_status' => 'widowed',
            'has_spouse' => false,
            'own_home' => false,
            'home_value' => 0,
            'nrb_transferred_from_spouse' => 325000,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->calculator->calculateIHTLiability($assets, $profile);

        expect($result['nrb_from_spouse'])->toBe(325000.0)
            ->and($result['total_nrb'])->toBe(650000.0)
            ->and($result['taxable_estate'])->toBe(50000.0)
            ->and($result['iht_liability'])->toBe(20000.0);
    });

    it('applies RNRB taper for estates over £2m', function () {
        $assets = collect([
            new Asset(['current_value' => 2200000]),
        ]);

        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'own_home' => true,
            'home_value' => 500000,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->calculator->calculateIHTLiability($assets, $profile);

        // RNRB should be tapered: £175,000 - ((£2,200,000 - £2,000,000) * 0.5) = £75,000
        expect($result['rnrb'])->toBe(75000.0)
            ->and($result['rnrb_eligible'])->toBe(true);
    });

    it('calculates zero IHT when estate is below NRB', function () {
        $assets = collect([
            new Asset(['current_value' => 300000]),
        ]);

        $profile = new IHTProfile([
            'marital_status' => 'single',
            'has_spouse' => false,
            'own_home' => false,
            'home_value' => 0,
            'nrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => 0,
        ]);

        $result = $this->calculator->calculateIHTLiability($assets, $profile);

        expect($result['iht_liability'])->toBe(0.0)
            ->and($result['taxable_estate'])->toBe(0.0);
    });
});

describe('checkRNRBEligibility', function () {
    it('returns true when own home', function () {
        $profile = new IHTProfile([
            'own_home' => true,
            'home_value' => 300000,
        ]);

        $assets = collect([]);

        $result = $this->calculator->checkRNRBEligibility($profile, $assets);

        expect($result)->toBe(true);
    });

    it('returns false when does not own home', function () {
        $profile = new IHTProfile([
            'own_home' => false,
            'home_value' => 0,
        ]);

        $assets = collect([]);

        $result = $this->calculator->checkRNRBEligibility($profile, $assets);

        expect($result)->toBe(false);
    });

    it('returns false when home value is zero', function () {
        $profile = new IHTProfile([
            'own_home' => true,
            'home_value' => 0,
        ]);

        $assets = collect([]);

        $result = $this->calculator->checkRNRBEligibility($profile, $assets);

        expect($result)->toBe(false);
    });
});

describe('calculateCharitableReduction', function () {
    it('returns 36% rate when charitable giving is 10% or more', function () {
        $rate = $this->calculator->calculateCharitableReduction(1000000, 10);

        expect($rate)->toBe(0.36);
    });

    it('returns 40% rate when charitable giving is less than 10%', function () {
        $rate = $this->calculator->calculateCharitableReduction(1000000, 5);

        expect($rate)->toBe(0.40);
    });

    it('returns 40% rate when no charitable giving', function () {
        $rate = $this->calculator->calculateCharitableReduction(1000000, 0);

        expect($rate)->toBe(0.40);
    });
});

describe('applyTaperRelief', function () {
    it('returns full 40% tax for gifts within 3 years', function () {
        $gift = new Gift([
            'gift_date' => Carbon::now()->subYears(2),
            'gift_value' => 100000,
        ]);

        $tax = $this->calculator->applyTaperRelief($gift);

        expect($tax)->toBe(40000.0); // 40% of 100000
    });

    it('applies 20% taper relief for gifts 3-4 years old', function () {
        $gift = new Gift([
            'gift_date' => Carbon::now()->subYears(3)->subMonths(6),
            'gift_value' => 100000,
        ]);

        $tax = $this->calculator->applyTaperRelief($gift);

        expect($tax)->toBe(32000.0); // 32% rate after 20% relief
    });

    it('applies 40% taper relief for gifts 4-5 years old', function () {
        $gift = new Gift([
            'gift_date' => Carbon::now()->subYears(4)->subMonths(6),
            'gift_value' => 100000,
        ]);

        $tax = $this->calculator->applyTaperRelief($gift);

        expect($tax)->toBe(24000.0); // 24% rate after 40% relief
    });

    it('applies 60% taper relief for gifts 5-6 years old', function () {
        $gift = new Gift([
            'gift_date' => Carbon::now()->subYears(5)->subMonths(6),
            'gift_value' => 100000,
        ]);

        $tax = $this->calculator->applyTaperRelief($gift);

        expect($tax)->toBe(16000.0); // 16% rate after 60% relief
    });

    it('applies 80% taper relief for gifts 6-7 years old', function () {
        $gift = new Gift([
            'gift_date' => Carbon::now()->subYears(6)->subMonths(6),
            'gift_value' => 100000,
        ]);

        $tax = $this->calculator->applyTaperRelief($gift);

        expect($tax)->toBe(8000.0); // 8% rate after 80% relief
    });

    it('returns zero tax for gifts over 7 years old', function () {
        $gift = new Gift([
            'gift_date' => Carbon::now()->subYears(8),
            'gift_value' => 100000,
        ]);

        $tax = $this->calculator->applyTaperRelief($gift);

        expect($tax)->toBe(0.0); // Fully exempt
    });
});

describe('calculatePETLiability', function () {
    it('calculates total PET liability for multiple gifts', function () {
        $gifts = collect([
            new Gift([
                'id' => 1,
                'gift_date' => Carbon::now()->subYears(4),
                'recipient' => 'Child 1',
                'gift_type' => 'pet',
                'gift_value' => 200000,
            ]),
            new Gift([
                'id' => 2,
                'gift_date' => Carbon::now()->subYears(2),
                'recipient' => 'Child 2',
                'gift_type' => 'pet',
                'gift_value' => 200000,
            ]),
        ]);

        $result = $this->calculator->calculatePETLiability($gifts);

        // With gifts sorted by date (oldest first): £200k + £200k = £400k
        // Only the second gift pushes running total over £325k NRB
        expect($result['total_gift_value'])->toBe(400000.0)
            ->and($result['gift_count'])->toBe(2)
            ->and($result['gifts'])->toHaveCount(1); // Only the gift that exceeds NRB threshold
    });

    it('filters out gifts older than 7 years', function () {
        $gifts = collect([
            new Gift([
                'id' => 1,
                'gift_date' => Carbon::now()->subYears(8),
                'recipient' => 'Child',
                'gift_type' => 'pet',
                'gift_value' => 100000,
            ]),
        ]);

        $result = $this->calculator->calculatePETLiability($gifts);

        expect($result['total_gift_value'])->toBe(0.0)
            ->and($result['gift_count'])->toBe(0);
    });

    it('filters out non-PET gifts', function () {
        $gifts = collect([
            new Gift([
                'id' => 1,
                'gift_date' => Carbon::now()->subYears(2),
                'recipient' => 'Friend',
                'gift_type' => 'small_gift',
                'gift_value' => 250,
            ]),
        ]);

        $result = $this->calculator->calculatePETLiability($gifts);

        expect($result['total_gift_value'])->toBe(0.0)
            ->and($result['gift_count'])->toBe(0);
    });
});
