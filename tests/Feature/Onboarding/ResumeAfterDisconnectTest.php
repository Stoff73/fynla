<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.2.4 — when a user with onboarding_completed=false +
 * onboarding_fyn_step != null + last ai_messages.created_at older
 * than 5 minutes returns to the conversation, the next session
 * emits a welcome-back quick_replies turn whose prompt_text contains
 * the OnboardingChatDirector's per-state label and whose bubbles are
 * the binary continue/something_else action_bubbles set.
 *
 * The 5-minute threshold itself is enforced client-side (the chat
 * frontend decides when to dispatch action=resume vs action=continue).
 * This test pins the director's CONTRACT: given a stale conversation
 * and action=resume, what does the director emit?
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function makeStaleOnboardingUser(string $stateId, ?string $selection = null): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'surname' => 'Jones',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => $stateId,
        'onboarding_fyn_selection' => $selection,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Seed a stale assistant message (> 5 minutes old) to model the
    // disconnect window. The director itself does not gate on this
    // timestamp, but the precondition makes the test honest about
    // the scenario shape per INV-2.2.4.
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'role' => 'assistant',
        'content' => 'mid-onboarding turn',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    return [$user, $conversation];
}

it('emits welcome-back quick_replies with continue/something_else action bubbles on resume after stale gap', function () {
    [$user, $conversation] = makeStaleOnboardingUser(
        OnboardingStateMachine::STATE_BASE_DEPENDANTS
    );

    $events = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $event) {
        $events[] = $event;
    }

    $quick = collect($events)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
    expect($quick['action_bubbles'] ?? false)->toBeTrue();
    expect($quick['prompt_text'])->toContain('Welcome back');
    expect($quick['prompt_text'])->toContain('Chris');

    $bubbles = $quick['bubbles'] ?? [];
    expect($bubbles)->toHaveCount(2);
    expect($bubbles[0])->toMatchArray(['id' => 'continue', 'label' => 'Continue']);
    expect($bubbles[1])->toMatchArray(['id' => 'something_else', 'label' => 'Something else']);

    // State must not advance — the user picks the next move.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
});

it('persists the welcome-back assistant message with the resume marker', function () {
    [$user, $conversation] = makeStaleOnboardingUser(
        OnboardingStateMachine::STATE_BASE_PERSONAL
    );

    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $_) {
        // drain
    }

    $resume = $conversation->messages()
        ->where('role', 'assistant')
        ->orderByDesc('id')
        ->first();

    expect($resume)->not->toBeNull();
    expect($resume->content)->toContain('Welcome back');
    expect($resume->metadata['is_resume_greeting'] ?? null)->toBeTrue();
    expect($resume->metadata['onboarding_step'] ?? null)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});

it('embeds the per-state label in the welcome-back greeting for every supported state', function (string $stateId, string $expectedFragment) {
    [$user, $conversation] = makeStaleOnboardingUser($stateId, 'protection');

    $events = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $event) {
        $events[] = $event;
    }

    $quick = collect($events)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
    expect($quick['prompt_text'])->toContain($expectedFragment);
})->with([
    'path_choice' => [OnboardingStateMachine::STATE_PATH_CHOICE, 'choosing how to get started'],
    'journey_selection' => [OnboardingStateMachine::STATE_JOURNEY_SELECTION, 'picking a life-stage journey'],
    'focus_selection' => [OnboardingStateMachine::STATE_FOCUS_SELECTION, 'picking a module to focus on first'],
    'base_personal' => [OnboardingStateMachine::STATE_BASE_PERSONAL, 'capturing your date of birth and marital status'],
    'base_spouse' => [OnboardingStateMachine::STATE_BASE_SPOUSE, "capturing your partner's details"],
    'base_dependants' => [OnboardingStateMachine::STATE_BASE_DEPENDANTS, 'noting whether you have dependants'],
    'base_dependants_detail' => [OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL, 'noting your dependants'],
    'base_employment' => [OnboardingStateMachine::STATE_BASE_EMPLOYMENT, 'noting your employment situation'],
    'base_work' => [OnboardingStateMachine::STATE_BASE_WORK, 'capturing your employer and role'],
    'base_retirement_date' => [OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE, 'noting when you retired'],
    'base_expenditure' => [OnboardingStateMachine::STATE_BASE_EXPENDITURE, 'noting your monthly expenditure'],
    'asset_capture' => [OnboardingStateMachine::STATE_ASSET_CAPTURE, 'mapping your protection records'],
    'add_more' => [OnboardingStateMachine::STATE_ADD_MORE, 'choosing whether to add another module'],
]);

it('falls back to the default mid-onboarding label for unknown states', function () {
    [$user, $conversation] = makeStaleOnboardingUser('an_unrecognised_state');

    $events = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $event) {
        $events[] = $event;
    }

    $quick = collect($events)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
    expect($quick['prompt_text'])->toContain('mid-onboarding');
});

it('emits an error event when resume is invoked with no saved step', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $events = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $event) {
        $events[] = $event;
    }

    expect(collect($events)->firstWhere('type', 'quick_replies'))->toBeNull();

    // The director's errorEvent helper returns a `content` event with the
    // error text — there is no separate `error` SSE type.
    $errorContent = collect($events)->firstWhere(fn ($e) => ($e['type'] ?? null) === 'content'
        && str_contains((string) ($e['text'] ?? ''), 'No onboarding'));
    expect($errorContent)->not->toBeNull();
});
