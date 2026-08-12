<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Services\Investment\PortfolioExposureService;

function exposureHolding(array $attributes): Holding
{
    return (new Holding)->forceFill($attributes);
}

describe('PortfolioExposureService', function () {
    it('classifies direct holdings and reports whole and classified exposure', function () {
        $result = app(PortfolioExposureService::class)->analyse(collect([
            exposureHolding([
                'security_name' => 'UK shares',
                'asset_type' => 'equity',
                'current_value' => 60000,
            ]),
            exposureHolding([
                'security_name' => 'Unknown structured product',
                'asset_type' => 'structured_product',
                'current_value' => 40000,
            ]),
        ]));

        expect($result['total_value'])->toBe(100000.0)
            ->and($result['classified_value'])->toBe(60000.0)
            ->and($result['unclassified_value'])->toBe(40000.0)
            ->and($result['coverage_percent'])->toBe(60.0)
            ->and($result['coverage_threshold_percent'])->toBe(80.0)
            ->and($result['drift_available'])->toBeFalse()
            ->and($result['allocation'])->toContain([
                'asset_class' => 'equities',
                'value' => 60000.0,
                'portfolio_percentage' => 60.0,
                'classified_percentage' => 100.0,
            ])
            ->and($result['allocation'])->toContain([
                'asset_class' => 'unclassified',
                'value' => 40000.0,
                'portfolio_percentage' => 40.0,
                'classified_percentage' => null,
            ]);
    });

    it('uses only recorded look-through data for composite holdings', function () {
        $result = app(PortfolioExposureService::class)->analyse(collect([
            exposureHolding([
                'security_name' => 'Entered multi-asset fund',
                'asset_type' => 'fund',
                'current_value' => 100000,
                'look_through_allocation' => [
                    'equities' => 55,
                    'bonds' => 35,
                    'cash' => 10,
                ],
                'look_through_source' => 'user_entered_fund_factsheet',
                'look_through_effective_at' => '2026-07-31',
            ]),
        ]));

        expect($result['coverage_percent'])->toBe(100.0)
            ->and($result['allocation'])->toContain([
                'asset_class' => 'equities',
                'value' => 55000.0,
                'portfolio_percentage' => 55.0,
                'classified_percentage' => 55.0,
            ])
            ->and($result['holdings'][0]['classification']['method'])->toBe('recorded_look_through')
            ->and($result['holdings'][0]['classification']['source'])->toBe('user_entered_fund_factsheet')
            ->and($result['holdings'][0]['classification']['effective_at'])->toBe('2026-07-31');
    });

    it('keeps an unrecorded mixed fund unclassified instead of inventing a mix', function () {
        $result = app(PortfolioExposureService::class)->analyse(collect([
            exposureHolding([
                'security_name' => 'Unknown balanced fund',
                'asset_type' => 'fund',
                'current_value' => 100000,
            ]),
        ]));

        expect($result['classified_value'])->toBe(0.0)
            ->and($result['unclassified_value'])->toBe(100000.0)
            ->and($result['coverage_percent'])->toBe(0.0)
            ->and($result['allocation'])->toBe([[
                'asset_class' => 'unclassified',
                'value' => 100000.0,
                'portfolio_percentage' => 100.0,
                'classified_percentage' => null,
            ]]);
    });

    it('shows entered and recommended drift when classified coverage is safe', function () {
        $result = app(PortfolioExposureService::class)->analyse(
            collect([
                exposureHolding(['security_name' => 'Shares', 'asset_type' => 'equity', 'current_value' => 70000]),
                exposureHolding(['security_name' => 'Gilts', 'asset_type' => 'bond', 'current_value' => 30000]),
            ]),
            [
                'allocation' => ['equities' => 60, 'bonds' => 40],
                'source' => 'user_entered',
                'effective_at' => '2026-04-06',
            ],
            [
                'allocation' => ['equities' => 50, 'bonds' => 40, 'cash' => 10],
                'source' => 'fynla_recommendation',
                'effective_at' => '2026-08-01',
            ],
        );

        expect($result['drift_available'])->toBeTrue()
            ->and($result['comparisons']['entered']['source'])->toBe('user_entered')
            ->and($result['comparisons']['entered']['effective_at'])->toBe('2026-04-06')
            ->and($result['comparisons']['entered']['drift_percentage_points'])->toBe([
                'equities' => 10.0,
                'bonds' => -10.0,
            ])
            ->and($result['comparisons']['recommended']['drift_percentage_points'])->toBe([
                'equities' => 20.0,
                'bonds' => -10.0,
                'cash' => -10.0,
            ]);
    });

    it('suppresses both drift comparisons when coverage is below the threshold', function () {
        $result = app(PortfolioExposureService::class)->analyse(
            collect([
                exposureHolding(['security_name' => 'Shares', 'asset_type' => 'equity', 'current_value' => 79000]),
                exposureHolding(['security_name' => 'Unclassified fund', 'asset_type' => 'fund', 'current_value' => 21000]),
            ]),
            ['allocation' => ['equities' => 60, 'bonds' => 40], 'source' => 'user_entered'],
            ['allocation' => ['equities' => 50, 'bonds' => 50], 'source' => 'fynla_recommendation'],
        );

        expect($result['coverage_percent'])->toBe(79.0)
            ->and($result['drift_available'])->toBeFalse()
            ->and($result['comparisons']['entered']['drift_percentage_points'])->toBeNull()
            ->and($result['comparisons']['recommended']['drift_percentage_points'])->toBeNull()
            ->and($result['comparisons']['entered']['unavailable_reason'])->toBe('classification_coverage_below_threshold');
    });

    it('leaves any unrecorded remainder of a partial look-through unclassified', function () {
        $result = app(PortfolioExposureService::class)->analyse(collect([
            exposureHolding([
                'security_name' => 'Partially classified fund',
                'asset_type' => 'fund',
                'current_value' => 100000,
                'look_through_allocation' => ['equities' => 50, 'bonds' => 30],
                'look_through_source' => 'provider_factsheet',
            ]),
        ]));

        expect($result['classified_value'])->toBe(80000.0)
            ->and($result['unclassified_value'])->toBe(20000.0)
            ->and($result['coverage_percent'])->toBe(80.0)
            ->and($result['drift_available'])->toBeTrue();
    });
});
