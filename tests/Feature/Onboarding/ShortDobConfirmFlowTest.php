<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Drain a director generator into a list of events. */
function drainDobEvents(Generator $gen): array
{
    $events = [];
    foreach ($gen as $e) {
        $events[] = $e;
    }

    return $events;
}

function shortDobUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_DOB,
        'marital_status' => 'single',
        'funnel_answers' => ['assets' => ['bank']],
        'date_of_birth' => '1982-02-19',
    ], $overrides));
}

it('confirms back a two-digit-year DOB in full before advancing', function () {
    $user = shortDobUser();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $m = new ReflectionMethod($director, 'maybeConfirmShortDob');
    $m->setAccessible(true);
    $events = drainDobEvents($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_CAMPAIGN_DOB,
        ['date_of_birth' => '1982-02-19', 'marital_status' => null],
        '19/02/82'
    ));

    $qr = collect($events)->firstWhere('type', 'quick_replies');
    expect($qr)->not->toBeNull()
        ->and($qr['prompt_text'])->toContain('19th February 1982')
        ->and($qr['prompt_text'])->toContain('is that correct?')
        ->and(collect($qr['bubbles'])->pluck('id')->all())->toBe(['yes', 'no']);

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_dob_confirm']['dob'])->toBe('1982-02-19')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB); // held
});

it('confirms a textual-month short-year DOB too', function () {
    $user = shortDobUser();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $m = new ReflectionMethod($director, 'maybeConfirmShortDob');
    $m->setAccessible(true);
    $events = drainDobEvents($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_CAMPAIGN_DOB,
        ['date_of_birth' => '1982-02-19'],
        'born 19 Feb 82'
    ));

    expect(collect($events)->firstWhere('type', 'quick_replies'))->not->toBeNull();
});

it('does not confirm a full-format DOB — no extra gate', function () {
    $user = shortDobUser();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $m = new ReflectionMethod($director, 'maybeConfirmShortDob');
    $m->setAccessible(true);

    foreach (['19 February 1982', '19/02/1982', 'I was born on 19-02-1982'] as $fullForm) {
        $events = drainDobEvents($m->invoke(
            $director, $user, $conversation,
            OnboardingStateMachine::STATE_CAMPAIGN_DOB,
            ['date_of_birth' => '1982-02-19'],
            $fullForm
        ));
        expect($events)->toBe([]);
    }

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_dob_confirm'] ?? null)->toBeNull();
});

it('does not confirm outside the campaign DOB state', function () {
    $user = shortDobUser(['onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_PERSONAL]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $m = new ReflectionMethod($director, 'maybeConfirmShortDob');
    $m->setAccessible(true);
    $events = drainDobEvents($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_BASE_PERSONAL,
        ['date_of_birth' => '1982-02-19'],
        '19/02/82'
    ));

    expect($events)->toBe([]);
});

it('confirms a short DOB captured via the parking fast-path too', function () {
    // The fact extractor parses "19/02/82" deterministically and parks it;
    // hydrateFromParking then applies it synchronously — that path must ask
    // the same confirm the grouped-extract path does.
    $user = shortDobUser(['date_of_birth' => null]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $events = drainDobEvents($director->handleUserMessage($user, $conversation, '19/02/82'));

    $qr = collect($events)->firstWhere('type', 'quick_replies');
    expect($qr)->not->toBeNull()
        ->and($qr['prompt_text'])->toContain('19th February 1982');

    $user->refresh();
    expect($user->date_of_birth?->format('Y-m-d'))->toBe('1982-02-19')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB) // held
        ->and($user->onboarding_fyn_context['pending_dob_confirm']['dob'] ?? null)->toBe('1982-02-19');
});

it('advances past the DOB state when the user confirms', function () {
    $user = shortDobUser([
        'onboarding_fyn_context' => ['pending_dob_confirm' => ['dob' => '1982-02-19']],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    drainDobEvents($director->handleUserMessage($user, $conversation, "Yes, that's right"));

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_dob_confirm'] ?? null)->toBeNull()
        ->and($user->date_of_birth?->format('Y-m-d'))->toBe('1982-02-19')
        // No pension ticked, single → the pensions questions and spouse section
        // are skipped; the flow advances into the expenditure section.
        ->and($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
});

it('clears the DOB and re-asks when the user rejects the confirm', function () {
    $user = shortDobUser([
        'onboarding_fyn_context' => ['pending_dob_confirm' => ['dob' => '1982-02-19']],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $events = drainDobEvents($director->handleUserMessage($user, $conversation, 'No, change it'));

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_dob_confirm'] ?? null)->toBeNull()
        ->and($user->date_of_birth)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB);

    // The DOB question is re-asked.
    $text = collect($events)->where('type', 'content')->pluck('text')->implode(' ');
    expect($text)->toContain('date of birth');
});

it('accepts a raw two-digit-year date in the capture handler backstop', function () {
    $user = User::factory()->create(['date_of_birth' => null]);
    $agent = app(CoordinatingAgent::class);

    $m = new ReflectionMethod($agent, 'handleCapturePersonalDetails');
    $m->setAccessible(true);
    $result = $m->invoke($agent, ['date_of_birth' => '19/02/82'], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $user->refresh();
    expect($user->date_of_birth?->format('Y-m-d'))->toBe('1982-02-19');
});
