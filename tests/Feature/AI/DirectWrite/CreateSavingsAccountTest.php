<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S0.5.a — direct-write conversion of create_savings_account.
 * Pins the new handler contract: transactional persist + success envelope +
 * `created: true` so HasAiChat fires the `entity_created` SSE event.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('create_savings_account persists a SavingsAccount row directly', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Nationwide Cash ISA',
        'institution' => 'Nationwide',
        'account_type' => 'easy_access',
        'current_balance' => 5000,
        'interest_rate' => 4.5,
        'is_isa' => true,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['created'])->toBeTrue();
    expect($result['entity_type'])->toBe('savings_account');
    expect($result['entity_id'])->toBeInt();
    expect($result['name'])->toBe('Nationwide Cash ISA');
    expect($result)->toHaveKey('persisted_fields');

    $account = SavingsAccount::find($result['entity_id']);
    expect($account)->not->toBeNull();
    expect($account->user_id)->toBe($user->id);
    expect($account->account_name)->toBe('Nationwide Cash ISA');
    expect($account->institution)->toBe('Nationwide');
    expect((float) $account->current_balance)->toBe(5000.00);
    expect((float) $account->interest_rate)->toBe(4.5);
    expect($account->is_isa)->toBeTrue();
    expect($account->account_type)->toBe('cash_isa'); // ISA auto-inference
    expect($account->ownership_type)->toBe('individual');
    expect((float) $account->ownership_percentage)->toBe(100.00);
});

it('create_savings_account maps AI-specific account_type enums to DB values', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $fixed = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Shawbrook 5-year bond',
        'account_type' => 'fixed_term',
        'current_balance' => 20000,
    ], $user);

    expect(SavingsAccount::find($fixed['entity_id'])->account_type)->toBe('fixed');

    $regular = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'First Direct regular saver',
        'account_type' => 'regular_saver',
        'current_balance' => 300,
    ], $user);

    expect(SavingsAccount::find($regular['entity_id'])->account_type)->toBe('easy_access');
});

it('create_savings_account returns validation_failed on missing required field', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'institution' => 'Aviva',
    ], $user);

    expect($result)->toHaveKey('error');
    expect($result['error'])->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
    expect(SavingsAccount::count())->toBe(0);
});

it('create_savings_account blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Preview Test',
        'current_balance' => 1000,
    ], $user);

    expect($result['blocked'])->toBeTrue();
    expect(SavingsAccount::count())->toBe(0);
});

it('create_savings_account rejects duplicate account names for the same user', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Nationwide Cash ISA',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Nationwide Cash ISA',
        'current_balance' => 5000,
    ], $user);

    // Existing checkForDuplicate helper signals via `warning`, not `error`.
    expect($result['warning'] ?? false)->toBeTrue();
    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
});

it('create_savings_account return shape does not contain fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'account_name' => 'Test',
        'current_balance' => 100,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});
