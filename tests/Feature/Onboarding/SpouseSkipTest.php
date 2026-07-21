<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\OnboardingProgress;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M16 — spouse skip link fires POST /action with action=skip and the
 * director advances from STATE_BASE_SPOUSE to STATE_BASE_DEPENDANTS,
 * recording the skip on onboarding_progress so resume can see it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('advances from base_spouse to base_dependants when skip is invoked', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_SPOUSE,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'skip') as $event) {
        $received[] = $event;
    }

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);

    $advance = collect($received)->firstWhere('type', 'onboarding_advance');
    expect($advance)->not->toBeNull();
    expect($advance['from_step'])->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);
    expect($advance['to_step'])->toBe(OnboardingStateMachine::STATE_BASE_DEPENDANTS);
    expect($advance['skipped'] ?? false)->toBeTrue();

    // Skip is recorded on onboarding_progress so resume can show it.
    // Column is `step_name`, not `step`.
    $progress = OnboardingProgress::where('user_id', $user->id)
        ->where('step_name', OnboardingStateMachine::STATE_BASE_SPOUSE)
        ->first();
    expect($progress)->not->toBeNull();
    expect($progress->step_data['skipped'] ?? null)->toBeTrue();
});

it('refuses to skip a step that is not in the sanctioned skip-target list', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        // base_personal is NOT in the skip_targets whitelist.
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_PERSONAL,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $received = [];
    foreach (app(OnboardingChatDirector::class)->handleAction($user, $conversation, 'skip') as $event) {
        $received[] = $event;
    }

    // Step unchanged.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);

    // An error event was emitted, no advance.
    $advance = collect($received)->firstWhere('type', 'onboarding_advance');
    expect($advance)->toBeNull();
});
