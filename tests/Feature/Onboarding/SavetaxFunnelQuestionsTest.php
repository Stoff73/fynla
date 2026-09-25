<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * CSJ 2026-09-25: a user who never answered the Save Tax funnel page is
 * greeted by Fyn and asked the funnel questions in chat (employment, spouse,
 * spouse income, assets — the income band is not asked), then walks the
 * normal Save Tax flow from the same profile the funnel would have written.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
    config()->set('onboarding.forced_campaign', 'savetax');
});

function ftqUser(array $attrs = []): User
{
    $user = User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        'funnel_answers' => null,
        'employment_status' => null,
        'marital_status' => null,
        'household_calculation_mode' => null,
        'first_name' => 'Sam',
    ], $attrs));
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

function ftqStart(User $user): string
{
    Sanctum::actingAs($user->fresh());

    return test()->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
}

function ftqSay(User $user, string $message): string
{
    $conversation = AiConversation::forUser($user->id)->onboarding()->latest('id')->firstOrFail();
    Sanctum::actingAs($user->fresh());

    return test()->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => $message])
        ->streamedContent();
}

/** The bubbles of the most recent assistant turn, as ids. */
function ftqLastBubbleIds(User $user): array
{
    $conversation = AiConversation::forUser($user->id)->onboarding()->latest('id')->firstOrFail();
    $message = $conversation->messages()->where('role', 'assistant')->latest('id')->first();

    return array_map(fn (array $b) => $b['id'], (array) ($message->metadata['bubbles'] ?? []));
}

it('greets, asks employment, spouse, spouse income and assets, then maps the profile and enters base_work', function () {
    $user = ftqUser();
    $first = ftqStart($user);
    expect($first)->toContain("I'm Fyn")->toContain('employment');
    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT);

    ftqSay($user, 'Full-time');
    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE);

    ftqSay($user, 'Yes');
    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME);
    expect(ftqLastBubbleIds($user))->toBe(['zero', 'upto_50270', '50271_100000', '100001_125140', 'over_125140']);

    ftqSay($user, "They don't earn");
    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_ASSETS);

    ftqSay($user, 'Bank account');
    ftqSay($user, 'Pension');
    ftqSay($user, "That's everything");

    $user->refresh();
    expect($user->funnel_answers)->toMatchArray([
        'campaign' => 'savetax', 'employment' => 'full-time',
        'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => ['bank', 'pension'],
    ])
        ->and($user->employment_status)->toBe('full_time')
        ->and($user->marital_status)->toBe('married')
        ->and($user->household_calculation_mode)->toBe('single_earner_couple')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK);
});

it('labels the spouse-income bands from the tax configuration', function () {
    $user = ftqUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'spouse' => 'yes']]);
    ftqStart($user);
    $conversation = AiConversation::forUser($user->id)->onboarding()->latest('id')->firstOrFail();
    $labels = array_column((array) $conversation->messages()->where('role', 'assistant')->latest('id')->first()->metadata['bubbles'], 'label');

    $income = app(TaxConfigService::class)->getIncomeTax();
    expect($labels[1])->toContain(number_format((float) $income['higher_rate_threshold']))
        ->and($labels[4])->toContain(number_format((float) $income['additional_rate_threshold']));
});

it('skips spouse income when there is no spouse', function () {
    $user = ftqUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time']]);
    ftqStart($user);
    ftqSay($user, 'No');

    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_ASSETS);
});

it('sends a retired user to the retirement-date branch after the questions', function () {
    $user = ftqUser();
    ftqStart($user);
    ftqSay($user, 'Retired');
    ftqSay($user, 'No');
    ftqSay($user, "That's everything");
    $user->refresh();

    expect($user->employment_status)->toBe('retired')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::nextFromEmployment('', $user));
});

it('writes an empty asset list when That\'s everything is tapped first', function () {
    $user = ftqUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'spouse' => 'no'], 'employment_status' => 'full_time']);
    ftqStart($user);
    ftqSay($user, "That's everything");
    $user->refresh();

    expect($user->funnel_answers['assets'])->toBe([])
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK);
});

