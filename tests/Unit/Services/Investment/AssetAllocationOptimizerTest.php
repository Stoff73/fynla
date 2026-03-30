<?php

declare(strict_types=1);

use App\Models\Investment\RiskProfile;
use App\Services\Investment\SimpleAssetAllocationOptimizer;

beforeEach(function () {
    $this->optimizer = new SimpleAssetAllocationOptimizer;
});

describe('getTargetAllocation', function () {
    it('returns cautious allocation for cautious risk profile', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'cautious',
        ]);

        $allocation = $this->optimizer->getTargetAllocation($profile);

        // Cautious maps to risk level 1 via InvestmentDefaults
        expect($allocation)->toHaveCount(4)
            ->and($allocation[0]['asset_type'])->toBe('equities')
            ->and($allocation[0]['percentage'])->toBe(10.0)
            ->and($allocation[1]['asset_type'])->toBe('bonds')
            ->and($allocation[1]['percentage'])->toBe(70.0)
            ->and($allocation[2]['asset_type'])->toBe('cash')
            ->and($allocation[2]['percentage'])->toBe(20.0)
            ->and($allocation[3]['asset_type'])->toBe('alternatives')
            ->and($allocation[3]['percentage'])->toBe(0.0);
    });

    it('returns balanced allocation for balanced risk profile', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'balanced',
        ]);

        $allocation = $this->optimizer->getTargetAllocation($profile);

        // Balanced maps to risk level 3 via InvestmentDefaults
        expect($allocation[0]['percentage'])->toBe(50.0) // equities
            ->and($allocation[1]['percentage'])->toBe(40.0) // bonds
            ->and($allocation[2]['percentage'])->toBe(5.0) // cash
            ->and($allocation[3]['percentage'])->toBe(5.0); // alternatives
    });

    it('returns adventurous allocation for adventurous risk profile', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'adventurous',
        ]);

        $allocation = $this->optimizer->getTargetAllocation($profile);

        // Adventurous maps to risk level 5 via InvestmentDefaults
        expect($allocation[0]['percentage'])->toBe(90.0) // equities
            ->and($allocation[1]['percentage'])->toBe(5.0) // bonds
            ->and($allocation[2]['percentage'])->toBe(0.0) // cash
            ->and($allocation[3]['percentage'])->toBe(5.0); // alternatives
    });

    it('total allocation sums to 100%', function () {
        $profile = new RiskProfile(['risk_tolerance' => 'balanced']);

        $allocation = $this->optimizer->getTargetAllocation($profile);

        $total = array_sum(array_column($allocation, 'percentage'));

        expect($total)->toBe(100.0);
    });
});

