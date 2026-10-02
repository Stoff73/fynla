<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Actions\ActionHowToFacts;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * A savings card's how-to names the same account, rate and balance its title
 * does: the figures the recommendation was written from travel to the card
 * (buildRecommendation → adapter → composer → aggregator → NextActionsService
 * → ActionCardService → ActionHowToFacts). The batch stays draft until CSJ
 * approves it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
    $this->seed(ActionHowToSeeder::class);
});

function zeroRateSaver(): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 40000, 'monthly_expenditure' => 2000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_name' => 'Joint Current Account', 'institution' => 'Nationwide',
        'account_type' => 'current_account', 'access_type' => 'immediate', 'is_isa' => false,
        'current_balance' => 4500, 'interest_rate' => 0, 'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    return $user;
}

it('keeps a draft savings entry off the card until CSJ approves it', function () {
    expect(SavingsActionDefinition::whereNotNull('how_to_steps')->count())->toBeGreaterThan(30);
    SavingsActionDefinition::where('key', 'zero_rate_account')->update(['how_to_status' => 'draft']);

    $user = zeroRateSaver();
    $item = collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === 'zero_rate_account');

    expect($item)->not->toBeNull()
        ->and(app(ActionCardService::class)->for($user, $item['id'])['how_to'])->toBe([]);
});

it('fills an approved savings how-to with the card\'s own account and balance', function () {
    SavingsActionDefinition::where('key', 'zero_rate_account')->update(['how_to_status' => 'approved']);
    $user = zeroRateSaver();
    $item = collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === 'zero_rate_account');

    $card = app(ActionCardService::class)->for($user, $item['id']);

    expect($item['card']['figures'])->toMatchArray(['account_name' => 'Joint Current Account', 'balance' => '£4,500'])
        ->and($card['why'])->toContain('Joint Current Account holds £4,500 at no interest.')
        // Not an ISA, so the ISA transfer step is left out.
        ->and(implode(' ', $card['how_to']))->not->toContain('ISA transfer form')
        ->and($card['how_to'])->toContain('Move the money across, keeping whatever you need for day-to-day spending where it is.')
        // The rate-gap lines need figures a zero-rate card does not have, so they are left out.
        ->and(implode(' ', $card['why']))->not->toContain('pays %');
});

it('knows when the user\'s ISA allowance is used and names their children, for the ISA-full, spouse and children branches', function () {
    $user = zeroRateSaver();
    InvestmentAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'current_value' => 30000,
        'isa_subscription_current_year' => 20000, 'contributions_ytd' => 20000,
    ]);
    FamilyMember::factory()->create([
        'user_id' => $user->id, 'relationship' => 'child', 'first_name' => 'Emma', 'is_dependent' => true,
        'date_of_birth' => now()->subYears(6)->toDateString(),
    ]);

    ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, []);

    expect($facts['isa_full'])->toBeTrue()
        ->and($text)->not->toHaveKey('isa_left')
        ->and($facts['has_children'])->toBeTrue()
        ->and($text['children'])->toBe('Emma');
});

it('never calls a child\'s Junior ISA the user\'s Cash ISA', function () {
    // Live on fynla.org 2026-09-29: "You can add to your Cash ISA with Vanguard"
    // for the Carters, whose only Vanguard ISA is Oliver's Junior ISA.
    $user = zeroRateSaver();
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_name' => "Oliver's Junior ISA", 'institution' => 'Vanguard',
        'account_type' => 'junior_isa', 'is_isa' => true, 'isa_type' => null,
        'current_balance' => 2800, 'interest_rate' => 0, 'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, []);

    expect($facts['has_cash_isa'])->toBeFalse()
        ->and($text)->not->toHaveKey('cash_isa');
});

it('gives a list row the same topic as its card, so no engine bucket like "Lifecycle" shows', function () {
    // Live on fynla.org 2026-09-29: the actions list read "Savings · Lifecycle"
    // and "Estate Planning · Warning" after the card itself was fixed (#965).
    $user = zeroRateSaver();
    $item = collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === 'zero_rate_account');

    expect(strtolower((string) $item['card']['category']))->toBe('lifecycle')
        ->and($item['meta'])->toBe('')
        ->and($item['meta'])->toBe((string) ActionCardService::topicFor($item['card']['category']));
});
