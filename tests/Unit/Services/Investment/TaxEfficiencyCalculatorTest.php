<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\DividendTaxCalculator;
use App\Services\Investment\Tax\CGTHarvestingCalculator;
use App\Services\Investment\TaxEfficiencyCalculator;
use App\Services\TaxConfigService;

beforeEach(function () {
    $this->taxConfig = Mockery::mock(TaxConfigService::class);

    // Set up mock expectations for TaxConfigService methods
    $this->taxConfig->shouldReceive('getDividendTax')->andReturn([
        'allowance' => 500,
        'basic_rate' => 0.0875,
        'higher_rate' => 0.3375,
        'additional_rate' => 0.3935,
    ]);

    $this->taxConfig->shouldReceive('getCapitalGainsTax')->andReturn([
        'annual_exempt_amount' => 3000,
        'basic_rate' => 0.10,
        'higher_rate' => 0.20,
    ]);

    $this->taxConfig->shouldReceive('getIncomeTax')->andReturn([
        'bands' => [
            ['name' => 'Personal Allowance', 'threshold' => 0, 'rate' => 0],
            ['name' => 'Basic Rate', 'threshold' => 12570, 'rate' => 0.20],
            ['name' => 'Higher Rate', 'threshold' => 50270, 'rate' => 0.40],
            ['name' => 'Additional Rate', 'threshold' => 125140, 'rate' => 0.45],
        ],
        'personal_allowance' => 12570,
        'basic_rate_limit' => 37700,
        'higher_rate_threshold' => 50270,
        'additional_rate_threshold' => 125140,
    ]);

    $this->cgtHarvestingCalculator = Mockery::mock(CGTHarvestingCalculator::class);
    $this->dividendTaxCalculator = new DividendTaxCalculator($this->taxConfig);
    $this->taxCalculator = new TaxEfficiencyCalculator($this->taxConfig, $this->dividendTaxCalculator, $this->cgtHarvestingCalculator);
});

afterEach(function () {
    Mockery::close();
});

describe('calculateUnrealizedGains', function () {
    it('calculates unrealized gains for holdings', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Stock A',
                'cost_basis' => 10000,
                'current_value' => 15000,
            ]),
            new Holding([
                'security_name' => 'Stock B',
                'cost_basis' => 5000,
                'current_value' => 7000,
            ]),
        ]);

        $result = $this->taxCalculator->calculateUnrealizedGains($holdings);

        expect($result['total_unrealized_gains'])->toBe(7000.0)
            ->and($result['count'])->toBe(2)
            ->and($result['holdings_with_gains'][0]['security_name'])->toBe('Stock A')
            ->and($result['holdings_with_gains'][0]['unrealized_gain'])->toBe(5000.0)
            ->and($result['holdings_with_gains'][0]['gain_percent'])->toBe(50.0);
    });

    it('filters out holdings with losses', function () {
        $holdings = collect([
            new Holding([
                'security_name' => 'Winner',
                'cost_basis' => 10000,
                'current_value' => 12000,
            ]),
            new Holding([
                'security_name' => 'Loser',
                'cost_basis' => 5000,
                'current_value' => 4000,
            ]),
        ]);

        $result = $this->taxCalculator->calculateUnrealizedGains($holdings);

        expect($result['count'])->toBe(1)
            ->and($result['holdings_with_gains'][0]['security_name'])->toBe('Winner');
    });

    it('returns zero for empty holdings', function () {
        $holdings = collect([]);

        $result = $this->taxCalculator->calculateUnrealizedGains($holdings);

        expect($result['total_unrealized_gains'])->toBe(0.0)
            ->and($result['count'])->toBe(0);
    });
});

describe('calculateDividendTax', function () {
    it('applies dividend allowance before tax', function () {
        $dividendIncome = 2000;
        $totalIncome = 30000;

        $tax = $this->taxCalculator->calculateDividendTax($dividendIncome, $totalIncome);

        // Taxable dividends: 2000 - 500 = 1500
        // Basic rate taxpayer: 1500 * 0.0875 = 131.25
        expect($tax)->toBe(131.25);
    });

    it('returns zero tax when dividends are below allowance', function () {
        $dividendIncome = 300;
        $totalIncome = 30000;

        $tax = $this->taxCalculator->calculateDividendTax($dividendIncome, $totalIncome);

        expect($tax)->toBe(0.0);
    });

    it('applies higher rate for high earners', function () {
        $dividendIncome = 10000;
        $totalIncome = 100000; // Higher rate taxpayer

        $tax = $this->taxCalculator->calculateDividendTax($dividendIncome, $totalIncome);

        // Taxable: 10000 - 500 = 9500
        // Higher rate: 9500 * 0.3375 = 3206.25
        expect($tax)->toBe(3206.25);
    });

    it('applies additional rate for top earners', function () {
        $dividendIncome = 50000;
        $totalIncome = 200000; // Additional rate taxpayer

        $tax = $this->taxCalculator->calculateDividendTax($dividendIncome, $totalIncome);

        // Taxable: 50000 - 500 = 49500
        // Additional rate: 49500 * 0.3935 = 19478.25
        expect($tax)->toBe(19478.25);
    });
});

