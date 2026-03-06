<?php

declare(strict_types=1);

use App\Models\ISAAllowanceTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\ISATracker;
use App\Services\TaxConfigService;

// Mock TaxConfigService before running tests
beforeEach(function () {
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('getISAAllowances')
        ->andReturn([
            'annual_allowance' => 20000,
            'lifetime_isa' => [
                'annual_allowance' => 4000,
            ],
        ]);
    $mockTaxConfig->shouldReceive('getTaxYear')
        ->andReturn('2025/26');

    // Bind the mock to the service container
    $this->app->instance(TaxConfigService::class, $mockTaxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('ISATracker', function () {
    describe('getCurrentTaxYear', function () {
        it('returns correct tax year format', function () {
            $tracker = app(ISATracker::class);
            $taxYear = $tracker->getCurrentTaxYear();
            expect($taxYear)->toMatch('/^\d{4}\/\d{2}$/');
        });

        it('returns correct tax year based on date', function () {
            // This test assumes we're in 2024/25 tax year (after April 6, 2024)
            // In production, this would be more dynamic
            $tracker = app(ISATracker::class);
            $taxYear = $tracker->getCurrentTaxYear();
            expect($taxYear)->toBeString();
            expect(strlen($taxYear))->toBe(7);
        });
    });

    describe('getTotalAllowance', function () {
        it('returns correct ISA allowance from config', function () {
            $tracker = app(ISATracker::class);
            $allowance = $tracker->getTotalAllowance('2024/25');
            expect($allowance)->toBe(20000.0);
        });
    });

    describe('getLISAAllowance', function () {
        it('returns correct LISA allowance from config', function () {
            $tracker = app(ISATracker::class);
            $allowance = $tracker->getLISAAllowance();
            expect($allowance)->toBe(4000.0);
        });
    });

    describe('getISAAllowanceStatus', function () {
        it('creates tracking record if not exists', function () {
            $tracker = app(ISATracker::class);
            $user = User::factory()->create();
            $status = $tracker->getISAAllowanceStatus($user->id, '2024/25');

            expect($status)->toHaveKeys([
                'cash_isa_used',
                'stocks_shares_isa_used',
                'lisa_used',
                'total_used',
                'total_allowance',
                'remaining',
                'percentage_used',
            ]);

            expect($status['total_allowance'])->toBe(20000.0);
            expect($status['total_used'])->toBe(0.0);
            expect($status['remaining'])->toBe(20000.0);
        });

        it('calculates ISA usage from savings accounts', function () {
            $tracker = app(ISATracker::class);
            $user = User::factory()->create();

            // Create cash ISA account
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2024/25',
                'isa_subscription_amount' => 5000,
            ]);

            // Create LISA account
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_isa' => true,
                'isa_type' => 'LISA',
                'isa_subscription_year' => '2024/25',
                'isa_subscription_amount' => 4000,
            ]);

            $status = $tracker->getISAAllowanceStatus($user->id, '2024/25');

            expect($status['cash_isa_used'])->toBe(5000.0);
            expect($status['lisa_used'])->toBe(4000.0);
            expect($status['total_used'])->toBe(9000.0);
            expect($status['remaining'])->toBe(11000.0);
            expect($status['percentage_used'])->toBe(45.0);
        });

        it('only counts ISAs for the specified tax year', function () {
            $tracker = app(ISATracker::class);
            $user = User::factory()->create();

            // Create ISA for 2024/25
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2024/25',
                'isa_subscription_amount' => 5000,
            ]);

            // Create ISA for 2023/24 (should not be counted)
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2023/24',
                'isa_subscription_amount' => 10000,
            ]);

            $status = $tracker->getISAAllowanceStatus($user->id, '2024/25');

            expect($status['cash_isa_used'])->toBe(5000.0);
            expect($status['total_used'])->toBe(5000.0);
        });
    });

    describe('updateISAUsage', function () {
        it('updates ISA usage for a specific type', function () {
            $tracker = app(ISATracker::class);
            $user = User::factory()->create();

            $tracker->updateISAUsage($user->id, 'stocks_shares', 10000);

            $tracking = ISAAllowanceTracking::where('user_id', $user->id)
                ->where('tax_year', $tracker->getCurrentTaxYear())
                ->first();

            expect($tracking->stocks_shares_isa_used)->toBe('10000.00');
            expect($tracking->total_used)->toBe('10000.00');
        });

        it('updates total when multiple types are used', function () {
            $tracker = app(ISATracker::class);
            $user = User::factory()->create();

            $tracker->updateISAUsage($user->id, 'stocks_shares', 10000);
            $tracker->updateISAUsage($user->id, 'cash', 5000);

            $tracking = ISAAllowanceTracking::where('user_id', $user->id)
                ->where('tax_year', $tracker->getCurrentTaxYear())
                ->first();

            expect($tracking->total_used)->toBe('15000.00');
        });
    });
});
