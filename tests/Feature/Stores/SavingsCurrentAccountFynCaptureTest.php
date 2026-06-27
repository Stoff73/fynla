<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * /bug 6.2 — a bank/current account captured by Fyn must store as the canonical
 * `current_account` type, not be coerced to the `easy_access` savings product.
 * The live xAI tool schema offers `current_account`; the handler used to strip
 * it back to `easy_access`.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['tier' => 'tier1']);
});

function invokeCreateSavings(object $user, array $input): array
{
    $agent = app(CoordinatingAgent::class);
    $method = (new ReflectionClass($agent))->getMethod('handleCreateSavingsAccount');
    $method->setAccessible(true);

    return $method->invoke($agent, $input, $user, false);
}

it('stores a current_account as current_account, not easy_access', function () {
    $result = invokeCreateSavings($this->user, [
        'account_name' => 'Barclays Current',
        'current_balance' => 5000,
        'account_type' => 'current_account',
    ]);

    expect($result['success'] ?? false)->toBeTrue();
    $this->assertDatabaseHas('savings_accounts', [
        'user_id' => $this->user->id,
        'account_name' => 'Barclays Current',
        'account_type' => 'current_account',
    ]);
});

it('maps a free-text "bank" account_type to current_account', function () {
    invokeCreateSavings($this->user, [
        'account_name' => 'High Street Bank',
        'current_balance' => 3000,
        'account_type' => 'bank',
    ]);

    $this->assertDatabaseHas('savings_accounts', [
        'user_id' => $this->user->id,
        'account_name' => 'High Street Bank',
        'account_type' => 'current_account',
    ]);
});

it('still defaults a genuinely untyped savings account to easy_access', function () {
    invokeCreateSavings($this->user, [
        'account_name' => 'Rainy Day',
        'current_balance' => 2000,
        'account_type' => 'savings',
    ]);

    $this->assertDatabaseHas('savings_accounts', [
        'user_id' => $this->user->id,
        'account_name' => 'Rainy Day',
        'account_type' => 'easy_access',
    ]);
});
