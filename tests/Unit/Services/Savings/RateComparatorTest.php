<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SavingsMarketRate;
use App\Models\User;
use App\Services\Savings\RateComparator;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // The comparator reads benchmarks for the ACTIVE tax year; the seeder only
    // knows 2025/26 (fyn-wiring F20), so seed the active year here.
    $taxYear = app(TaxConfigService::class)->getTaxYear();
    foreach (['easy_access' => 0.0450, 'easy_access_isa' => 0.0475, 'notice' => 0.0500, 'fixed_1_year' => 0.0525] as $key => $rate) {
        SavingsMarketRate::create(['rate_key' => $key, 'label' => $key, 'rate' => $rate, 'tax_year' => $taxYear, 'effective_from' => now()->toDateString()]);
    }
    $this->comparator = app(RateComparator::class);
    $this->user = User::factory()->create();
});

it('treats the percentage column as a percentage against the decimal benchmark', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 10000, 'interest_rate' => 4.25,
    ]);
    $benchmark = 0.045;

    $result = $this->comparator->compareToMarketRates($account);

    expect($result['account_rate'])->toBe(0.0425)
        ->and($result['account_rate_percent'])->toBe(4.25)
        ->and($result['market_rate'])->toBe(round($benchmark, 4))
        ->and($result['market_rate_percent'])->toBe(round($benchmark * 100, 2))
        ->and($result['difference_percent'])->toBe(round(4.25 - $benchmark * 100, 2))
        ->and($result['category'])->toBe('Fair')
        ->and($result['is_competitive'])->toBeTrue();
});

it('rates a 2.5 per cent easy-access account as Poor against the seeded market', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 10000, 'interest_rate' => 2.5,
    ]);

    expect($this->comparator->compareToMarketRates($account)['category'])->toBe('Poor');
});

it('computes the annual interest difference in pounds from the percentage column', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 10000, 'interest_rate' => 2.5,
    ]);

    expect($this->comparator->calculateInterestDifference($account, 0.045))->toBe(200.0);
});
