<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\ContributionEstimatorService;
use App\Services\TaxConfigService;

beforeEach(function () {
    $this->taxConfig = Mockery::mock(TaxConfigService::class);

    $this->taxConfig->shouldReceive('getISAAllowances')->andReturn([
        'annual_allowance' => 20000,
    ]);

    $this->service = new ContributionEstimatorService($this->taxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('ContributionEstimatorService', function () {
    it('returns user override when provided', function () {
        $account = new InvestmentAccount(['account_type' => 'isa']);

        $result = $this->service->estimateMonthlyContribution($account, 500.0);
        expect($result)->toBe(500.0);
    });

    it('returns zero for negative user override on SIPP', function () {
        $account = new InvestmentAccount(['account_type' => 'sipp']);

        // Negative override is ignored, falls through to SIPP default (0.0)
        $result = $this->service->estimateMonthlyContribution($account, -100.0);
        expect($result)->toBe(0.0);
    });

    it('estimates ISA contribution from allowance when no subscription data', function () {
        $account = new InvestmentAccount([
            'account_type' => 'isa',
            'isa_subscription_current_year' => 0,
        ]);

        $result = $this->service->estimateMonthlyContribution($account);
        // Default: 20000 / 12 = 1666.67
        expect($result)->toBeGreaterThan(1600);
        expect($result)->toBeLessThan(1700);
    });

    it('estimates GIA contribution from account value', function () {
        $account = new InvestmentAccount([
            'account_type' => 'gia',
            'current_value' => 100000,
        ]);

        $result = $this->service->estimateMonthlyContribution($account);
        // GIA: 100000 * 0.05 / 12 = 416.67
        expect($result)->toBeGreaterThan(416);
        expect($result)->toBeLessThan(417);
    });

    it('returns zero for SIPP account type', function () {
        $account = new InvestmentAccount(['account_type' => 'sipp']);

        $result = $this->service->estimateMonthlyContribution($account);
        expect($result)->toBe(0.0);
    });

    it('calculates portfolio contribution across multiple accounts', function () {
        $isaAccount = new InvestmentAccount([
            'account_type' => 'isa',
            'isa_subscription_current_year' => 0,
        ]);
        $isaAccount->id = 1;

        $giaAccount = new InvestmentAccount([
            'account_type' => 'gia',
            'current_value' => 50000,
        ]);
        $giaAccount->id = 2;

        $accounts = collect([$isaAccount, $giaAccount]);

        $result = $this->service->estimatePortfolioContribution($accounts);
        // ISA: ~1666.67 + GIA: 50000 * 0.05 / 12 = ~208.33 = ~1875
        expect($result)->toBeGreaterThan(1800);
        expect($result)->toBeLessThan(1900);
    });

    it('applies per-account overrides in portfolio calculation', function () {
        $account1 = new InvestmentAccount(['account_type' => 'isa']);
        $account1->id = 1;
        $account2 = new InvestmentAccount([
            'account_type' => 'gia',
            'current_value' => 100000,
        ]);
        $account2->id = 2;

        $accounts = collect([$account1, $account2]);
        $overrides = [1 => 300.0]; // Override account 1 only

        $result = $this->service->estimatePortfolioContribution($accounts, $overrides);
        // Account 1: 300 (override) + Account 2: 100000 * 0.05 / 12 = ~416.67
        expect($result)->toBeGreaterThan(716);
        expect($result)->toBeLessThan(717);
    });

    it('handles ISA subscription data when available', function () {
        $account = new InvestmentAccount([
            'account_type' => 'isa',
            'isa_subscription_current_year' => 6000,
        ]);

        $result = $this->service->estimateMonthlyContribution($account);
        // 6000 / months elapsed in tax year (varies), should be positive
        expect($result)->toBeGreaterThan(0);
    });
});
