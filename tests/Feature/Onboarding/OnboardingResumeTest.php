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
