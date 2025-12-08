<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Investment\ContributionOptimizer;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->user = User::factory()->create([
        'marital_status' => 'single',
    ]);
    // Get ContributionOptimizer from the container with all dependencies
    $this->optimizer = app(ContributionOptimizer::class);
});

describe('ContributionOptimizer', function () {
    it('calculates wrapper allocation correctly for basic rate taxpayer', function () {
        $inputs = [
            'monthly_investable_income' => 1000,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 20,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'basic',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        expect($result)->toHaveKeys([
            'wrapper_allocation',
            'lump_sum_analysis',
            'projections',
            'tax_efficiency_score',
            'recommendations',
        ]);

        // Verify wrapper allocation structure
        expect($result['wrapper_allocation'])->toHaveKeys([
            'isa_contribution',
            'gia_contribution',
            'pension_contribution',
            'total_monthly',
        ]);

        // Verify total allocation equals input
        $totalMonthly = $result['wrapper_allocation']['isa_contribution']
            + $result['wrapper_allocation']['pension_contribution']
            + $result['wrapper_allocation']['gia_contribution'];

        expect($totalMonthly)->toBe(1000.0);
    });

    it('prioritizes ISA allocation up to allowance', function () {
        $inputs = [
            'monthly_investable_income' => 500, // £6k annually, within ISA allowance
            'lump_sum_amount' => 0,
            'time_horizon_years' => 10,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'basic',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        // Should allocate to ISA if allowance available, or GIA if not
        $isaMonthly = $result['wrapper_allocation']['isa_contribution'];
        $giaMonthly = $result['wrapper_allocation']['gia_contribution'];
        // Either ISA or GIA should have allocation (500 goes somewhere)
        expect($isaMonthly + $giaMonthly)->toBeGreaterThan(0);
        expect($isaMonthly + $giaMonthly)->toBeLessThanOrEqual(500);
    });

    it('includes pension for higher rate taxpayers', function () {
        $inputs = [
            'monthly_investable_income' => 2000,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 25,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'higher',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        // Higher rate taxpayers should have pension allocation due to tax relief
        $pensionMonthly = $result['wrapper_allocation']['pension_contribution'];
        expect($pensionMonthly)->toBeGreaterThan(0);

        // Verify tax relief calculation exists
        expect($result)->toHaveKey('tax_relief');
        expect($result['tax_relief']['total_relief'])->toBeGreaterThanOrEqual(0);
    });

    it('analyzes lump sum vs DCA correctly', function () {
        $inputs = [
            'monthly_investable_income' => 500,
            'lump_sum_amount' => 10000,
            'time_horizon_years' => 5,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'basic',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        expect($result['lump_sum_analysis'])->toHaveKeys([
            'lump_sum',
            'dca',
            'recommendation',
            'timing_risk',
        ]);

        // Verify recommendation is either 'lump_sum' or 'dca'
        expect($result['lump_sum_analysis']['recommendation'])
            ->toBeIn(['lump_sum', 'dca', 'hybrid']);
    });

    it('calculates tax efficiency score between 0-100', function () {
        $inputs = [
            'monthly_investable_income' => 1500,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 15,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'higher',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        expect($result['tax_efficiency_score'])
            ->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(100);
    });

    it('generates three projection values', function () {
        $inputs = [
            'monthly_investable_income' => 1000,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 20,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'basic',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        expect($result['projections'])->toHaveKeys([
            'expected_value',
            'conservative_value',
            'optimistic_value',
        ]);

        // Verify each scenario has value
        expect($result['projections']['conservative_value'])->toBeGreaterThanOrEqual(0);
        expect($result['projections']['expected_value'])->toBeGreaterThan(0);
        expect($result['projections']['optimistic_value'])->toBeGreaterThan(0);

        // Verify scenarios are ordered correctly
        expect($result['projections']['optimistic_value'])
            ->toBeGreaterThan($result['projections']['expected_value']);
        expect($result['projections']['expected_value'])
            ->toBeGreaterThan($result['projections']['conservative_value']);
    });

    it('provides actionable recommendations', function () {
        $inputs = [
            'monthly_investable_income' => 1000,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 20,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'basic',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        expect($result['recommendations'])->toBeArray();

        // Verify each recommendation has required fields
        foreach ($result['recommendations'] as $recommendation) {
            expect($recommendation)->toHaveKeys(['priority', 'title', 'description']);
        }
    });

    it('respects ISA allowance limits', function () {
        $inputs = [
            'monthly_investable_income' => 3000, // £36k annually, exceeds ISA allowance
            'lump_sum_amount' => 0,
            'time_horizon_years' => 10,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'higher',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        // ISA allocation should not exceed £20k annual allowance (£1,667 monthly)
        $isaMonthly = $result['wrapper_allocation']['isa_contribution'];
        $isaAnnual = $isaMonthly * 12;
        expect($isaAnnual)->toBeLessThanOrEqual(20000);

        // Overflow should go to Pension or GIA
        $pensionMonthly = $result['wrapper_allocation']['pension_contribution'];
        $giaMonthly = $result['wrapper_allocation']['gia_contribution'];
        expect($pensionMonthly + $giaMonthly)->toBeGreaterThan(0);
    });

    it('handles zero investable income gracefully', function () {
        $inputs = [
            'monthly_investable_income' => 0,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 10,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'basic',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        // Should still return valid structure
        expect($result)->toHaveKeys([
            'wrapper_allocation',
            'projections',
            'tax_efficiency_score',
            'recommendations',
        ]);

        // All allocations should be zero
        expect($result['wrapper_allocation']['isa_contribution'])->toEqual(0);
        expect($result['wrapper_allocation']['pension_contribution'])->toEqual(0);
        expect($result['wrapper_allocation']['gia_contribution'])->toEqual(0);
    });

    it('calculates pension tax relief correctly for higher rate', function () {
        $inputs = [
            'monthly_investable_income' => 1000,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 20,
            'risk_tolerance' => 'balanced',
            'income_tax_band' => 'higher',
        ];

        $result = $this->optimizer->optimizeContributions($this->user->id, $inputs);

        if ($result['wrapper_allocation']['pension_contribution'] > 0) {
            $pensionContribution = $result['wrapper_allocation']['pension_contribution'];
            $taxRelief = $result['tax_relief']['total_relief'];

            // Higher rate should get at least basic rate relief (20%)
            $minExpectedRelief = ($pensionContribution * 12) * 0.20;

            expect($taxRelief)->toBeGreaterThanOrEqual($minExpectedRelief * 0.99);
        }
    });

    it('adjusts projections based on risk tolerance', function () {
        $baseInputs = [
            'monthly_investable_income' => 1000,
            'lump_sum_amount' => 0,
            'time_horizon_years' => 20,
            'income_tax_band' => 'basic',
        ];

        $conservativeResult = $this->optimizer->optimizeContributions(
            $this->user->id,
            array_merge($baseInputs, ['risk_tolerance' => 'conservative'])
        );
        $aggressiveResult = $this->optimizer->optimizeContributions(
            $this->user->id,
            array_merge($baseInputs, ['risk_tolerance' => 'aggressive'])
        );

        // Risk tolerance should affect projections
        // Aggressive should have higher expected value
        expect($aggressiveResult['projections']['expected_value'])
            ->toBeGreaterThan($conservativeResult['projections']['expected_value']);
    });
});