it('hides assets already picked and keeps That\'s everything last', function () {
    $user = ftqUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'spouse' => 'no']]);
    ftqStart($user);
    ftqSay($user, 'ISA');
    $ids = ftqLastBubbleIds($user);

    expect($ids)->not->toContain('isa')
        ->and(end($ids))->toBe('done')
        ->and($user->refresh()->funnel_answers['assets'])->toBe(['isa']);
});

it('greets only once per conversation', function () {
    $user = ftqUser();
    ftqStart($user);
    $conversation = AiConversation::forUser($user->id)->onboarding()->latest('id')->firstOrFail();

    $again = OnboardingStateMachine::buildFunnelEmploymentPrompt('', $user->refresh(), $conversation);
    expect($again)->not->toContain("I'm Fyn")->toContain('employment');
});

it('lets typed free text out of the new front door instead of looping', function () {
    // The first funnel question is now the front door path_choice used to be:
    // a user who types instead of tapping steps aside to advice Fyn rather
    // than "Sorry, I didn't catch that" forever (CSJ 2026-08-18 rule).
    $user = ftqUser(['onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT]);
    $conversation = AiConversation::create([
        'user_id' => $user->id, 'status' => 'active', 'model_used' => 'director',
        'title' => 'front door', 'message_count' => 0,
    ]);

    $events = iterator_to_array(
        app(OnboardingChatDirector::class)
            ->handleUserMessage($user, $conversation, 'let me just look around for now', '/dashboard'),
        false,
    );

    expect($user->refresh()->onboarding_fyn_step)->toBeNull()
        ->and($user->onboarding_completed)->toBeFalse()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->not->toContain("didn't catch that");
});

it('matches a typed employment answer written without the hyphen', function () {
    expect(OnboardingStateMachine::matchBubble(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT, 'I work full time'))->toBe('full-time')
        ->and(OnboardingStateMachine::matchBubble(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT, 'part time'))->toBe('part-time')
        ->and(OnboardingStateMachine::matchBubble(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT, "I'm self employed"))->toBe('self-employed');
});

it('does not greet a second time when the walk reaches income', function () {
    $user = ftqUser();
    ftqStart($user);
    ftqSay($user, 'Full-time');
    ftqSay($user, 'No');
    $turn = ftqSay($user, "That's everything");

    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK)
        ->and($turn)->not->toContain("I'm Fyn")
        ->and($turn)->toContain('income');
});

it('asks only the missing question for a partial funnel', function () {
    $user = ftqUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'spouse' => 'no']]);
    ftqStart($user);

    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_ASSETS);
});

it('Start over lands back on Save Tax, not the journey chooser', function () {
    $user = ftqUser([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        'funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'spouse' => 'no', 'assets' => ['bank']],
        'employment_status' => 'full_time',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding',
    ]);

    $events = iterator_to_array(
        app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'restart'),
        false,
    );
    $user->refresh();

    expect($user->onboarding_fyn_path)->toBe('campaign')
        ->and($user->onboarding_fyn_selection)->toBe('savetax')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK)
        ->and(collect($events)->pluck('type')->all())->toContain('done');
});

it('Start over for a user who never answered the funnel asks the questions again', function () {
    $user = ftqUser([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding',
    ]);

    iterator_to_array(app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'restart'), false);

    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT);
});

it('the default first turn is Save Tax while it is forced', function () {
    $user = ftqUser(['onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax']);
    $conversation = AiConversation::create([
        'user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding',
    ]);

    $text = collect(iterator_to_array(app(OnboardingChatDirector::class)->emitFirstTurn($user, $conversation), false))
        ->pluck('prompt_text')->filter()->implode(' ');

    expect($text)->toContain('employment')->not->toContain('life-stage journey');
});

it('asks "anything else" after the first asset instead of repeating the whole question', function () {
    $user = ftqUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'spouse' => 'no']]);
    $first = ftqStart($user);
    expect($first)->toContain('Which of these do you have?');

    $again = ftqSay($user, 'ISA');
    expect($again)->toContain('Anything else?')->not->toContain('Which of these do you have?');
});
