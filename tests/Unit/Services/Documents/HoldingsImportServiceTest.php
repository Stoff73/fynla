<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Documents\HoldingsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('matches sheet to existing account by type and provider', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'isa',
        'provider' => 'Hargreaves Lansdown',
    ]);

    $service = new HoldingsImportService;
    $match = $service->findMatchingAccount($user, 'investment_holdings', [
        'account_type' => 'isa',
        'provider' => 'Hargreaves Lansdown',
    ]);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($account->id);
});

it('returns null when no matching account found', function () {
    $user = User::factory()->create();

    $service = new HoldingsImportService;
    $match = $service->findMatchingAccount($user, 'investment_holdings', [
        'account_type' => 'isa',
        'provider' => 'Vanguard',
    ]);

    expect($match)->toBeNull();
});

it('diffs imported holdings against existing', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);

    Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'security_name' => 'Vanguard FTSE 100',
        'ticker' => 'VUKE',
        'isin' => 'IE00B810Q511',
        'quantity' => 100,
        'current_value' => 5000,
    ]);

    $service = new HoldingsImportService;
    $diff = $service->diffHoldings($account, [
        ['security_name' => 'Vanguard FTSE 100', 'isin' => 'IE00B810Q511', 'quantity' => 150, 'current_value' => 7500],
        ['security_name' => 'iShares Core MSCI World', 'ticker' => 'IWDA', 'quantity' => 200, 'current_value' => 12000],
    ]);

    // VUKE matched by ISIN, quantity changed
    $vukeResult = collect($diff)->firstWhere('security_name', 'Vanguard FTSE 100');
    expect($vukeResult['status'])->toBe('update');

    // IWDA not found
    $iwdaResult = collect($diff)->firstWhere('security_name', 'iShares Core MSCI World');
    expect($iwdaResult['status'])->toBe('add');
});

it('marks unchanged holdings correctly', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);

    Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'security_name' => 'Vanguard FTSE 100',
        'ticker' => 'VUKE',
        'isin' => 'IE00B810Q511',
        'quantity' => 100,
        'current_value' => 5000,
    ]);

    $service = new HoldingsImportService;
    $diff = $service->diffHoldings($account, [
        ['security_name' => 'Vanguard FTSE 100', 'isin' => 'IE00B810Q511', 'quantity' => 100, 'current_value' => 5000],
    ]);

    expect($diff[0]['status'])->toBe('unchanged');
});

it('applies holdings import correctly', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);

    $service = new HoldingsImportService;
    $result = $service->applyHoldings($account, [
        ['status' => 'add', 'security_name' => 'Test Fund', 'ticker' => 'TF', 'quantity' => 50, 'current_value' => 1000, 'asset_type' => 'fund'],
    ]);

    expect($result['created'])->toBe(1);
    expect($account->holdings()->count())->toBe(1);
    expect($account->holdings()->first()->security_name)->toBe('Test Fund');
});
