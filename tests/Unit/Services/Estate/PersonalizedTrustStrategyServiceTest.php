<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\Estate\IHTProfile;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\AssetLiquidityAnalyzer;
use App\Services\Estate\PersonalizedTrustStrategyService;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Settings\AssumptionsService;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->liquidityAnalyzer = new AssetLiquidityAnalyzer;
    $taxConfig = app(\App\Services\TaxConfigService::class);
    $assumptionsService = app(AssumptionsService::class);
    $riskPreferenceService = app(RiskPreferenceService::class);
    $this->service = new PersonalizedTrustStrategyService($this->liquidityAnalyzer, $taxConfig, $assumptionsService, $riskPreferenceService);

    $this->user = new User([
        'id' => 1,
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'test@example.com',
        'date_of_birth' => '1970-01-01',
        'gender' => 'male',
        'marital_status' => 'single',
    ]);
    $this->user->age = 55;

    $this->profile = new IHTProfile([
        'user_id' => 1,
        'marital_status' => 'single',
        'available_nrb' => 325000,
        'own_home' => true,
        'home_value' => 500000,
    ]);
});

describe('PersonalizedTrustStrategyService', function () {
    it('generates trust strategy for liquid assets under NRB', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Savings Account',
                'current_value' => 200000,
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 80000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        expect($result)->toHaveKeys(['strategies', 'liquidity_analysis', 'giftable_amounts', 'strategy_impact', 'summary']);
        expect($result['strategies'])->toBeArray();
        expect($result['strategies'])->toHaveCount(5); // 5 strategies

        // Check Strategy 1: Immediate CLT
        $strategy1 = $result['strategies'][0];
        expect($strategy1['strategy_name'])->toBe('Immediate Discretionary Trust (CLT)');
        expect($strategy1['amount'])->toBe(200000.0);
        expect($strategy1['lifetime_tax_charge'])->toBe(0.0); // Within NRB
        expect($strategy1['priority'])->toBe(1);
    });

    it('calculates lifetime IHT charge correctly for amounts exceeding NRB', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Holdings',
                'current_value' => 500000, // £175k over NRB
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 200000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategy1 = $result['strategies'][0];

        // £500k - £325k NRB = £175k excess
        // 20% charge on excess = £35,000
        expect($strategy1['lifetime_tax_charge'])->toBe(35000.0);

        // Check tax treatment details
        expect($strategy1['tax_treatment']['immediate_charge'])->toBe(35000.0);
        expect($strategy1['tax_treatment']['death_within_7_years'])->toBe(70000.0); // 40% total = £70k
        expect($strategy1['tax_treatment']['after_7_years'])->toBe(35000.0); // Only lifetime charge remains
    });

    it('generates multi-cycle CLT strategy for large estates', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Large Cash Holdings',
                'current_value' => 1000000,
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 400000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategy2 = $result['strategies'][1]; // Multi-Cycle CLT Strategy

        expect($strategy2['strategy_name'])->toBe('Multi-Cycle CLT Strategy');
        expect($strategy2)->toHaveKey('clt_schedule');
        expect($strategy2['clt_schedule'])->toBeArray();

        // With 20 years, can have 2-3 cycles (7-year intervals)
        expect($strategy2['cycles_needed'])->toBeGreaterThanOrEqual(2);

        // Each cycle should use full NRB
        foreach ($strategy2['clt_schedule'] as $cycle) {
            expect($cycle['amount'])->toBe(325000.0);
            expect($cycle['immediate_charge'])->toBe(0.0); // Within NRB each cycle
        }
    });

    it('generates loan trust strategy with growth projection', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Reserve',
                'current_value' => 400000,
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 160000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategy3 = $result['strategies'][2]; // Loan Trust Strategy

        expect($strategy3['strategy_name'])->toBe('Loan Trust Strategy');
        expect($strategy3['amount'])->toBe(400000.0);
        expect($strategy3['lifetime_tax_charge'])->toBe(0.0); // No charge on loan
        expect($strategy3['potential_death_charge'])->toBe(0.0); // Loan stays in estate
        expect($strategy3['risk_level'])->toBe('Low');
    });

    it('generates discounted gift trust strategy with discount calculation', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Bond',
                'current_value' => 500000,
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 200000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategy4 = $result['strategies'][3]; // Discounted Gift Trust

        expect($strategy4['strategy_name'])->toBe('Discounted Gift Trust');
        expect($strategy4)->toHaveKey('discount_value');
        expect($strategy4)->toHaveKey('chargeable_amount');

        // Discount should reduce the chargeable amount
        expect($strategy4['chargeable_amount'])->toBeLessThan($strategy4['amount']);
        expect($strategy4['discount_value'])->toBeGreaterThan(0);
    });

    it('identifies main residence for property trust strategy', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'property',
                'asset_name' => 'Main Family Home',
                'current_value' => 600000,
            ]),
        ]);

        // Mark as main residence
        $assets->first()->is_main_residence = true;

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 240000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategy5 = $result['strategies'][4]; // Property Trust Planning

        expect($strategy5['strategy_name'])->toBe('Property Trust Planning');
        expect($strategy5['applicable'])->toBeTrue();
        expect($strategy5)->toHaveKey('property_details');
        expect($strategy5['property_details']['current_value'])->toBe(600000.0);
    });

    it('calculates taper relief correctly for multi-cycle death charge', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Portfolio',
                'current_value' => 650000,
            ]),
        ]);

        // Simulate death in 5 years (within 7-year window for first cycle)
        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 260000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 5 // Dies in 5 years
        );

        $strategy2 = $result['strategies'][1]; // Multi-Cycle CLT Strategy

        // Death at year 5 means taper relief applies (60% of 40% charge)
        expect($strategy2['potential_death_charge'])->toBeGreaterThan(0);
    });

    it('calculates overall strategy impact correctly', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Holdings',
                'current_value' => 800000,
            ]),
        ]);

        $currentIHTLiability = 320000.0;

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: $currentIHTLiability,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $impact = $result['strategy_impact'];

        expect($impact)->toHaveKeys([
            'total_amount_transferred',
            'total_iht_saving',
            'total_lifetime_charges',
            'total_potential_death_charges',
            'net_saving',
            'worst_case_cost',
            'worst_case_net_saving',
        ]);

        // Net saving should be IHT saving minus lifetime charges
        $expectedNetSaving = $impact['total_iht_saving'] - $impact['total_lifetime_charges'];
        expect($impact['net_saving'])->toBe($expectedNetSaving);

        // Worst case cost includes both lifetime and death charges
        expect($impact['worst_case_cost'])->toBeGreaterThanOrEqual($impact['total_lifetime_charges']);
    });

    it('generates appropriate summary and effectiveness rating', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Portfolio',
                'current_value' => 500000,
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 200000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $summary = $result['summary'];

        expect($summary)->toHaveKeys([
            'current_iht_liability',
            'total_strategies',
            'recommended_strategy',
            'maximum_estate_reduction',
            'maximum_iht_saving',
            'total_costs',
            'net_benefit',
            'effectiveness_rating',
        ]);

        expect($summary['current_iht_liability'])->toBe(200000.0);
        expect($summary['total_strategies'])->toBeGreaterThan(0);
        expect($summary['effectiveness_rating'])->toBeIn(['Excellent', 'Very Good', 'Good', 'Moderate', 'Limited', 'N/A - No IHT liability']);
    });

    it('filters eligible assets for each strategy based on liquidity', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Account',
                'current_value' => 100000,
            ]),
            new Asset([
                'asset_type' => 'property',
                'asset_name' => 'Main Home',
                'current_value' => 500000,
            ]),
        ]);

        $assets->last()->is_main_residence = true;

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 240000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategy1 = $result['strategies'][0]; // Immediate CLT (liquid only)
        expect($strategy1['eligible_assets'])->toHaveCount(1);
        expect($strategy1['eligible_assets'][0]['asset_type'])->toBe('cash');

        $strategy5 = $result['strategies'][4]; // Property Trust
        expect($strategy5['property_details']['property_name'])->toBe('Main Home');
    });

    it('handles zero IHT liability correctly', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Savings',
                'current_value' => 100000, // Well below NRB
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 0, // No IHT liability
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        expect($result['summary']['current_iht_liability'])->toBe(0.0);
        expect($result['summary']['effectiveness_rating'])->toBe('N/A - No IHT liability');
    });

    it('calculates giftable amounts by liquidity category', function () {
        $assets = collect([
            new Asset(['asset_type' => 'cash', 'asset_name' => 'Cash', 'current_value' => 100000]),
            new Asset(['asset_type' => 'investment', 'asset_name' => 'Investments', 'current_value' => 200000]),
            new Asset(['asset_type' => 'property', 'asset_name' => 'Rental Property', 'current_value' => 300000]),
            new Asset(['asset_type' => 'property', 'asset_name' => 'Main Home', 'current_value' => 500000]),
        ]);

        $assets->last()->is_main_residence = true;

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 400000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $giftableAmounts = $result['giftable_amounts'];

        // Cash = Liquid (investments are now semi-liquid)
        expect($giftableAmounts['immediately_giftable'])->toBe(100000.0);

        // Investments + Rental property = Semi-liquid
        expect($giftableAmounts['giftable_with_planning'])->toBe(500000.0);

        // Main residence = Illiquid (pensions also illiquid but none in this test)
        expect($giftableAmounts['not_giftable'])->toBe(500000.0);

        expect($giftableAmounts['total_giftable'])->toBe(600000.0);
    });

    it('prioritizes strategies correctly', function () {
        $assets = collect([
            new Asset([
                'asset_type' => 'cash',
                'asset_name' => 'Cash Portfolio',
                'current_value' => 800000,
            ]),
        ]);

        $result = $this->service->generatePersonalizedTrustStrategy(
            assets: $assets,
            currentIHTLiability: 320000,
            profile: $this->profile,
            user: $this->user,
            yearsUntilDeath: 20
        );

        $strategies = $result['strategies'];

        // Priorities should be 1, 2, 3, 4, 5
        expect($strategies[0]['priority'])->toBe(1); // Immediate CLT
        expect($strategies[1]['priority'])->toBe(2); // Multi-Cycle
        expect($strategies[2]['priority'])->toBe(3); // Loan Trust
        expect($strategies[3]['priority'])->toBe(4); // Discounted Gift
        expect($strategies[4]['priority'])->toBe(5); // Property Planning

        // Recommended strategy should be Priority 1
        expect($result['summary']['recommended_strategy'])->toBe($strategies[0]['strategy_name']);
    });
});
