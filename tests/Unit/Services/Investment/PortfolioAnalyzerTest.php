<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\RiskProfile;
use App\Services\Investment\PortfolioAnalyzer;

beforeEach(function () {
    $this->analyzer = new PortfolioAnalyzer;
});

/*
 * The `calculateTotalValue` describe block was deleted with the method it
 * covered (W-0238). Its three tests each asserted that a collection of accounts
 * sums to the whole of their values — true of the arithmetic, and the wrong
 * question, because a jointly held account is not wholly the viewer's. The
 * behaviour now lives in CrossModuleAssetAggregator::calculateInvestmentTotal,
 * which is covered against a real household in
 * tests/Feature/Dashboard/ModuleTotalsMatchNetWorthTest.php.
 */

describe('calculateReturns', function () {
    it('calculates gains and returns for holdings', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Stock A',
                'cost_basis' => 10000,
                'current_value' => 12000,
            ]),
            new Holding([
                'security_name' => 'Stock B',
                'cost_basis' => 5000,
                'current_value' => 4500,
            ]),
        ]);

        $returns = $this->analyzer->calculateReturns($holdings);

        expect($returns['total_cost_basis'])->toBe(15000.0)
            ->and($returns['total_current_value'])->toBe(16500.0)
            ->and($returns['total_gain'])->toBe(1500.0)
            ->and($returns['total_return_percent'])->toBe(10.0);
    });

    it('returns zero returns for empty holdings', function () {
        $holdings = collect([]);

        $returns = $this->analyzer->calculateReturns($holdings);

        expect($returns['total_return_percent'])->toBe(0.0)
            ->and($returns['total_gain'])->toBe(0.0);
    });

    it('handles negative returns correctly', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Stock C',
                'cost_basis' => 10000,
                'current_value' => 8000,
            ]),
        ]);

        $returns = $this->analyzer->calculateReturns($holdings);

        expect($returns['total_gain'])->toBe(-2000.0)
            ->and($returns['total_return_percent'])->toBe(-20.0);
    });
});

describe('calculateAssetAllocation', function () {
    it('calculates asset allocation by type', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 60000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 30000]),
            new Holding(['asset_type' => 'equity', 'current_value' => 10000]),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocation($holdings);

        expect($allocation)->toHaveCount(2)
            ->and($allocation[0]['asset_type'])->toBe('equity')
            ->and($allocation[0]['value'])->toBe(70000.0)
            ->and($allocation[0]['percentage'])->toBe(70.0)
            ->and($allocation[1]['asset_type'])->toBe('bond')
            ->and($allocation[1]['value'])->toBe(30000.0)
            ->and($allocation[1]['percentage'])->toBe(30.0);
    });

    it('returns empty array for empty holdings', function () {
        $holdings = collect([]);

        $allocation = $this->analyzer->calculateAssetAllocation($holdings);

        expect($allocation)->toBe([]);
    });

    it('sorts allocation by value descending', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'cash', 'current_value' => 5000]),
            new Holding(['asset_type' => 'equity', 'current_value' => 50000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 20000]),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocation($holdings);

        expect($allocation[0]['asset_type'])->toBe('equity')
            ->and($allocation[1]['asset_type'])->toBe('bond')
            ->and($allocation[2]['asset_type'])->toBe('cash');
    });
});

