<?php

declare(strict_types=1);

use App\Agents\SavingsAgent;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\SavingsAccount;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Actions\ActionHowTo;
use App\Services\Actions\ActionHowToFacts;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Cache;

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
    $this->seed(InvestmentActionDefinitionSeeder::class);
    $this->seed(EstateActionDefinitionSeeder::class);
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

it('gives the General Investment Account steps the dividend and Capital Gains Tax allowances from tax config', function () {
    // Item 15 (2026-10-07): the steps name both allowances; neither may be typed in (Rule 2).
    ['text' => $text] = app(ActionHowToFacts::class)->for(zeroRateSaver(), []);
    $steps = collect(ActionHowTo::parse((string) file_get_contents(database_path('seeders/data/action-how-to/savings.md')))['excess_cash_gia']['steps'])
        ->pluck('text')->implode(' ');

    expect($text['dividend_allowance'])->toBe('£500')
        ->and($text['cgt_allowance'])->toBe('£3,000')
        ->and($steps)->toContain('Dividends above your {dividend_allowance} dividend allowance')
        ->and($steps)->toContain('gains above your {cgt_allowance} Capital Gains Tax allowance');
});

it('fills the offset mortgage how-to with the card\'s rates and the cash above the emergency fund target', function () {
    SavingsActionDefinition::where('key', 'offset_mortgage_better')->update(['how_to_status' => 'approved']);
    $user = zeroRateSaver();
    Mortgage::factory()->create(['user_id' => $user->id, 'interest_rate' => 5.25, 'outstanding_balance' => 180000]);
    $offset = fn (): ?array => collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === 'offset_mortgage_better');

    // £4,500 is below a six-month target: nothing is spare, so no card (2026-10-08:
    // every account not ticked as emergency fund used to count as spare).
    expect($offset())->toBeNull();

    SavingsAccount::query()->where('user_id', $user->id)->update(['current_balance' => 40000]);
    Cache::flush();
    $analysis = app(SavingsAgent::class)->analyze($user->id);
    $spare = 40000 - 6 * (float) (($analysis['data'] ?? $analysis)['summary']['monthly_expenditure']);
    $item = $offset();
    $card = app(ActionCardService::class)->for($user, $item['id']);

    expect(implode(' ', $card['why']))->toContain('Your mortgage costs 5.25% a year. Your savings earn 0.00% on average')
        ->and(implode(' ', $card['why']))->toContain('You hold £'.number_format($spare, 0).' in savings above your emergency fund target.')
        ->and(implode(' ', $card['how_to']))->toContain('interest is charged on the mortgage less those savings')
        ->and(implode(' ', $card['how_to']))->not->toContain('{');
});
