<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M15 — STATE_BASE_EMPLOYMENT_MORE multi-job loop.
 *
 * After the user captures their primary employment, Fyn offers a
 * "Yes, I have another job" bubble that loops back to base_employment
 * for a second capture. Choosing No advances to base_expenditure.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('is defined in the state machine as a bubbles state', function () {
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE);

    expect($state)->not->toBeNull();
    expect($state['turn_type'])->toBe('bubbles');

    $bubbleIds = array_column($state['bubbles'] ?? [], 'id');
    expect($bubbleIds)->toContain('yes');
    expect($bubbleIds)->toContain('no');
});

it('emits the add-another-job prompt when re-entered via continue', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE,
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

    $quick = collect($received)->firstWhere('type', 'quick_replies');
    expect($quick)->not->toBeNull();

    $ids = array_column($quick['bubbles'] ?? [], 'id');
    expect($ids)->toContain('yes');
    expect($ids)->toContain('no');
});
