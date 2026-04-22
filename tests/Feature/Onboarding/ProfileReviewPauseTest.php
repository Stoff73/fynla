<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M14 — profile-review pauses at family and expenditure checkpoints.
 *
 * These states exist so the user can review what Fyn captured before
 * moving on. The state machine declares them with layout='standard' so
 * the frontend shrinks the chat aside from 712px to 356px and un-blurs
 * the dashboard. The director signals the transition via
 * onboarding_layout_change SSE events.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('emits onboarding_layout_change=standard when entering STATE_PROFILE_REVIEW_FAMILY', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Test',
    ]);

    // Trigger a re-emit of the current state by invoking the continue action.
    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'continue') as $event) {
        $received[] = $event;
    }

    $layout = collect($received)->firstWhere('type', 'onboarding_layout_change');
    expect($layout)->not->toBeNull();
    expect($layout['mode'])->toBe('standard');
});

it('emits onboarding_layout_change=standard when entering STATE_PROFILE_REVIEW_EXPENDITURE', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PROFILE_REVIEW_EXPENDITURE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Test',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'continue') as $event) {
        $received[] = $event;
    }

    $layout = collect($received)->firstWhere('type', 'onboarding_layout_change');
    expect($layout)->not->toBeNull();
    expect($layout['mode'])->toBe('standard');
});

it('emits onboarding_layout_change=wide for capture states (not pause states)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Test',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'continue') as $event) {
        $received[] = $event;
    }

    $layout = collect($received)->firstWhere('type', 'onboarding_layout_change');
    expect($layout['mode'])->toBe('wide');
});
