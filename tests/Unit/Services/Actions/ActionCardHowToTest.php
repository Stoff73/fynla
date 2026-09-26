<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * How-to steps are fixed text per action definition, drafted and then reviewed
 * by CSJ (ruling 2026-09-25). A card shows them only once approved.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('shows steps only once they are approved', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 40000, 'interest_rate' => 4.5, 'ownership_type' => 'individual', 'joint_owner_id' => null]);

    TaxActionDefinition::where('strategy_type', 'pension_tax_relief')->update([
        'how_to_steps' => json_encode(['Decide how much to pay in.']),
        'how_to_status' => 'draft',
    ]);
    expect(app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'])->toBe([]);

    TaxActionDefinition::where('strategy_type', 'pension_tax_relief')->update(['how_to_status' => 'approved']);
    expect(app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'])->toBe(['Decide how much to pay in.']);
});
