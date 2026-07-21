<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('create_investment_account persists an InvestmentAccount row directly', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
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

it('create_investment_account records the ISA subscription this tax year and counts it toward the allowance', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'Vanguard Stocks & Shares ISA',
        'account_type' => 'stocks_shares_isa',
        'current_value' => 30000,
        'isa_subscription_current_year' => 5000, // "I've put in £5,000 this year"
    ], $user);

    $account = InvestmentAccount::find($result['entity_id']);
    expect($account->account_type)->toBe('isa');
    expect((float) $account->isa_subscription_current_year)->toBe(5000.00);

    // End-to-end: the ISA allowance calc now sees the S&S ISA subscription, so
    // the £20k allowance is correctly reduced to £15k of headroom (Bug A + Bug B).
    expect(app(TaxStrategyMath::class)->estimateIsaSubscriptionsThisYear($user->fresh()))
        ->toBe(5000.0);
});

it('create_investment_account maps personal_investment_account to gia', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'HL GIA',
        'account_type' => 'personal_investment_account',
        'current_value' => 10000,
    ], $user);

    expect(InvestmentAccount::find($result['entity_id'])->account_type)->toBe('gia');
});

it('create_investment_account feeds taxable dividend income onto the user profile', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'annual_dividend_income' => null]);

    $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'General Investment Account',
        'account_type' => 'personal_investment_account',
        'current_value' => 40000,
        'annual_dividend_income' => 800,
    ], $user);

    // The tax-strategy engine reads users.annual_dividend_income for
    // Dividend Allowance usage — the capture must populate it.
    expect((float) $user->fresh()->annual_dividend_income)->toBe(800.00);

    // A second dividend-paying account accumulates.
    $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'Share Trading Account',
        'account_type' => 'personal_investment_account',
        'current_value' => 5000,
        'annual_dividend_income' => 200,
    ], $user);

    expect((float) $user->fresh()->annual_dividend_income)->toBe(1000.00);
});

it('create_investment_account never counts ISA dividends against the Dividend Allowance', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'annual_dividend_income' => null]);

    $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'Vanguard Stocks & Shares ISA',
        'account_type' => 'stocks_shares_isa',
        'current_value' => 30000,
        'annual_dividend_income' => 600,
    ], $user);

    // ISA dividends are tax-free — they must not touch the taxable figure.
    expect($user->fresh()->annual_dividend_income)->toBeNull();
});

it('create_investment_account passes through specialised types unchanged', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'premium']);

    foreach (['vct', 'eis', 'private_company', 'crowdfunding', 'saye'] as $type) {
        $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
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

    $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
        'provider' => 'Vanguard',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
    expect(InvestmentAccount::count())->toBe(0);
});

it('create_investment_account blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'Preview',
        'current_value' => 1000,
    ], $user);

    expect($result['blocked'])->toBeTrue();
    expect(InvestmentAccount::count())->toBe(0);
});

it('create_investment_account return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_investment_account', [
        'account_name' => 'Test',
        'current_value' => 100,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});
