<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * "Ask Fyn about this" on an action card (2026-09-26 walk): Fyn was sent the
 * card's question with nothing tying it to the card, rebuilt the pension figures
 * from other tool data, left the personal pension out and called the action a
 * "mechanical-tier strategy". The question is grounded in the card the user is
 * looking at — the same payload web, /m and iOS render — whatever the classifier
 * files it under.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

/** The walk household: £60,000, Nest 5% + 3%, Vanguard personal pension £200 a month. */
function askFynWalkUser(): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_name' => 'Nest', 'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_name' => 'Vanguard', 'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);

    return $user;
}

function askFynTurn(User $user, string $message, string $primary): string
{
    return app(FynContextAssembler::class)->build(FynTurnContext::make(
        user: $user, message: $message, currentRoute: '/actions/tax_pension_tax_relief',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => $primary],
    ));
}

it('grounds the card\'s own question in that card, though the classifier files it under retirement', function (): void {
    $user = askFynWalkUser();
    $card = app(ActionCardService::class)->for($user, 'tax_pension_tax_relief');

    expect($card['ask_fyn']['prompt'])->toBe(ActionCardService::ASK_FYN_PREFIX.$card['title']);

    $out = askFynTurn($user, $card['ask_fyn']['prompt'], 'retirement_contribution');

    expect($out)->toContain('<action_grounding>')
        ->toContain($card['description'])
        ->toContain('get_recommendations')
        ->and(substr_count($out, '<action_grounding>'))->toBe(1)
        // Every part of what has gone in this year, adding up to the total: the
        // walk's narration left the personal pension out of the £7,800.
        ->and($out)->toContain('pension_paid_in_this_year: £7,800 in total')
        ->toContain('<user_provided>Nest</user_provided> £4,800 (you pay £3,000, your employer pays £1,800)')
        ->toContain('<user_provided>Vanguard</user_provided> £3,000 (you pay £2,400, the provider adds £600 of basic-rate tax relief)');
});

it('leaves a question that names no action of the user\'s ungrounded', function (): void {
    $user = askFynWalkUser();

    expect(askFynTurn($user, ActionCardService::ASK_FYN_PREFIX.'Pay £9,999 more into your pension', 'retirement_contribution'))
        ->not->toContain('<action_grounding>')
        ->and(askFynTurn($user, 'Tell me more about pensions', 'retirement_contribution'))
        ->not->toContain('<action_grounding>');
});

it('never grounds another user\'s action', function (): void {
    $owner = askFynWalkUser();
    $card = app(ActionCardService::class)->for($owner, 'tax_pension_tax_relief');
    $other = User::factory()->create(['onboarding_completed' => true]);

    expect(askFynTurn($other, $card['ask_fyn']['prompt'], 'retirement_contribution'))
        ->not->toContain('<action_grounding>');
});

// L3-3 (fynla.org /m, 29 Sep 2026): an action that is one of a set of
// alternatives says so in Fyn's grounding, from the plan's own sentence.
it('carries the plan\'s alternatives sentence into the grounding', function (): void {
    $user = askFynWalkUser();
    $note = '"Wrap savings in an ISA" is an alternative to "Gift savings to your spouse" and "Share savings 50/50": doing one changes or removes the saving from the other, so their savings do not add up. The plan total counts "Gift savings to your spouse" instead of this one.';
    $card = [
        'title' => 'Wrap savings in an ISA',
        'module_label' => 'Tax',
        'description' => 'Move £20,000 into a Cash ISA.',
        'why' => [],
        'what_this_changes' => [],
        'key_figure' => null,
        'how_to' => [],
        'conflict_note' => 'Alternative to "Gift savings to your spouse" — compare before doing both.',
        'alternatives_note' => $note,
    ];

    // ActionCardService is final; exercise the one grounding builder directly.
    $grounding = (new ReflectionMethod(FynContextAssembler::class, 'actionGrounding'))
        ->invoke(app(FynContextAssembler::class), $card, $user);

    expect($grounding)->toContain("alternatives: {$note}");
});
