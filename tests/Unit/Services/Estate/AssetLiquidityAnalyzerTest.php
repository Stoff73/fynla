<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Services\Estate\AssetLiquidityAnalyzer;

beforeEach(function () {
    $this->analyzer = new AssetLiquidityAnalyzer;
});

describe('AssetLiquidityAnalyzer', function () {
    describe('analyzeAssetLiquidity', function () {
        it('classifies liquid and semi-liquid assets correctly', function () {
            $assets = collect([
                new Asset([
                    'id' => 1,
                    'asset_name' => 'Cash Savings',
                    'asset_type' => 'other',
                    'current_value' => 50000,
                ]),
                new Asset([
                    'id' => 2,
                    'asset_name' => 'Investment Portfolio',
                    'asset_type' => 'investment',
                    'current_value' => 120000,
                ]),
            ]);

            $result = $this->analyzer->analyzeAssetLiquidity($assets);

            // Cash/other = liquid, investments = semi-liquid
            expect($result['liquid']['count'])->toBe(1);
            expect($result['liquid']['total_value'])->toBe(50000.0);
            expect($result['semi_liquid']['count'])->toBe(1);
            expect($result['semi_liquid']['total_value'])->toBe(120000.0);
            expect($result['illiquid']['count'])->toBe(0);
        });

        it('classifies semi-liquid assets correctly', function () {
            $assets = collect([
                new Asset([
                    'id' => 1,
                    'asset_name' => 'Buy-to-Let Property',
                    'asset_type' => 'property',
                    'current_value' => 280000,
                    'is_main_residence' => false,
                ]),
            ]);

            $result = $this->analyzer->analyzeAssetLiquidity($assets);

            expect($result['semi_liquid']['count'])->toBe(1);
            expect($result['semi_liquid']['total_value'])->toBe(280000.0);
        });

        it('classifies main residence as illiquid', function () {
            $assets = collect([
                new Asset([
                    'id' => 1,
                    'asset_name' => 'Main Family Home',
                    'asset_type' => 'property',
                    'current_value' => 650000,
                    'is_main_residence' => true,
                ]),
            ]);

            $result = $this->analyzer->analyzeAssetLiquidity($assets);

            expect($result['illiquid']['count'])->toBe(1);
            expect($result['illiquid']['total_value'])->toBe(650000.0);
            expect($result['illiquid']['assets'][0]['is_giftable'])->toBe(false);
            expect($result['illiquid']['assets'][0]['not_giftable_reason'])
                ->toContain('gift with reservation of benefit');
        });

        it('calculates summary correctly', function () {
            $assets = collect([
                new Asset([
                    'id' => 1,
                    'asset_name' => 'Cash',
                    'asset_type' => 'other',
                    'current_value' => 50000,
                ]),
                new Asset([
                    'id' => 2,
                    'asset_name' => 'Rental Property',
                    'asset_type' => 'property',
                    'current_value' => 280000,
                    'is_main_residence' => false,
                ]),
                new Asset([
                    'id' => 3,
                    'asset_name' => 'Main Home',
                    'asset_type' => 'property',
                    'current_value' => 650000,
                    'is_main_residence' => true,
                ]),
            ]);

            $result = $this->analyzer->analyzeAssetLiquidity($assets);

            expect($result['summary']['total_giftable_value'])->toBe(330000.0);
            expect($result['summary']['total_value'])->toBe(980000.0);
            expect($result['summary']['giftable_percentage'])->toBe(33.7);
        });
    });

    describe('classifyAsset', function () {
        it('classifies investment assets as semi-liquid', function () {
            $asset = new Asset([
                'asset_type' => 'investment',
                'asset_name' => 'Stocks & Shares ISA',
            ]);

            $result = $this->analyzer->classifyAsset($asset);

            expect($result['liquidity'])->toBe('semi_liquid');
            expect($result['is_giftable'])->toBe(true);
            expect($result['gifting_considerations'])->toBeArray();
        });

        it('classifies pension as illiquid and not giftable', function () {
            $asset = new Asset([
                'asset_type' => 'pension',
                'asset_name' => 'Company Pension',
            ]);

            $result = $this->analyzer->classifyAsset($asset);

            expect($result['liquidity'])->toBe('illiquid');
            expect($result['is_giftable'])->toBe(false);
            expect($result['not_giftable_reason'])
                ->toContain('cannot be used to pay Inheritance Tax');
        });

        it('classifies rental property as semi-liquid', function () {
            $asset = new Asset([
                'asset_type' => 'property',
                'asset_name' => 'Buy-to-Let',
                'is_main_residence' => false,
            ]);

            $result = $this->analyzer->classifyAsset($asset);

            expect($result['liquidity'])->toBe('semi_liquid');
            expect($result['is_giftable'])->toBe(true);
        });

        it('classifies main residence as illiquid', function () {
            $asset = new Asset([
                'asset_type' => 'property',
                'asset_name' => 'Family Home',
                'is_main_residence' => true,
            ]);

            $result = $this->analyzer->classifyAsset($asset);

            expect($result['liquidity'])->toBe('illiquid');
            expect($result['is_giftable'])->toBe(false);
        });

        it('identifies main residence by name keywords', function () {
            $asset = new Asset([
                'asset_type' => 'property',
                'asset_name' => 'Main Residence in London',
                'is_main_residence' => false, // Not explicitly marked
            ]);

            $result = $this->analyzer->classifyAsset($asset);

            expect($result['liquidity'])->toBe('illiquid');
            expect($result['is_giftable'])->toBe(false);
        });
    });

    describe('calculateMaximumGiftableAmount', function () {
        it('calculates immediately giftable amount correctly', function () {
            $assets = collect([
                new Asset([
                    'asset_name' => 'Cash',
                    'asset_type' => 'other',
                    'current_value' => 50000,
                ]),
                new Asset([
                    'asset_name' => 'Investments',
                    'asset_type' => 'investment',
                    'current_value' => 120000,
                ]),
            ]);

            $result = $this->analyzer->calculateMaximumGiftableAmount($assets);

            // Only cash/other is liquid; investments are semi-liquid
            expect($result['immediately_giftable'])->toBe(50000.0);
            expect($result['giftable_with_planning'])->toBe(120000.0);
            expect($result['liquid_asset_count'])->toBe(1);
            expect($result['semi_liquid_asset_count'])->toBe(1);
        });

        it('calculates giftable with planning correctly', function () {
            $assets = collect([
                new Asset([
                    'asset_name' => 'Rental Property',
                    'asset_type' => 'property',
                    'current_value' => 280000,
                    'is_main_residence' => false,
                ]),
            ]);

            $result = $this->analyzer->calculateMaximumGiftableAmount($assets);

            expect($result['giftable_with_planning'])->toBe(280000.0);
            expect($result['semi_liquid_asset_count'])->toBe(1);
        });

        it('excludes illiquid assets from giftable amount', function () {
            $assets = collect([
                new Asset([
                    'asset_name' => 'Cash',
                    'asset_type' => 'other',
                    'current_value' => 50000,
                ]),
                new Asset([
                    'asset_name' => 'Main Home',
                    'asset_type' => 'property',
                    'current_value' => 650000,
                    'is_main_residence' => true,
                ]),
            ]);

            $result = $this->analyzer->calculateMaximumGiftableAmount($assets);

            expect($result['total_giftable'])->toBe(50000.0);
            expect($result['not_giftable'])->toBe(650000.0);
        });
    });
});