describe('calculateCGTLiability', function () {
    it('applies annual exemption before tax', function () {
        $realizedGains = 10000;
        $totalIncome = 30000;

        $cgt = $this->taxCalculator->calculateCGTLiability($realizedGains, $totalIncome);

        // Taxable gains: 10000 - 3000 = 7000
        // Basic rate: 7000 * 0.10 = 700
        expect($cgt)->toBe(700.0);
    });

    it('returns zero tax when gains are below exemption', function () {
        $realizedGains = 2000;
        $totalIncome = 30000;

        $cgt = $this->taxCalculator->calculateCGTLiability($realizedGains, $totalIncome);

        expect($cgt)->toBe(0.0);
    });

    it('applies higher rate for high earners', function () {
        $realizedGains = 20000;
        $totalIncome = 100000; // Higher rate taxpayer

        $cgt = $this->taxCalculator->calculateCGTLiability($realizedGains, $totalIncome);

        // Taxable: 20000 - 3000 = 17000
        // Higher rate: 17000 * 0.20 = 3400
        expect($cgt)->toBe(3400.0);
    });

    it('handles zero income correctly', function () {
        $realizedGains = 10000;
        $totalIncome = 0;

        $cgt = $this->taxCalculator->calculateCGTLiability($realizedGains, $totalIncome);

        // Should apply basic rate
        expect($cgt)->toBe(700.0);
    });
});

describe('identifyHarvestingOpportunities', function () {
    it('delegates to CGTHarvestingCalculator and adapts response', function () {
        $this->cgtHarvestingCalculator->shouldReceive('calculateHarvestingOpportunities')
            ->once()
            ->with(1, [])
            ->andReturn([
                'success' => true,
                'opportunities' => [
                    [
                        'security_name' => 'Loss Stock A',
                        'cost_basis' => 10000,
                        'current_value' => 8500,
                        'loss_amount' => 1500,
                        'loss_percent' => 15.0,
                        'rationale' => 'Realize £1,500 loss (15.0%)',
                    ],
                ],
                'total_harvestable_losses' => 1500,
                'potential_tax_saving' => 300,
            ]);

        $result = $this->taxCalculator->identifyHarvestingOpportunities(1);

        expect($result['opportunities_count'])->toBe(1)
            ->and($result['holdings'][0]['security_name'])->toBe('Loss Stock A')
            ->and($result['holdings'][0]['unrealized_loss'])->toBe(-1500.0)
            ->and($result['total_harvestable_losses'])->toBe(1500.0)
            ->and($result['potential_tax_saving'])->toBe(300.0);
    });

    it('returns empty result when no opportunities', function () {
        $this->cgtHarvestingCalculator->shouldReceive('calculateHarvestingOpportunities')
            ->once()
            ->with(1, [])
            ->andReturn([
                'success' => true,
                'opportunities' => [],
                'total_harvestable_losses' => 0,
                'potential_tax_saving' => 0,
            ]);

        $result = $this->taxCalculator->identifyHarvestingOpportunities(1);

        expect($result['opportunities_count'])->toBe(0)
            ->and($result['holdings'])->toBeEmpty();
    });

    it('passes options through to CGTHarvestingCalculator', function () {
        $options = ['expected_gains' => 5000, 'tax_rate' => 0.20];

        $this->cgtHarvestingCalculator->shouldReceive('calculateHarvestingOpportunities')
            ->once()
            ->with(1, $options)
            ->andReturn([
                'success' => true,
                'opportunities' => [],
                'total_harvestable_losses' => 0,
                'potential_tax_saving' => 0,
            ]);

        $result = $this->taxCalculator->identifyHarvestingOpportunities(1, $options);

        expect($result['opportunities_count'])->toBe(0);
    });
});

describe('calculateTaxShelterRatio', function () {
    it('rewards high ISA usage', function () {
        $accounts = collect([
            new InvestmentAccount([
                'account_type' => 'isa',
                'current_value' => 60000,
            ]),
            new InvestmentAccount([
                'account_type' => 'gia',
                'current_value' => 40000,
            ]),
        ]);

        $holdings = collect([]);

        $score = $this->taxCalculator->calculateTaxShelterRatio($accounts, $holdings);

        // 60% in ISA = score of 60 (directly reflects tax-sheltered percentage)
        expect($score)->toBe(60);
    });

    it('penalizes low ISA usage', function () {
        $accounts = collect([
            new InvestmentAccount([
                'account_type' => 'gia',
                'current_value' => 80000,
            ]),
            new InvestmentAccount([
                'account_type' => 'isa',
                'current_value' => 20000,
            ]),
        ]);

        $holdings = collect([]);

        $score = $this->taxCalculator->calculateTaxShelterRatio($accounts, $holdings);

        // Only 20% in ISA = poor usage, should be penalized
        expect($score)->toBeLessThan(90);
    });

    it('scores 100 when all assets are in ISA regardless of holdings gains', function () {
        $accounts = collect([
            new InvestmentAccount([
                'account_type' => 'isa',
                'current_value' => 50000,
            ]),
        ]);

        $holdings = collect([
            new Holding(['cost_basis' => 10000, 'current_value' => 20000]), // 100% gain
            new Holding(['cost_basis' => 10000, 'current_value' => 18000]), // 80% gain
            new Holding(['cost_basis' => 10000, 'current_value' => 16000]), // 60% gain
            new Holding(['cost_basis' => 10000, 'current_value' => 17000]), // 70% gain
        ]);

        $score = $this->taxCalculator->calculateTaxShelterRatio($accounts, $holdings);

        // Score is based on tax-sheltered percentage of accounts only
        // 100% in ISA = score of 100
        expect($score)->toBe(100);
    });

    it('returns score capped between 0 and 100', function () {
        $accounts = collect([
            new InvestmentAccount([
                'account_type' => 'isa',
                'current_value' => 100000,
            ]),
        ]);

        $holdings = collect([]);

        $score = $this->taxCalculator->calculateTaxShelterRatio($accounts, $holdings);

        expect($score)->toBeGreaterThanOrEqual(0)
            ->and($score)->toBeLessThanOrEqual(100);
    });
});
