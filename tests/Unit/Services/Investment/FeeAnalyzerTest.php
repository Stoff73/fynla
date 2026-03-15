<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;

beforeEach(function () {
    $this->riskPreferenceService = Mockery::mock(RiskPreferenceService::class);
    $this->riskPreferenceService->shouldReceive('getReturnParameters')
        ->with('medium')
        ->andReturn([
            'expected_return_min' => 3.5,
            'expected_return_max' => 6.5,
            'expected_return_typical' => 5.0,
            'volatility' => 10.0,
        ]);

    $this->taxConfigService = Mockery::mock(TaxConfigService::class);
    $this->taxConfigService->shouldReceive('get')
        ->with('investment.fee_benchmarks', Mockery::any())
        ->andReturn([
            'low_cost_ocf' => 0.0015,
            'high_cost_ocf' => 0.0075,
            'platform_fee_typical' => 0.0025,
        ]);

    $this->feeAnalyzer = new FeeAnalyzer($this->riskPreferenceService, $this->taxConfigService);
});

afterEach(function () {
    Mockery::close();
});

describe('calculateTotalFees', function () {
    it('calculates total fees across accounts and holdings', function () {
        $accounts = collect([
            new InvestmentAccount([
                'current_value' => 100000,
                'platform_fee_percent' => 0.25, // 0.25%
            ]),
            new InvestmentAccount([
                'current_value' => 50000,
                'platform_fee_percent' => 0.35, // 0.35%
            ]),
        ]);

        $holdings = collect([
            new Holding([
                'current_value' => 60000,
                'ocf_percent' => 0.50, // 0.50% OCF
            ]),
            new Holding([
                'current_value' => 40000,
                'ocf_percent' => 0.15, // 0.15% OCF
            ]),
        ]);

        $result = $this->feeAnalyzer->calculateTotalFees($accounts, $holdings);

        // Platform fees: (100000 * 0.0025) + (50000 * 0.0035) = 250 + 175 = 425
        // Fund fees: (60000 * 0.005) + (40000 * 0.0015) = 300 + 60 = 360
        // Transaction costs: estimateTransactionCosts(150000, 0.10) = 150000 * 0.10 * 0.001 = 15
        // Total: 425 + 360 + 15 = 800

        expect($result['portfolio_value'])->toBe(150000.0)
            ->and($result['total_annual_fees'])->toBe(800.0)
            ->and($result['fee_breakdown'])->toHaveCount(3)
            ->and($result['fee_drag_percent'])->toBeGreaterThan(0);
    });

    it('returns zero fees for empty portfolio', function () {
        $accounts = collect([]);
        $holdings = collect([]);

        $result = $this->feeAnalyzer->calculateTotalFees($accounts, $holdings);

        expect($result['total_annual_fees'])->toBe(0.0)
            ->and($result['fee_drag_percent'])->toBe(0.0);
    });

    it('includes 10-year and 20-year fee projections', function () {
        $accounts = collect([
            new InvestmentAccount([
                'current_value' => 100000,
                'platform_fee_percent' => 0.30,
            ]),
        ]);

        $holdings = collect([
            new Holding([
                'current_value' => 100000,
                'ocf_percent' => 0.20,
            ]),
        ]);

        $result = $this->feeAnalyzer->calculateTotalFees($accounts, $holdings);

        expect($result)->toHaveKey('fees_over_10_years')
            ->and($result)->toHaveKey('fees_over_20_years')
            ->and($result['fees_over_20_years'])->toBe($result['fees_over_10_years'] * 2);
    });
});