describe('calculateAssetAllocationWithLookThrough', function () {
    it('passes through direct equity holdings unchanged', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 50000, 'security_name' => 'BP plc']),
            new Holding(['asset_type' => 'bond', 'current_value' => 30000, 'security_name' => 'UK Gilt']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        $equityAlloc = collect($allocation)->firstWhere('asset_type', 'equities');
        $bondAlloc = collect($allocation)->firstWhere('asset_type', 'bonds');

        expect($equityAlloc['value'])->toBe(50000.0);
        expect($bondAlloc['value'])->toBe(30000.0);
    });

    it('keeps mixed funds without recorded look-through unclassified', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'fund', 'current_value' => 100000, 'security_name' => 'Vanguard LifeStrategy Balanced Fund']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        expect($allocation)->toBe([[
            'asset_type' => 'unclassified',
            'value' => 100000.0,
            'percentage' => 100.0,
        ]]);
    });

    it('classifies bond fund with sub_type as bonds', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'fund', 'sub_type' => 'bond_fund', 'current_value' => 50000, 'security_name' => 'iShares Corporate Bond Fund']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        expect($allocation)->toHaveCount(1);
        expect($allocation[0]['asset_type'])->toBe('bonds');
        expect($allocation[0]['value'])->toBe(50000.0);
    });

    it('keeps an ETF without a recorded type or look-through unclassified', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'etf', 'current_value' => 25000, 'security_name' => 'iShares UK Property REIT ETF']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        expect($allocation)->toHaveCount(1);
        expect($allocation[0]['asset_type'])->toBe('unclassified');
    });

    it('classifies money market fund with sub_type as cash', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'fund', 'sub_type' => 'money_market_fund', 'current_value' => 20000, 'security_name' => 'Royal London Money Market Fund']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        expect($allocation)->toHaveCount(1);
        expect($allocation[0]['asset_type'])->toBe('cash');
    });

    it('does not fabricate a split for an unknown fund', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'fund', 'current_value' => 30000, 'security_name' => 'XYZ Unknown Fund']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        expect($allocation)->toBe([[
            'asset_type' => 'unclassified',
            'value' => 30000.0,
            'percentage' => 100.0,
        ]]);
    });

    it('returns empty array for empty holdings', function () {
        $holdings = collect([]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        expect($allocation)->toBe([]);
    });

    it('combines direct holdings with explicit unclassified fund value', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 40000, 'security_name' => 'Lloyds Banking Group']),
            new Holding(['asset_type' => 'fund', 'current_value' => 100000, 'security_name' => 'Vanguard Multi-Asset Fund']),
        ]);

        $allocation = $this->analyzer->calculateAssetAllocationWithLookThrough($holdings);

        $equityAlloc = collect($allocation)->firstWhere('asset_type', 'equities');
        $unknownAlloc = collect($allocation)->firstWhere('asset_type', 'unclassified');
        expect($equityAlloc['value'])->toBe(40000.0);
        expect($unknownAlloc['value'])->toBe(100000.0);
    });
});

describe('calculateReturns period filtering', function () {
    it('includes ytd_return and one_year_return in results', function () {
        $holdings = collect([
            new Holding([
                'cost_basis' => 10000,
                'current_value' => 11000,
                'purchase_date' => now()->subYears(2),
            ]),
        ]);

        $returns = $this->analyzer->calculateReturns($holdings);

        expect($returns)->toHaveKey('ytd_return');
        expect($returns)->toHaveKey('one_year_return');
    });

    it('returns zero ytd for holdings purchased after year start', function () {
        // Holding purchased yesterday - should not appear in YTD for pre-period holdings
        $holdings = collect([
            new Holding([
                'cost_basis' => 10000,
                'current_value' => 10500,
                'purchase_date' => now()->subDay(),
            ]),
        ]);

        $returns = $this->analyzer->calculateReturns($holdings);

        // YTD return is 0 because no holdings existed before Jan 1
        expect($returns['ytd_return'])->toBe(0.0);
    });
});

describe('calculatePortfolioRisk', function () {
    it('estimates portfolio volatility based on asset mix', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 70000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 30000]),
        ]);

        $riskProfile = new RiskProfile([
            'risk_tolerance' => 'balanced',
            'capacity_for_loss_percent' => 20,
        ]);

        $risk = $this->analyzer->calculatePortfolioRisk($holdings, $riskProfile);

        expect($risk['estimated_volatility'])->toBeGreaterThan(0)
            ->and($risk['risk_level'])->toBeIn(['low', 'medium', 'high']);
    });

    it('returns medium risk for empty holdings', function () {
        $holdings = collect([]);
        $riskProfile = new RiskProfile(['risk_tolerance' => 'balanced']);

        $risk = $this->analyzer->calculatePortfolioRisk($holdings, $riskProfile);

        expect($risk['risk_level'])->toBe('medium');
    });

    it('calculates higher volatility for equity-heavy portfolios', function () {
        $equityHeavy = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 90000]),
            new Holding(['asset_type' => 'cash', 'current_value' => 10000]),
        ]);

        $balanced = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 50000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 50000]),
        ]);

        $riskProfile = new RiskProfile(['risk_tolerance' => 'balanced']);

        $equityRisk = $this->analyzer->calculatePortfolioRisk($equityHeavy, $riskProfile);
        $balancedRisk = $this->analyzer->calculatePortfolioRisk($balanced, $riskProfile);

        expect($equityRisk['estimated_volatility'])->toBeGreaterThan($balancedRisk['estimated_volatility']);
    });
});
