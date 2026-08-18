<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * CSJ 2026-08-18: a user who has logged in before must never be stuck at the
 * front door of onboarding.
 *
 * `path_choice` is a bubbles turn, and a bubbles turn answers anything it does
 * not recognise with "Sorry, I didn't catch that. Please pick one of the options
 * above." With only "Follow a journey" and "Pick a focus" on offer, a returning
 * user who wanted to ask a question — or add a single record — could not get
 * past it on any surface (seen live on /m, 2026-08-18).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('offers a way past the two onboarding paths', function (): void {
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PATH_CHOICE);

    $labels = array_column($state['bubbles'] ?? [], 'label');

    expect($labels)->toContain('Follow a journey')
        ->and($labels)->toContain('Pick a focus')
        // The front door needs a third exit.
        ->and($labels)->toContain('Something else');
});

it('routes that third option out of onboarding, not deeper into it', function (): void {
    expect(OnboardingStateMachine::nextFromPathChoice('Something else'))
        ->toBe(OnboardingStateMachine::STATE_FREE_CHAT);

    // The two real paths still go where they always did.
    expect(OnboardingStateMachine::nextFromPathChoice('Follow a journey'))
        ->toBe(OnboardingStateMachine::STATE_JOURNEY_SELECTION);
    expect(OnboardingStateMachine::nextFromPathChoice('Pick a focus'))
        ->toBe(OnboardingStateMachine::STATE_FOCUS_SELECTION);
});

it('matches the third option however the user says it', function (): void {
    foreach (['Something else', 'something else', 'skip'] as $said) {
        expect(OnboardingStateMachine::matchBubble(OnboardingStateMachine::STATE_PATH_CHOICE, $said))
            ->toBe('skip');
    }
});

it('pauses onboarding rather than marking it done', function (): void {
    // The distinction matters: a paused user routes to advice Fyn (canonical
    // 3-part dispatch predicate — completed false, step null), while a user
    // marked complete would have onboarding claimed for them that never
    // happened.
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);

    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_FREE_CHAT);

    expect($state)->not->toBeNull()
        ->and($state['turn_type'])->toBe('terminal')
        ->and($state['prompt_text'])->toContain('What would you like help with')
        ->and($user->onboarding_completed)->toBeFalse();
});

it('lets typed free text out of the front door', function (): void {
    // The bubble is the visible exit; typing is the one people actually use.
    // "let me just look around" used to get "Sorry, I didn't catch that. Please
    // pick one of the options above." on every attempt, forever.
    //
    // Note what this is NOT: a question ("how much can I put in an ISA?") is
    // claimed by the interruption handler, answered inline, and the walk
    // continues — which is better than leaving. Only a message nothing claims
    // takes the exit.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'front door',
        'message_count' => 0,
    ]);

    $director = app(OnboardingChatDirector::class);

    $events = [];
    foreach ($director->handleUserMessage($user, $conversation, 'let me just look around for now', '/dashboard') as $event) {
        $events[] = $event;
    }

    $user->refresh();

    // Paused, not completed — they have not onboarded.
    expect($user->onboarding_fyn_step)->toBeNull()
        ->and($user->onboarding_completed)->toBeFalse();

    // And they were not told to pick an option.
    $said = collect($events)->where('type', 'content')->pluck('text')->implode(' ');
    expect($said)->not->toContain("didn't catch that");
});

it('still takes the two real paths when they are chosen', function (): void {
    // The escape must not swallow the onboarding flow itself.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'front door',
        'message_count' => 0,
    ]);

    foreach (app(OnboardingChatDirector::class)
        ->handleUserMessage($user, $conversation, 'Follow a journey', '/dashboard') as $event) {
        // drain
    }

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_JOURNEY_SELECTION);
});
