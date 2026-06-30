<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

/**
 * Mirrors SectionAdviceFromCatalogueTest::invokeSectionAdvice — uniquely
 * prefixed because Pest file-scope functions are global across the run and
 * both files load together in a full-suite pass.
 */
function synthesisInvokeSectionAdvice(User $user, string $section): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, $section);
}

it('routes the campaign to a synthesis advice state before the terminal state', function () {
    $states = OnboardingStateMachine::states();

    expect($states)->toHaveKey(OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS)
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['turn_type'])->toBe('advice')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['advice_section'])->toBe('synthesis')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['next'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL);
});

it('voices a bulleted plan that mirrors the /tax-strategy dashboard (no numbering, no locked tease)', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000, 'household_calculation_mode' => 'single_earner_couple',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $text = synthesisInvokeSectionAdvice($user->fresh(), 'synthesis');

    expect($text)->not->toBeNull()
        ->and($text)->toContain('- ')                        // markdown bullets, like every other recap screen
        ->and($text)->toMatch('/Together these .*£[\d,]+/')  // combined total line
        ->and($text)->toContain('qualified financial adviser'); // FCA signposting

    // The /tax-strategy dashboard renders ONLY composed_plan.items — never the
    // locked strategies. The chat summary must match the page the user taps
    // straight through to, so the "one more strategy is waiting" tease is gone.
    expect($text)->not->toContain('One more strategy is waiting');
});

it('persists the voiced synthesis to ai_messages so /tax-strategy shows exactly what was said (A4)', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000, 'household_calculation_mode' => 'single_earner_couple',
    ]);
    $user->forceFill([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS,
        'onboarding_fyn_path' => 'campaign',
    ])->save();
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $director = app(OnboardingChatDirector::class);
    $stateId = OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS;
    $state = OnboardingStateMachine::getState($stateId);

    // emitAdviceTurn is the production path for the synthesis state
    // (emitTurnForState routes turn_type 'advice' straight to it); reflection
    // on private internals is the suite's established pattern.
    $m = new ReflectionMethod($director, 'emitAdviceTurn');
    $m->setAccessible(true);
    $events = iterator_to_array($m->invoke($director, $user->fresh(), $conv, $stateId, $state), false);

    $voiced = collect($events)->firstWhere('type', 'content')['text'] ?? null;

    $row = AiMessage::where('conversation_id', $conv->id)
        ->where('role', 'assistant')
        ->get()
        ->first(fn (AiMessage $msg): bool => ($msg->metadata['advice_section'] ?? null) === 'synthesis');

    expect($voiced)->not->toBeNull()
        ->and($row)->not->toBeNull()
        ->and($row->content)->toBe($voiced) // the row holds EXACTLY what Fyn voiced
        ->and($row->metadata['onboarding_step'])->toBe($stateId);
});

it('returns null synthesis for a user with no strategies so the turn stays silent', function () {
    $user = User::factory()->create(['annual_employment_income' => 0, 'monthly_expenditure' => 0]);

    expect(synthesisInvokeSectionAdvice($user->fresh(), 'synthesis'))->toBeNull();
});
