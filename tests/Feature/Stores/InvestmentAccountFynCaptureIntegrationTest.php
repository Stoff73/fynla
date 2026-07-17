<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AuditLog;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
    $this->user = User::factory()->create(['tier' => 'premium']);
});

it('creates an investment account via Fyn AI handleCreateInvestmentAccount with FYN_AI audit context', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateInvestmentAccount');
    $method->setAccessible(true);

    $input = [
        'account_name' => 'Vanguard Stocks & Shares',
        'current_value' => 25000,
        'account_type' => 'stocks_shares_isa',
        'provider' => 'Vanguard',
        'monthly_contribution_amount' => 500,
        'ownership_type' => 'individual',
    ];

    $result = $method->invoke($agent, $input, $this->user, false);

    expect($result['success'] ?? false)->toBeTrue();
    expect($result['entity_type'] ?? null)->toBe('investment_account');

    $this->assertDatabaseHas('investment_accounts', [
        'user_id' => $this->user->id,
        'account_name' => 'Vanguard Stocks & Shares',
        'account_type' => 'isa',
        'isa_type' => 'stocks_and_shares',
    ]);

    $account = InvestmentAccount::where('account_name', 'Vanguard Stocks & Shares')->first();
    $auditRow = AuditLog::where('model_type', InvestmentAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();
    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('rejects preview-user investment account creates via Fyn', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateInvestmentAccount');
    $method->setAccessible(true);

    $result = $method->invoke($agent, [
        'account_name' => 'Preview Account',
        'current_value' => 1000,
    ], $this->user, true);

    expect($result['blocked'] ?? false)->toBeTrue();
});

it('returns tier_limit_reached error when free-tier user reaches the investment cap of 2', function () {
    $freeUser = User::factory()->create(['tier' => 'free']);

    InvestmentAccount::factory()->create(['user_id' => $freeUser->id]);
    InvestmentAccount::factory()->create(['user_id' => $freeUser->id]);

    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateInvestmentAccount');
    $method->setAccessible(true);

    $result = $method->invoke($agent, [
        'account_name' => 'Third Account',
        'current_value' => 5000,
        'account_type' => 'personal_investment_account',
    ], $freeUser, false);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('tier_limit_reached');
});

it('rejects ISA with non-individual ownership', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateInvestmentAccount');
    $method->setAccessible(true);

    $result = $method->invoke($agent, [
        'account_name' => 'Joint ISA',
        'current_value' => 10000,
        'account_type' => 'stocks_shares_isa',
        'ownership_type' => 'joint',
    ], $this->user, false);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect($result['message'] ?? '')->toContain('ISAs can only be individually owned');
});
