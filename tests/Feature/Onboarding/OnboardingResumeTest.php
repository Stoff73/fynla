<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M20 — resume flow + welcome-back greeting.
 *
 * When a user lands on a conversation they abandoned mid-onboarding,
 * the client sends POST /action with action=resume. The director must:
 *   1. Emit a welcome-back greeting naming the saved step.
 *   2. Surface Continue / Something else action bubbles.
 *   3. Not advance state — the user decides next turn.
 * And for action=continue, it re-emits the current state's turn.
 * And for action=restart, it deletes prior messages and resets to path_choice.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('emits a welcome-back greeting with Continue / Something else bubbles on resume', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $event) {
        $received[] = $event;
    }

    $quick = collect($received)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
    expect($quick['prompt_text'])->toContain('Welcome back');
    expect($quick['action_bubbles'] ?? false)->toBeTrue();

    $bubbleIds = array_column($quick['bubbles'] ?? [], 'id');
    expect($bubbleIds)->toBe(['continue', 'something_else']);

    // State not advanced — the user decides what to do next turn.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
});

it('persists the Continue / Something else bubbles on the welcome-back greeting', function () {
    // Regression: the greeting's bubbles rode only in the live SSE stream, so
    // any transcript-only render (/m dock remount, the native app) showed a
    // welcome-back with no way to respond. The persisted row must carry the
    // same metadata.bubbles + action_bubbles every other onboarding turn does.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $_) {
        // drain the generator
    }

    $greeting = $conversation->messages()
        ->where('metadata->is_resume_greeting', true)
        ->first();

    expect($greeting)->not->toBeNull();
    expect($greeting->metadata['action_bubbles'] ?? false)->toBeTrue();
    expect(array_column($greeting->metadata['bubbles'] ?? [], 'id'))
        ->toBe(['continue', 'something_else']);
});

it('keeps only the latest welcome-back greeting across repeated resumes', function () {
    // Regression: the web resume flow calls action=resume on every chat open.
    // Without pruning, each call persisted a new welcome-back, so the mobile
    // resume (which renders the full transcript verbatim) showed a repeated
    // "Welcome back" on startup. Resuming N times must leave exactly ONE.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $director = app(OnboardingChatDirector::class);
    foreach (range(1, 3) as $_) {
        foreach ($director->handleAction($user, $conversation, 'resume') as $__) {
            // drain the generator
        }
    }

    $greetings = $conversation->messages()
        ->where('metadata->is_resume_greeting', true)
        ->get();

    expect($greetings)->toHaveCount(1);
    expect($greetings->first()->content)->toContain('Welcome back');
});

it('re-emits the current state on continue', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
        'onboarding_completed' => false,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'continue') as $event) {
        $received[] = $event;
    }

    // path_choice is a quick-replies state — it must emit the same bubbles
    // it would on a fresh first turn.
    $quick = collect($received)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
});

/**
 * CSJ regression (csjones conversation 63): STATE_PATH_CHOICE.prompt_text
 * opens with the full "Hi {first_name}, I'm Fyn — welcome to Fynla"
 * introduction. Every re-emission of the state — resume Continue,
 * interruption re-emits, retry fallthroughs — was persisting that whole
 * introduction again, stacking multiple "welcome to Fynla" rows in one
 * conversation's transcript. The introduction must appear exactly once per
 * conversation; every subsequent emission uses reprompt_text instead.
 */
it('emits the full welcome introduction on the very first turn of a conversation', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->emitFirstTurn($user, $conversation) as $event) {
        $received[] = $event;
    }

    $quick = collect($received)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
    expect($quick['prompt_text'])->toContain('welcome to Fynla');
    expect(array_column($quick['bubbles'], 'id'))->toBe(['journey', 'focus', 'skip']);

    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect($persisted->content)->toContain('welcome to Fynla');
});

it('drops the welcome introduction on re-emission once an assistant message already exists', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $director = app(OnboardingChatDirector::class);

    // First-ever emission — the full introduction, as above.
    foreach ($director->emitFirstTurn($user, $conversation) as $_) {
        // drain
    }

    // Re-emission — a resume "Continue" replays the same current state.
    // The conversation now already has a persisted assistant message, so
    // the introduction sentence must not be repeated.
    $received = [];
    foreach ($director->handleAction($user, $conversation, 'continue') as $event) {
        $received[] = $event;
    }

    $quick = collect($received)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();
    expect($quick['prompt_text'])->not->toContain('welcome to Fynla');
    expect($quick['prompt_text'])->not->toContain("I'm Fyn");
    expect($quick['prompt_text'])->toContain('life-stage journey or pick a single module focus');
    // Bubbles are unchanged by the reprompt swap.
    expect(array_column($quick['bubbles'], 'id'))->toBe(['journey', 'focus', 'skip']);

    // The persisted row for the re-emission is the reprompt, not a second
    // copy of the introduction — exactly one "welcome to Fynla" row exists
    // across the whole conversation.
    $persisted = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect($persisted->content)->not->toContain('welcome to Fynla');
    expect(
        $conversation->messages()
            ->where('role', 'assistant')
            ->where('content', 'like', '%welcome to Fynla%')
            ->count()
    )->toBe(1);
});

it('deletes prior messages and resets to path_choice on restart', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS,
        'onboarding_fyn_selection' => 'protection',
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Seed a few prior messages that must be wiped.
    $conversation->messages()->createMany([
        ['role' => 'user', 'content' => 'earlier message 1'],
        ['role' => 'assistant', 'content' => 'earlier reply 1'],
    ]);

    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'restart') as $_) {
        // drain
    }

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    expect($user->onboarding_fyn_selection)->toBeNull();

    // Prior messages wiped.
    expect($conversation->messages()->where('role', 'user')->count())->toBe(0);
});

// ── Task 3 (structured turn intent): the resume prune matches the enum ─────

it('prunes an enum-stamped resume greeting without the legacy flag', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_DEPENDANTS,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // A future greeting row carrying ONLY the enum (no legacy boolean) —
    // the prune must still remove it before persisting the fresh greeting.
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'Welcome back, Chris. Last time we were adding your dependants.',
        'metadata' => ['turn_intent' => 'resume_greeting'],
    ]);

    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'resume') as $_) {
        // drain
    }

    $greetings = $conversation->messages()
        ->where('role', 'assistant')
        ->where('content', 'like', 'Welcome back%')
        ->get();
    expect($greetings)->toHaveCount(1);
    expect($greetings->first()->metadata['is_resume_greeting'] ?? null)->toBeTrue();
});