describe('calculateDeviation', function () {
    it('calculates deviation between current and target allocation', function () {
        $current = [
            ['asset_type' => 'equity', 'percentage' => 70],
            ['asset_type' => 'bond', 'percentage' => 20],
            ['asset_type' => 'cash', 'percentage' => 10],
        ];

        $target = [
            ['asset_type' => 'equity', 'percentage' => 60],
            ['asset_type' => 'bond', 'percentage' => 30],
            ['asset_type' => 'cash', 'percentage' => 10],
        ];

        $result = $this->optimizer->calculateDeviation($current, $target);

        expect($result['deviations'])->toHaveCount(3)
            ->and($result['deviations'][0]['asset_type'])->toBe('equity')
            ->and($result['deviations'][0]['current'])->toBe(70.0)
            ->and($result['deviations'][0]['target'])->toBe(60.0)
            ->and($result['deviations'][0]['difference'])->toBe(10.0)
            ->and($result['deviations'][0]['status'])->toBe('overweight');
    });

    it('identifies underweight positions', function () {
        $current = [
            ['asset_type' => 'bond', 'percentage' => 15],
        ];

        $target = [
            ['asset_type' => 'bond', 'percentage' => 30],
        ];

        $result = $this->optimizer->calculateDeviation($current, $target);

        expect($result['deviations'][0]['status'])->toBe('underweight')
            ->and($result['deviations'][0]['difference'])->toBe(-15.0);
    });

    it('determines if rebalancing is needed', function () {
        $current = [
            ['asset_type' => 'equity', 'percentage' => 80],
            ['asset_type' => 'bond', 'percentage' => 20],
        ];

        $target = [
            ['asset_type' => 'equity', 'percentage' => 60],
            ['asset_type' => 'bond', 'percentage' => 40],
        ];

        $result = $this->optimizer->calculateDeviation($current, $target);

        // Deviation of 20% should trigger rebalancing
        expect($result['needs_rebalancing'])->toBe(true);
    });

    it('does not trigger rebalancing for small deviations', function () {
        $current = [
            ['asset_type' => 'equity', 'percentage' => 62],
            ['asset_type' => 'bond', 'percentage' => 38],
        ];

        $target = [
            ['asset_type' => 'equity', 'percentage' => 60],
            ['asset_type' => 'bond', 'percentage' => 40],
        ];

        $result = $this->optimizer->calculateDeviation($current, $target);

        // Small deviation should not trigger rebalancing
        expect($result['needs_rebalancing'])->toBe(false);
    });

    it('handles missing asset types in current allocation', function () {
        $current = [
            ['asset_type' => 'equity', 'percentage' => 100],
        ];

        $target = [
            ['asset_type' => 'equity', 'percentage' => 60],
            ['asset_type' => 'bond', 'percentage' => 30],
            ['asset_type' => 'cash', 'percentage' => 10],
        ];

        $result = $this->optimizer->calculateDeviation($current, $target);

        expect($result['deviations'])->toHaveCount(3)
            ->and($result['deviations'][1]['current'])->toBe(0.0) // bond
            ->and($result['deviations'][2]['current'])->toBe(0.0); // cash
    });
});

describe('suggestNewInvestorAllocation', function () {
    it('calculates age-based equity allocation', function () {
        $age = 30;

        $allocation = $this->optimizer->suggestNewInvestorAllocation($age);

        // 100 - 30 = 70% equity
        expect($allocation[0]['asset_type'])->toBe('equity')
            ->and($allocation[0]['percentage'])->toBe(70.0)
            ->and($allocation[1]['asset_type'])->toBe('bond')
            ->and($allocation[1]['percentage'])->toBe(20.0)
            ->and($allocation[2]['asset_type'])->toBe('cash')
            ->and($allocation[2]['percentage'])->toBe(10.0);
    });

    it('reduces equity allocation for older investors', function () {
        $allocation25 = $this->optimizer->suggestNewInvestorAllocation(25);
        $allocation60 = $this->optimizer->suggestNewInvestorAllocation(60);

        $equity25 = $allocation25[0]['percentage'];
        $equity60 = $allocation60[0]['percentage'];

        expect($equity25)->toBeGreaterThan($equity60);
    });

    it('enforces minimum equity allocation', function () {
        $age = 90; // 100 - 90 = 10%, but minimum is 20%

        $allocation = $this->optimizer->suggestNewInvestorAllocation($age);

        expect($allocation[0]['percentage'])->toBe(20.0); // Minimum equity
    });

    it('enforces maximum equity allocation', function () {
        $age = 20; // 100 - 20 = 80%

        $allocation = $this->optimizer->suggestNewInvestorAllocation($age);

        expect($allocation[0]['percentage'])->toBe(80.0); // Maximum equity
    });

    it('total allocation sums to 100%', function () {
        $allocation = $this->optimizer->suggestNewInvestorAllocation(45);

        $total = array_sum(array_column($allocation, 'percentage'));

        expect($total)->toBe(100.0);
    });
});

describe('edge cases', function () {
    it('handles extreme risk profiles', function () {
        $cautious = new RiskProfile(['risk_tolerance' => 'cautious']);
        $adventurous = new RiskProfile(['risk_tolerance' => 'adventurous']);

        $cautiousAllocation = $this->optimizer->getTargetAllocation($cautious);
        $adventurousAllocation = $this->optimizer->getTargetAllocation($adventurous);

        $cautiousEquity = collect($cautiousAllocation)->firstWhere('asset_type', 'equities')['percentage'];
        $adventurousEquity = collect($adventurousAllocation)->firstWhere('asset_type', 'equities')['percentage'];

        expect($adventurousEquity)->toBeGreaterThan($cautiousEquity);
    });

});
