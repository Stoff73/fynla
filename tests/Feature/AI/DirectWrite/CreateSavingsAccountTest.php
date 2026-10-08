<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
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

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Nationwide Cash ISA',
        'institution' => 'Nationwide',
        'account_type' => 'cash_isa',
        'current_balance' => 5000,
        'interest_rate' => 4.5,
        'is_isa' => true,
        'ownership_type' => 'individual',
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
    expect($account->account_type)->toBe('cash_isa');
    expect($account->ownership_type)->toBe('individual');
    expect((float) $account->ownership_percentage)->toBe(100.00);
});

it('create_savings_account maps AI-specific account_type enums to DB values', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $fixed = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Shawbrook 5-year bond',
        'account_type' => 'fixed_term',
        'current_balance' => 20000,
    ], $user);

    expect(SavingsAccount::find($fixed['entity_id'])->account_type)->toBe('fixed');

    $regular = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'First Direct regular saver',
        'account_type' => 'regular_saver',
        'current_balance' => 300,
    ], $user);

    expect(SavingsAccount::find($regular['entity_id'])->account_type)->toBe('easy_access');
});

it('create_savings_account returns validation_failed on missing required field', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'institution' => 'Aviva',
    ], $user);

    expect($result)->toHaveKey('error');
    expect($result['error'])->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
    expect(SavingsAccount::count())->toBe(0);
});

it('create_savings_account blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Preview Test',
        'current_balance' => 1000,
    ], $user);

    expect($result['blocked'])->toBeTrue();
    expect(SavingsAccount::count())->toBe(0);
});

it('create_savings_account asks rather than overwriting when the account already exists', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Nationwide Cash ISA',
        'current_balance' => 1000,
    ]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Nationwide Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 5000,
    ], $user);

    // SPEC-crud-handler-contract C2: a different balance on a record the user
    // already has is ambiguous — a correction, or a second account. RecaptureGuard
    // writes nothing and asks. This replaced `checkForDuplicate`, which warned and
    // discarded the value.
    expect($result['error_type'] ?? null)->toBe('confirm_edit_required');
    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
    expect((float) SavingsAccount::where('user_id', $user->id)->first()->current_balance)->toBe(1000.0);
});

it('asks the partner rather than adding a second copy of a joint account the other partner added', function (): void {
    // Full Luna run 2026-10-08: Sam added a joint Santander account; Jordan,
    // invited, described the same account and a second joint row was made
    // (278 and 279), counting £24,000 twice. Rule 6: one record per joint asset.
    $sam = User::factory()->create(['is_preview_user' => false]);
    $jordan = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $sam->id, 'joint_owner_id' => $jordan->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 4,
    ]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 4,
        'ownership_type' => 'joint',
    ], $jordan);

    expect($result['error_type'] ?? null)->toBe('confirm_duplicate_required')
        ->and($result['message'])->toContain('Is this a separate savings account you also hold, or the same one?')
        ->and(SavingsAccount::count())->toBe(1);
});

it('does not take an account the partner holds alone for the user\'s own', function (): void {
    $sam = User::factory()->create(['is_preview_user' => false]);
    $jordan = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $sam->id, 'joint_owner_id' => null, 'ownership_type' => 'individual',
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000,
    ]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'ownership_type' => 'individual',
    ], $jordan);

    expect($result['success'] ?? false)->toBeTrue()
        ->and(SavingsAccount::where('user_id', $jordan->id)->count())->toBe(1);
});

it('takes "the same one" on a partner\'s joint account as nothing to change, not a refused edit', function (): void {
    // Full Luna run 2026-10-08, turn 1744: asked "a separate savings account you
    // also hold, or the same one?", Jordan said "It's the same one"; the model
    // sent update_record with the stored values and Jordan, the joint owner,
    // was told "Record not found or unauthorized." Matching values write
    // nothing (SPEC-crud-handler-contract C2), whoever is the primary owner.
    $sam = User::factory()->create(['is_preview_user' => false]);
    $jordan = User::factory()->create(['is_preview_user' => false]);
    $account = SavingsAccount::factory()->create([
        'user_id' => $sam->id, 'joint_owner_id' => $jordan->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 4,
    ]);
    $before = $account->fresh()->updated_at;

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'savings_account', 'entity_id' => $account->id,
        'fields' => ['interest_rate' => 4, 'current_balance' => 24000],
    ], $jordan);

    expect($result['error'] ?? false)->toBeFalse()
        ->and($result['success'] ?? false)->toBeTrue()
        ->and($result['fields_updated'] ?? null)->toBe([])
        ->and($account->fresh()->updated_at->equalTo($before))->toBeTrue();
});

it('lets the joint owner change the joint account, which stays the partner\'s record', function (): void {
    // Both owners of a joint account own it (CSJ 2026-10-08).
    $sam = User::factory()->create(['is_preview_user' => false]);
    $jordan = User::factory()->create(['is_preview_user' => false, 'spouse_id' => $sam->id]);
    $sam->update(['spouse_id' => $jordan->id]);
    $account = SavingsAccount::factory()->create([
        'user_id' => $sam->id, 'joint_owner_id' => $jordan->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 4,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'savings_account', 'entity_id' => $account->id,
        'fields' => ['current_balance' => 30000],
    ], $jordan);

    expect($result['success'] ?? false)->toBeTrue()
        ->and((float) $account->fresh()->current_balance)->toBe(30000.0)
        ->and($account->fresh()->user_id)->toBe($sam->id);
});

it('fills the joint account from the joint owner\'s entry, with no second copy', function (): void {
    // The guard fills empty fields (a stored 0 counts as empty) on a record the
    // user owns (C2), and both owners own a joint account (CSJ 2026-10-08).
    $sam = User::factory()->create(['is_preview_user' => false]);
    $jordan = User::factory()->create(['is_preview_user' => false, 'spouse_id' => $sam->id]);
    $sam->update(['spouse_id' => $jordan->id]);
    $account = SavingsAccount::factory()->create([
        'user_id' => $sam->id, 'joint_owner_id' => $jordan->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 0,
    ]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Santander easy access savings', 'institution' => 'Santander',
        'account_type' => 'easy_access', 'current_balance' => 24000, 'interest_rate' => 4,
        'ownership_type' => 'joint',
    ], $jordan);

    expect($result['error'] ?? false)->toBeFalse()
        ->and((float) $account->fresh()->interest_rate)->toBe(4.0)
        ->and(SavingsAccount::count())->toBe(1);
});

it('create_savings_account return shape does not contain fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Test',
        'current_balance' => 100,
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});

it('stamps current-tax-year ISA subscriptions when the user states them', function (): void {
    // Live-browser finding 2026-06-11: without this field the freshly-captured
    // ISA's full balance masquerades as this-year subscriptions (created-this-
    // year proxy) and the ISA top-up strategy never fires.
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'current_balance' => 19000,
        'is_isa' => true,
        'isa_subscription_amount' => 100,
    ], $user);

    expect($result['created'] ?? false)->toBeTrue();

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect((float) $account->isa_subscription_amount)->toBe(100.0)
        ->and($account->isa_subscription_year)
        ->toBe(app(TaxConfigService::class)->getTaxYear());
});

it('ignores isa_subscription_amount on non-ISA accounts', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Marcus Savings',
        'current_balance' => 81000,
        'is_isa' => false,
        'isa_subscription_amount' => 100,
    ], $user);

    $account = SavingsAccount::where('user_id', $user->id)->first();
    expect($account->isa_subscription_amount)->toBeNull()
        ->and($account->isa_subscription_year)->toBeNull();
});
