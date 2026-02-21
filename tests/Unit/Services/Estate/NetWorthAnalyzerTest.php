<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\Estate\Liability;
use App\Models\User;
use App\Services\Estate\NetWorthAnalyzer;
use App\Services\Shared\CrossModuleAssetAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $aggregator = new CrossModuleAssetAggregator;
    $this->analyzer = new NetWorthAnalyzer($aggregator);
    $this->user = User::factory()->create();
});

describe('calculateNetWorth', function () {
    it('calculates net worth correctly', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'investment',
            'asset_name' => 'ISA',
            'current_value' => 50000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Home Mortgage',
            'current_balance' => 200000,
        ]);

        $result = $this->analyzer->calculateNetWorth($this->user->id);

        expect($result['total_assets'])->toBe(550000.0)
            ->and($result['total_liabilities'])->toBe(200000.0)
            ->and($result['net_worth'])->toBe(350000.0)
            ->and(round($result['debt_to_asset_ratio'], 4))->toBe(0.3636);
    });

    it('handles zero assets and liabilities', function () {
        $result = $this->analyzer->calculateNetWorth($this->user->id);

        expect($result['total_assets'])->toBe(0.0)
            ->and($result['total_liabilities'])->toBe(0.0)
            ->and($result['net_worth'])->toBe(0.0)
            ->and($result['debt_to_asset_ratio'])->toBe(0.0);
    });
});

describe('analyzeAssetComposition', function () {
    it('groups assets by type and calculates percentages', function () {
        $assets = collect([
            new Asset(['asset_type' => 'property', 'current_value' => 500000]),
            new Asset(['asset_type' => 'investment', 'current_value' => 100000]),
            new Asset(['asset_type' => 'investment', 'current_value' => 50000]),
        ]);

        $result = $this->analyzer->analyzeAssetComposition($assets);

        expect($result)->toHaveCount(2)
            ->and($result[0]['type'])->toBe('property')
            ->and($result[0]['value'])->toBe(500000.0)
            ->and(round($result[0]['percentage'], 2))->toBe(76.92)
            ->and($result[1]['type'])->toBe('investment')
            ->and($result[1]['value'])->toBe(150000.0)
            ->and(round($result[1]['percentage'], 2))->toBe(23.08);
    });

    it('returns empty array when no assets', function () {
        $result = $this->analyzer->analyzeAssetComposition(collect([]));

        expect($result)->toBe([]);
    });
});

describe('identifyConcentrationRisk', function () {
    it('identifies high concentration risk for single asset over 50%', function () {
        $assets = collect([
            new Asset([
                'asset_name' => 'Main Property',
                'asset_type' => 'property',
                'current_value' => 800000,
            ]),
            new Asset([
                'asset_name' => 'Investments',
                'asset_type' => 'investment',
                'current_value' => 100000,
            ]),
        ]);

        $result = $this->analyzer->identifyConcentrationRisk($assets);

        expect($result['has_concentration_risk'])->toBe(true)
            ->and($result['risk_count'])->toBeGreaterThan(0)
            ->and($result['risks'][0]['severity'])->toBe('High');
    });

    it('identifies medium concentration risk for asset over 30%', function () {
        $assets = collect([
            new Asset([
                'asset_name' => 'Property',
                'asset_type' => 'property',
                'current_value' => 400000,
            ]),
            new Asset([
                'asset_name' => 'Investments',
                'asset_type' => 'investment',
                'current_value' => 600000,
            ]),
        ]);

        $result = $this->analyzer->identifyConcentrationRisk($assets);

        expect($result['has_concentration_risk'])->toBe(true)
            ->and($result['risks'][0]['severity'])->toBeIn(['Medium', 'High']);
    });

    it('identifies asset type concentration over 70%', function () {
        $assets = collect([
            new Asset([
                'asset_name' => 'Property 1',
                'asset_type' => 'property',
                'current_value' => 500000,
            ]),
            new Asset([
                'asset_name' => 'Property 2',
                'asset_type' => 'property',
                'current_value' => 300000,
            ]),
            new Asset([
                'asset_name' => 'Investment',
                'asset_type' => 'investment',
                'current_value' => 100000,
            ]),
        ]);

        $result = $this->analyzer->identifyConcentrationRisk($assets);

        $hasTypeConcentration = collect($result['risks'])
            ->contains(fn ($risk) => $risk['type'] === 'Asset Type Concentration');

        expect($hasTypeConcentration)->toBe(true);
    });

    it('returns no risk for well-diversified portfolio', function () {
        $assets = collect([
            new Asset(['asset_type' => 'property', 'current_value' => 250000]),
            new Asset(['asset_type' => 'investment', 'current_value' => 250000]),
            new Asset(['asset_type' => 'pension', 'current_value' => 250000]),
            new Asset(['asset_type' => 'business', 'current_value' => 250000]),
        ]);

        $result = $this->analyzer->identifyConcentrationRisk($assets);

        expect($result['has_concentration_risk'])->toBe(false)
            ->and($result['risk_count'])->toBe(0);
    });
});

describe('generateSummary', function () {
    it('generates comprehensive net worth summary', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 500000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Mortgage',
            'current_balance' => 200000,
        ]);

        $result = $this->analyzer->generateSummary($this->user->id);

        expect($result)->toHaveKeys(['net_worth', 'concentration_risk', 'health_score'])
            ->and($result['net_worth']['net_worth'])->toBe(300000.0)
            ->and($result['health_score'])->toHaveKeys(['score', 'grade', 'factors']);
    });

    it('calculates health score with positive net worth bonus', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'investment',
            'asset_name' => 'Portfolio',
            'current_value' => 100000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        $result = $this->analyzer->generateSummary($this->user->id);

        $hasPositiveBonus = collect($result['health_score']['factors'])
            ->contains(fn ($f) => $f['factor'] === 'Positive Net Worth');

        expect($hasPositiveBonus)->toBe(true);
    });

    it('penalizes high debt-to-asset ratio in health score', function () {
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Home',
            'current_value' => 300000,
            'ownership_type' => 'individual',
            'valuation_date' => Carbon::now(),
        ]);

        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Mortgage',
            'current_balance' => 250000, // Over 50% debt ratio
        ]);

        $result = $this->analyzer->generateSummary($this->user->id);

        $hasDebtPenalty = collect($result['health_score']['factors'])
            ->contains(fn ($f) => $f['factor'] === 'High Debt Ratio');

        expect($hasDebtPenalty)->toBe(true);
    });
});
