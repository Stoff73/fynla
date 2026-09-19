<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\TaxConfigService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function invokeSectionAdvice(User $user, string $section): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, $section);
}

it('voices the ISA wrap strategy in the savings section (the Azlan miss)', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000, 'household_calculation_mode' => 'single_earner_couple',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 19000,
        'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
        'isa_subscription_amount' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $text = invokeSectionAdvice($user->fresh(), 'savings');

    expect($text)->not->toBeNull()
        ->and($text)->toContain('ISA')
        ->and($text)->toMatch('/£[\d,]+/');
});

it('hedges judgement-tier strategies and states mechanical ones directly', function () {
    // income section voices pa_taper_rescue (claim_tier=mechanical) — no hedge prefix.
    $user = User::factory()->create([
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
        'date_of_birth' => '1980-06-01',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
    ]);

    $text = invokeSectionAdvice($user->fresh(), 'income');

    // Mechanical strategy: should NOT contain the hedge prefix.
    expect($text)->not->toBeNull()
        ->and($text)->not->toContain('You may want to consider:');
});

it('returns null for a section with no applicable strategies', function () {
    $user = User::factory()->create(['annual_employment_income' => 25000, 'monthly_expenditure' => 1500]);

    expect(invokeSectionAdvice($user->fresh(), 'investments'))->toBeNull();
});

it('degrades to a sensible closing line for synthesis with an empty plan', function () {
    // D1 Fix 6 — synthesis previously returned null (silent) when the plan was
    // empty; the final recap turn must instead voice an honest closing line.
    $user = User::factory()->create(['first_name' => 'Jo']);

    $text = invokeSectionAdvice($user->fresh(), 'synthesis');

    expect($text)->not->toBeNull()
        ->and($text)->toContain('Jo');
});

it('returns null for unmapped sections (giving, expenditure)', function () {
    $user = User::factory()->create();

    expect(invokeSectionAdvice($user->fresh(), 'giving'))->toBeNull();
    expect(invokeSectionAdvice($user->fresh(), 'expenditure'))->toBeNull();
});

// Azlan, 2026-09-18: "sounds a little sulky/negative when making
// recommendations". Fyn voices the title and the fact sentence; the caveats
// stay on the tax strategy page, and there is no hedge prefix at any tier.
it('voices a strategy as its title and fact sentence, without the hedge or the caveats', function () {
    $item = [
        'claim_tier' => 'judgement',
        'title' => 'Bed & ISA — potentially shelter £3,000 of gains this year',
        'description' => 'You hold £12,000 of unrealised gains outside your ISA. Selling around £20,000 of holdings and rebuying them inside an ISA could crystallise up to £3,000 within the annual exempt amount, but only if you have not already used the allowance. Confirm gains and losses elsewhere this tax year, ISA subscriptions, dealing costs and market risk before acting.',
    ];

    $line = OnboardingChatDirector::voiceStrategyItem($item);

    expect($line)->toBe('Bed & ISA — potentially shelter £3,000 of gains this year. You hold £12,000 of unrealised gains outside your ISA. Selling around £20,000 of holdings and rebuying them inside an ISA could crystallise up to £3,000 within the annual exempt amount.')
        ->and($line)->not->toContain('You may want to consider')
        ->and($line)->not->toContain('before acting');
});
