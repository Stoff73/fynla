<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionActionDefinition;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * Protection cards are the protection action definitions (CSJ 2026-09-29), so a
 * card carries its definition key, its own figures and, for a per-policy rule,
 * its policy: buildRecommendation → adapter → composer → aggregator →
 * NextActionsService → ActionCardService → ActionHowToFacts. The batch stays
 * draft until CSJ approves it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
    $this->seed(ActionHowToSeeder::class);
});

function untrustedPolicyHolder(): array
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000, 'annual_expenditure' => 30000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
    ProtectionProfile::factory()->create([
        'user_id' => $user->id, 'annual_income' => 60000, 'monthly_expenditure' => 2500,
        'mortgage_balance' => 0, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => [],
    ]);
    $policy = LifeInsurancePolicy::factory()->create([
        'user_id' => $user->id, 'provider' => 'Aviva', 'sum_assured' => 250000, 'in_trust' => false, 'joint_life' => false,
    ]);

    return [$user, $policy];
}

function trustCard(User $user): ?array
{
    return collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === 'policy_not_in_trust');
}

it('keeps a draft protection entry off the card until CSJ approves it', function () {
    // The seeded batch is approved (f2fbf7ec7); put this entry back in draft
    // rather than depend on the seed's current state.
    ProtectionActionDefinition::where('key', 'policy_not_in_trust')->update(['how_to_status' => 'draft']);
    [$user] = untrustedPolicyHolder();
    $item = trustCard($user);

    expect($item)->not->toBeNull()
        ->and(app(ActionCardService::class)->for($user, $item['id'])['how_to'])->toBe([]);
});

it('fills an approved protection how-to with the card\'s own insurer, one card per policy', function () {
    ProtectionActionDefinition::where('key', 'policy_not_in_trust')->update(['how_to_status' => 'approved']);
    [$user, $policy] = untrustedPolicyHolder();
    $item = trustCard($user);

    $card = app(ActionCardService::class)->for($user, $item['id']);

    expect($item['id'])->toEndWith('_p'.$policy->id)
        ->and($item['card']['figures'])->toMatchArray(['provider' => 'Aviva'])
        ->and($card['how_to'])->toContain('Ask Aviva for its trust form.')
        ->and(implode(' ', $card['why']))->toContain('Your life policy with Aviva is not in trust.');
});
