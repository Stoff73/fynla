<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * "Fund from" on money-moving action cards (design C; CSJ 2026-09-26: build it in
 * the first pass). The user's eligible accounts, largest first; a joint account
 * at the user's own share (Rule 6); the pick is remembered.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function fundedUser(): User
{
    return User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0,
        'annual_interest_income' => 0, 'annual_rental_income' => 0, 'annual_other_income' => 0,
        'onboarding_completed' => true, 'marital_status' => 'single',
    ]);
}

function cash(User $user, string $name, float $balance, string $type = 'easy_access', array $extra = []): SavingsAccount
{
    return SavingsAccount::factory()->create(array_merge([
        'user_id' => $user->id, 'account_name' => $name, 'institution' => $name,
        'account_type' => $type, 'is_isa' => false, 'current_balance' => $balance,
        'interest_rate' => 4.5, 'ownership_type' => 'individual', 'joint_owner_id' => null,
    ], $extra));
}

it('lists the user own easy-access and current accounts on a money-moving card, largest first', function () {
    $user = fundedUser();
    cash($user, 'Santander Everyday', 4200, 'current_account');
    cash($user, 'Nationwide Instant Access', 40000, 'easy_access');
    Sanctum::actingAs($user);

    $card = $this->getJson('/api/recommendations/actions/tax_pension_tax_relief')->assertOk()->json('data');

    expect(collect($card['funding']['accounts'])->pluck('name')->all())->toBe(['Nationwide Instant Access', 'Santander Everyday'])
        ->and((float) $card['funding']['accounts'][0]['balance'])->toBe(40000.0)
        ->and($card['funding']['selected_id'])->toBe($card['funding']['accounts'][0]['id']);
});

it('shows a joint account at the user own share', function () {
    $user = fundedUser();
    $spouse = User::factory()->create();
    cash($user, 'Joint Saver', 30000, 'easy_access', ['ownership_type' => 'joint', 'joint_owner_id' => $spouse->id, 'ownership_percentage' => 50]);
    Sanctum::actingAs($user);

    $card = $this->getJson('/api/recommendations/actions/tax_pension_tax_relief')->assertOk()->json('data');

    $joint = collect($card['funding']['accounts'])->firstWhere('name', 'Joint Saver');
    expect((float) $joint['balance'])->toBe(15000.0);
});

it('remembers the account the user picked', function () {
    $user = fundedUser();
    cash($user, 'Nationwide Instant Access', 40000);
    $small = cash($user, 'Santander Everyday', 4200, 'current_account');
    Sanctum::actingAs($user);

    $this->putJson('/api/plans/tax/funding-source', [
        'action_category' => 'pension_tax_relief', 'target_account_id' => 0,
        'funding_source_type' => 'savings', 'funding_source_id' => $small->id,
    ])->assertOk();

    $card = $this->getJson('/api/recommendations/actions/tax_pension_tax_relief')->assertOk()->json('data');
    expect($card['funding']['selected_id'])->toBe($small->id);
});

it('rejects another user account as the funding source', function () {
    $user = fundedUser();
    cash($user, 'Mine', 40000);
    $theirs = cash(User::factory()->create(), 'Theirs', 9000);
    Sanctum::actingAs($user);

    $this->putJson('/api/plans/tax/funding-source', [
        'action_category' => 'pension_tax_relief', 'target_account_id' => 0,
        'funding_source_type' => 'savings', 'funding_source_id' => $theirs->id,
    ])->assertStatus(422);
});

it('carries no funding block on an action that moves no money', function () {
    Sanctum::actingAs(User::factory()->create(['onboarding_completed' => true]));
    $unlock = collect($this->getJson('/api/recommendations/actions')->json('data.open'))->firstWhere('type', 'unlock');

    expect($this->getJson('/api/recommendations/actions/'.rawurlencode($unlock['id']))->assertOk()->json('data.funding'))->toBeNull();
});
