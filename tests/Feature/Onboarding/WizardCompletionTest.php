<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Audit findings #9/#10 — completing the form-wizard onboarding must flip
 * users.onboarding_completed AND null users.onboarding_fyn_step, exactly like
 * the OnboardingChatDirector completion chain does for the chat-led flow.
 * Otherwise the AiChatController dispatch predicate keeps treating the user
 * as mid-onboarding, and web vs /m can diverge on which Fyn they get.
 */
it('marks onboarding complete and clears the fyn step on POST /onboarding/complete', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'active_campaign' => 'savetax',
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => 'path_choice',
        'onboarding_fyn_context' => [
            'paused_at_step' => 'path_choice',
            'retained_key' => 'retained value',
        ],
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/onboarding/complete');

    $response->assertOk();
    $user->refresh();
    expect($user->onboarding_completed)->toBeTrue()
        ->and($user->onboarding_completed_at)->not->toBeNull()
        ->and($user->active_campaign)->toBeNull()
        ->and($user->onboarding_fyn_path)->toBeNull()
        ->and($user->onboarding_fyn_selection)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBeNull()
        ->and($user->onboarding_fyn_context)->toBe(['retained_key' => 'retained value']);
});

it('marks onboarding complete and clears the fyn step on POST /onboarding/complete-quick', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'path_choice',
        'onboarding_fyn_context' => [
            'paused_at_step' => 'path_choice',
            'retained_key' => 'retained value',
        ],
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/onboarding/complete-quick');

    $response->assertOk();
    $user->refresh();
    expect($user->onboarding_completed)->toBeTrue()
        ->and($user->onboarding_mode)->toBe('quick')
        ->and($user->active_campaign)->toBeNull()
        ->and($user->onboarding_fyn_path)->toBeNull()
        ->and($user->onboarding_fyn_selection)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBeNull()
        ->and($user->onboarding_fyn_context)->toBe(['retained_key' => 'retained value']);
});

it('marks onboarding complete and clears the fyn step on POST /onboarding/skip-to-dashboard', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'active_campaign' => 'savetax',
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => 'path_choice',
        'onboarding_fyn_context' => [
            'paused_at_step' => 'path_choice',
            'retained_key' => 'retained value',
        ],
        'life_stage' => 'young_family',
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/onboarding/skip-to-dashboard');

    $response->assertOk();
    $user->refresh();
    expect($user->onboarding_completed)->toBeTrue()
        ->and($user->active_campaign)->toBeNull()
        ->and($user->onboarding_fyn_path)->toBeNull()
        ->and($user->onboarding_fyn_selection)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBeNull()
        ->and($user->onboarding_fyn_context)->toBe(['retained_key' => 'retained value']);
});