describe('compareToLowCostAlternatives', function () {
    it('compares current OCF to low-cost alternatives', function () {
        $holdings = collect([
            new Holding([
                'current_value' => 50000,
                'ocf_percent' => 0.75, // 0.75% - expensive
            ]),
            new Holding([
                'current_value' => 50000,
                'ocf_percent' => 0.50, // 0.50% - moderate
            ]),
        ]);

        $result = $this->feeAnalyzer->compareToLowCostAlternatives($holdings);

        // Weighted average OCF: ((50000 * 0.75) + (50000 * 0.50)) / 100000 = 0.625%
        expect($result['current_average_ocf'])->toBe(0.625)
            ->and($result['low_cost_average_ocf'])->toBe(0.15)
            ->and($result['annual_saving'])->toBeGreaterThan(0)
            ->and($result['recommendation'])->toContain('Consider switching');
    });

    it('recommends keeping current funds when fees are competitive', function () {
        $holdings = collect([
            new Holding([
                'current_value' => 100000,
                'ocf_percent' => 0.12, // Already low-cost
            ]),
        ]);

        $result = $this->feeAnalyzer->compareToLowCostAlternatives($holdings);

        expect($result['annual_saving'])->toBeLessThan(100)
            ->and($result['recommendation'])->toBe('Fees are competitive');
    });

    it('calculates ten-year savings projection', function () {
        $holdings = collect([
            new Holding([
                'current_value' => 100000,
                'ocf_percent' => 1.00, // 1% - very expensive
            ]),
        ]);

        $result = $this->feeAnalyzer->compareToLowCostAlternatives($holdings);

        // Annual saving: (100000 * 0.01) - (100000 * 0.0015) = 1000 - 150 = 850
        expect($result['annual_saving'])->toBe(850.0)
            ->and($result['ten_year_saving'])->toBe(8500.0);
    });

    it('returns zero savings for empty holdings', function () {
        $holdings = collect([]);

        $result = $this->feeAnalyzer->compareToLowCostAlternatives($holdings);

        expect($result['annual_saving'])->toBe(0.0);
    });
});

describe('identifyHighFeeHoldings', function () {
    it('identifies holdings with high OCF', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Expensive Fund A',
                'current_value' => 50000,
                'ocf_percent' => 1.20, // High fee
            ]),
            new Holding([
                'security_name' => 'Index Fund B',
                'current_value' => 50000,
                'ocf_percent' => 0.15, // Low fee
            ]),
            new Holding([
                'security_name' => 'Expensive Fund C',
                'current_value' => 30000,
                'ocf_percent' => 0.85, // High fee
            ]),
        ]);

        $result = $this->feeAnalyzer->identifyHighFeeHoldings($holdings);

        expect($result['high_fee_count'])->toBe(2)
            ->and($result['holdings'])->toHaveCount(2)
            ->and($result['total_value_in_high_fee_funds'])->toBe(80000.0)
            ->and($result['holdings'][0]['security_name'])->toBe('Expensive Fund A');
    });

    it('returns empty result when all fees are reasonable', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Low Cost Fund',
                'current_value' => 100000,
                'ocf_percent' => 0.10,
            ]),
        ]);

        $result = $this->feeAnalyzer->identifyHighFeeHoldings($holdings);

        expect($result['high_fee_count'])->toBe(0)
            ->and($result['holdings'])->toBeEmpty();
    });

    it('calculates annual cost for each high-fee holding', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Active Fund',
                'current_value' => 100000,
                'ocf_percent' => 1.50,
            ]),
        ]);

        $result = $this->feeAnalyzer->identifyHighFeeHoldings($holdings);

        // Annual cost: 100000 * 0.015 = 1500
        expect($result['holdings'][0]['annual_cost'])->toBe(1500.0)
            ->and($result['holdings'][0]['recommendation'])->toBe('Consider lower-cost alternative');
    });
});

describe('calculateSimpleFeeDrag', function () {
    it('calculates fee drag as percentage of portfolio', function () {
        $drag = $this->feeAnalyzer->calculateSimpleFeeDrag(1000, 100000);

        // 1000 / 100000 = 1%
        expect($drag)->toBe(1.0);
    });

    it('returns zero for zero portfolio value', function () {
        $drag = $this->feeAnalyzer->calculateSimpleFeeDrag(500, 0);

        expect($drag)->toBe(0.0);
    });

    it('handles small fee amounts correctly', function () {
        $drag = $this->feeAnalyzer->calculateSimpleFeeDrag(250, 100000);

        // 250 / 100000 = 0.25%
        expect($drag)->toBe(0.25);
    });
});
