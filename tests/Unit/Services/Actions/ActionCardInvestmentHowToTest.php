<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\InvestmentActionDefinition;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * Investment cards carry their key and their own figures (item 8, CSJ
 * 2026-10-06) through the adapter, composer, aggregator and NextActionsService
 * to ActionCardService. The how-to batch stays draft until CSJ approves it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
    $this->seed(InvestmentActionDefinitionSeeder::class);
    $this->seed(ActionHowToSeeder::class);
});

function chargedInvestor(): array
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000, 'annual_expenditure' => 30000,
        'monthly_expenditure' => 2500, 'onboarding_completed' => true, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(45)->toDateString(),
    ]);
    RiskProfile::factory()->create(['user_id' => $user->id, 'risk_level' => 'medium']);
    $account = InvestmentAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'account_name' => 'Test ISA', 'ownership_type' => 'individual',
        'joint_owner_id' => null, 'ownership_percentage' => 100, 'current_value' => 100000,
        'platform_fee_type' => 'percentage', 'platform_fee_percent' => 0.45, 'advisor_fee_percent' => 0.75,
    ]);
    Holding::factory()->create([
        'holdable_id' => $account->id, 'holdable_type' => InvestmentAccount::class, 'asset_type' => 'equity',
        'current_value' => 100000, 'cost_basis' => 90000, 'ocf_percent' => 0.2,
    ]);

    return [$user, $account];
}

function investmentCard(User $user, string $key): ?array
{
    return collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === $key);
}

it('keeps a draft investment entry off the card until CSJ approves it', function () {
    [$user] = chargedInvestor();
    $item = investmentCard($user, 'account_charges');

    expect($item)->not->toBeNull()
        ->and(InvestmentActionDefinition::where('key', 'account_charges')->value('how_to_status'))->toBe('draft')
        ->and(app(ActionCardService::class)->for($user, $item['id'])['how_to'])->toBe([]);
});

it('fills an approved charges how-to with the card\'s own figures, one card per account', function () {
    InvestmentActionDefinition::where('key', 'account_charges')->update(['how_to_status' => 'approved']);
    [$user, $account] = chargedInvestor();
    $item = investmentCard($user, 'account_charges');

    $card = app(ActionCardService::class)->for($user, $item['id']);

    expect($item['id'])->toBe('investment_account_charges_a'.$account->id)
        ->and($item['card']['figures'])->toMatchArray(['account_name' => 'Test ISA', 'annual_fees' => '£1,400', 'has_adviser_fee' => true, 'is_isa' => true])
        ->and(implode(' ', $card['why']))->toContain('Test ISA costs £1,400 a year in charges, 1.40% of its value')
        ->and(implode(' ', $card['how_to']))->toContain('Ask your adviser what their ongoing fee pays for')
        ->and(implode(' ', $card['how_to']))->toContain('ask the new provider to transfer the ISA');
});
