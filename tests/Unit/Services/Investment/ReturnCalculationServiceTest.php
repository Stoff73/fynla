<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\ReturnCalculationService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new ReturnCalculationService;
});

describe('ReturnCalculationService', function () {
    it('returns null for account with no holdings', function () {
        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeNull();
    });

    it('returns null when all holdings have zero cost basis', function () {
        $holding = (object) [
            'cost_basis' => 0,
            'current_value' => 1000,
            'purchase_date' => null,
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeNull();
    });

    it('calculates positive annualised return', function () {
        // Holding: cost 10000, now worth 12000, purchased 2 years ago
        // Total return = (12000 - 10000) / 10000 = 0.20 (20%)
        // Annualised = (1.20 ^ (1/2) - 1) * 100 = ~9.54%
        $holding = (object) [
            'cost_basis' => 10000,
            'current_value' => 12000,
            'purchase_date' => Carbon::now()->subYears(2),
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeFloat();
        expect($result)->toBeGreaterThan(9.0);
        expect($result)->toBeLessThan(10.0);
    });

    it('calculates negative annualised return', function () {
        // Holding: cost 10000, now worth 8000, purchased 1 year ago
        // Total return = (8000 - 10000) / 10000 = -0.20 (-20%)
        $holding = (object) [
            'cost_basis' => 10000,
            'current_value' => 8000,
            'purchase_date' => Carbon::now()->subYear(),
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeFloat();
        expect($result)->toBeLessThan(0);
    });

    it('returns -100 for total loss', function () {
        // Holding: cost 10000, now worth 0
        $holding = (object) [
            'cost_basis' => 10000,
            'current_value' => 0,
            'purchase_date' => Carbon::now()->subYears(2),
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBe(-100.0);
    });

    it('defaults to 3 year holding period when no purchase date', function () {
        // Holding: cost 10000, now worth 15000, no purchase date
        // Total return = 50%, annualised over 3 years
        // (1.50 ^ (1/3) - 1) * 100 ≈ 14.47%
        $holding = (object) [
            'cost_basis' => 10000,
            'current_value' => 15000,
            'purchase_date' => null,
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeFloat();
        expect($result)->toBeGreaterThan(14.0);
        expect($result)->toBeLessThan(15.0);
    });

    it('handles multiple holdings with cost-basis weighting', function () {
        $holding1 = (object) [
            'cost_basis' => 20000,
            'current_value' => 24000,
            'purchase_date' => Carbon::now()->subYears(4),
        ];

        $holding2 = (object) [
            'cost_basis' => 5000,
            'current_value' => 5500,
            'purchase_date' => Carbon::now()->subYear(),
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding1, $holding2]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeFloat();
        expect($result)->toBeGreaterThan(0);
    });

    it('skips holdings with null cost basis', function () {
        $holding1 = (object) [
            'cost_basis' => null,
            'current_value' => 5000,
            'purchase_date' => Carbon::now()->subYear(),
        ];

        $holding2 = (object) [
            'cost_basis' => 10000,
            'current_value' => 11000,
            'purchase_date' => Carbon::now()->subYear(),
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding1, $holding2]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeFloat();
        // Only holding2 contributes: 10% return over 1 year
        expect($result)->toBeGreaterThan(9.0);
        expect($result)->toBeLessThan(11.0);
    });

    it('enforces minimum 3 month holding period', function () {
        // Very recent purchase (1 day ago)
        $holding = (object) [
            'cost_basis' => 10000,
            'current_value' => 10100,
            'purchase_date' => Carbon::now()->subDay(),
        ];

        $account = Mockery::mock(InvestmentAccount::class);
        $account->shouldReceive('getAttribute')->with('holdings')->andReturn(collect([$holding]));

        $result = $this->service->calculateAnnualisedReturn($account);
        expect($result)->toBeFloat();
        // With min 3 months (0.25 years), 1% gain annualised will be much higher
        expect($result)->toBeGreaterThan(0);
    });
});

afterEach(function () {
    Mockery::close();
});
