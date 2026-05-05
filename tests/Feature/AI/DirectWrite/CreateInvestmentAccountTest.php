<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('create_investment_account persists an InvestmentAccount row directly', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_investment_account', [
        'account_name' => 'Vanguard Stocks & Shares ISA',
        'provider' => 'Vanguard',
        'account_type' => 'stocks_shares_isa',
        'current_value' => 25000,
        'monthly_contribution_amount' => 500,
        'platform_fee_percent' => 0.15,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['created'])->toBeTrue();
    expect($result['entity_type'])->toBe('investment_account');
    expect($result['entity_id'])->toBeInt();

    $account = InvestmentAccount::find($result['entity_id']);
    expect($account)->not->toBeNull();
    expect($account->user_id)->toBe($user->id);
    expect($account->account_type)->toBe('isa'); // AI->DB enum mapping
    expect($account->provider)->toBe('Vanguard');
    expect((float) $account->current_value)->toBe(25000.00);
    expect((float) $account->monthly_contribution_amount)->toBe(500.00);
    expect($account->ownership_type)->toBe('individual');
});

it('create_investment_account maps personal_investment_account to gia', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_investment_account', [
        'account_name' => 'HL GIA',
        'account_type' => 'personal_investment_account',
        'current_value' => 10000,
    ], $user);

    expect(InvestmentAccount::find($result['entity_id'])->account_type)->toBe('gia');
});

it('create_investment_account passes through specialised types unchanged', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    foreach (['vct', 'eis', 'private_company', 'crowdfunding', 'saye'] as $type) {
        $result = app(CoordinatingAgent::class)->executeTool('create_investment_account', [
            'account_name' => "Test {$type}",
            'account_type' => $type,
            'current_value' => 1000,
        ], $user);

        expect($result['success'])->toBeTrue();
        expect(InvestmentAccount::find($result['entity_id'])->account_type)->toBe($type);
    }
});

it('create_investment_account returns validation_failed on missing required field', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_investment_account', [
        'provider' => 'Vanguard',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
    expect(InvestmentAccount::count())->toBe(0);
});

it('create_investment_account blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_investment_account', [
        'account_name' => 'Preview',
        'current_value' => 1000,
    ], $user);

    expect($result['blocked'])->toBeTrue();
    expect(InvestmentAccount::count())->toBe(0);
});

it('create_investment_account return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_investment_account', [
        'account_name' => 'Test',
        'current_value' => 100,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});
