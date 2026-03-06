<?php

declare(strict_types=1);

use App\Services\Investment\DividendTaxCalculator;
use App\Services\TaxConfigService;

beforeEach(function () {
    $this->taxConfig = Mockery::mock(TaxConfigService::class);

    $this->taxConfig->shouldReceive('getDividendTax')->andReturn([
        'allowance' => 500,
        'basic_rate' => 0.0875,
        'higher_rate' => 0.3375,
        'additional_rate' => 0.3938,
    ]);

    $this->taxConfig->shouldReceive('getIncomeTax')->andReturn([
        'personal_allowance' => 12570,
        'basic_rate_limit' => 37700,
        'higher_rate_threshold' => 50270,
        'additional_rate_threshold' => 125140,
    ]);

    $this->calculator = new DividendTaxCalculator($this->taxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('DividendTaxCalculator', function () {
    it('returns zero for zero dividend income', function () {
        $tax = $this->calculator->calculate(0, 30000);
        expect($tax)->toBe(0.0);
    });

    it('returns zero for negative dividend income', function () {
        $tax = $this->calculator->calculate(-500, 30000);
        expect($tax)->toBe(0.0);
    });

    it('returns zero when dividends are within allowance', function () {
        $tax = $this->calculator->calculate(400, 30000);
        expect($tax)->toBe(0.0);
    });

    it('returns zero when dividends exactly equal allowance', function () {
        $tax = $this->calculator->calculate(500, 30000);
        expect($tax)->toBe(0.0);
    });

    it('calculates basic rate tax for basic rate taxpayer', function () {
        // £30k salary, £2000 dividends
        // Taxable dividends = 2000 - 500 = 1500
        // All in basic band (salary 30000 < 50270)
        // Tax = 1500 * 0.0875 = 131.25
        $tax = $this->calculator->calculate(2000, 30000);
        expect($tax)->toBe(131.25);
    });

    it('splits dividends across basic and higher rate bands', function () {
        // £49,000 salary, £5000 dividends
        // Taxable dividends = 5000 - 500 = 4500
        // Basic band ceiling = 12570 + 37700 = 50270
        // Space in basic band = 50270 - 49000 = 1270
        // Dividends in basic band = 1270, tax = 1270 * 0.0875 = 111.125
        // Dividends in higher band = 4500 - 1270 = 3230, tax = 3230 * 0.3375 = 1090.125
        // Total = 111.125 + 1090.125 = 1201.25
        $tax = $this->calculator->calculate(5000, 49000);
        expect($tax)->toBe(1201.25);
    });

    it('calculates higher rate tax for higher rate taxpayer', function () {
        // £80k salary, £3000 dividends
        // Taxable dividends = 3000 - 500 = 2500
        // Salary already in higher band (80000 > 50270)
        // All taxable dividends in higher band
        // Tax = 2500 * 0.3375 = 843.75
        $tax = $this->calculator->calculate(3000, 80000);
        expect($tax)->toBe(843.75);
    });

    it('splits dividends across higher and additional rate bands', function () {
        // £120k salary, £10000 dividends
        // Total income = 130k, PA tapered
        // Taper: (130000 - 100000) / 2 = 15000, PA = max(0, 12570 - 15000) = 0
        // Basic band ceiling = 0 + 37700 = 37700
        // Higher band ceiling = 125140
        // Salary 120000 > basic band ceiling, so no basic rate dividends
        // Space in higher band = 125140 - 120000 = 5140
        // Taxable dividends = 10000 - 500 = 9500
        // Dividends in higher band = 5140, tax = 5140 * 0.3375 = 1734.75
        // Dividends in additional band = 9500 - 5140 = 4360, tax = 4360 * 0.3938 = 1716.97 (rounded)
        $tax = $this->calculator->calculate(10000, 120000);
        expect($tax)->toBe(3451.72);
    });

    it('applies personal allowance taper for income over 100k', function () {
        // £110k salary, £1000 dividends
        // Total income = 111k
        // PA taper: (111000 - 100000) / 2 = 5500, PA = 12570 - 5500 = 7070
        // Basic band ceiling = 7070 + 37700 = 44770
        // Salary 110000 > 44770, so all dividends in higher rate
        // Taxable dividends = 1000 - 500 = 500
        // Tax = 500 * 0.3375 = 168.75
        $tax = $this->calculator->calculate(1000, 110000);
        expect($tax)->toBe(168.75);
    });

    it('calculates additional rate for very high earners', function () {
        // £200k salary, £5000 dividends
        // Total income = 205k
        // PA fully tapered to 0
        // Basic band ceiling = 0 + 37700 = 37700
        // Higher band ceiling = 125140
        // Salary 200000 > 125140, all dividends in additional band
        // Taxable dividends = 5000 - 500 = 4500
        // Tax = 4500 * 0.3938 = 1772.10
        $tax = $this->calculator->calculate(5000, 200000);
        expect($tax)->toBe(1772.1);
    });

    it('handles zero non-dividend income', function () {
        // £0 salary, £20000 dividends (e.g., retired person living on dividends)
        // Taxable dividends = 20000 - 500 = 19500
        // PA = 12570, basic band ceiling = 12570 + 37700 = 50270
        // Space in basic band = 50270 - 0 = 50270
        // All 19500 in basic band
        // Tax = 19500 * 0.0875 = 1706.25
        $tax = $this->calculator->calculate(20000, 0);
        expect($tax)->toBe(1706.25);
    });
});
