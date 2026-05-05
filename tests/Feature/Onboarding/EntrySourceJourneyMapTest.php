<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.2.5 — entry-source → journey mapping is config-driven.
 *
 * AiChatController::startOnboarding looks the request `from` value
 * up in config('onboarding.journey_map'). A match pre-selects the
 * matching life-stage journey and lands the user at
 * STATE_BASE_PERSONAL. An unknown / missing `from` falls through
 * to STATE_PATH_CHOICE.
 *
 * Adding a new entry source must require only a config change.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function grantAiChatConsentForJourneyMapTest(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

it('exposes the four canonical journey-map entries by default', function () {
    $map = config('onboarding.journey_map');

    expect($map)->toBeArray();
    expect($map)->toMatchArray([
        'budgeting' => 'budgeting',
        'goals' => 'goals',
        'protection' => 'protection',
        'retirement' => 'retirement',
    ]);
});

it('skips path_choice and lands the user at base_personal for known `from` values', function (string $from, string $expectedSelection) {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_selection' => null,
    ]);
    grantAiChatConsentForJourneyMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => $from]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    expect($user->onboarding_fyn_path)->toBe('journey');
    expect($user->onboarding_fyn_selection)->toBe($expectedSelection);
})->with([
    'budgeting' => ['budgeting', 'budgeting'],
    'goals' => ['goals', 'goals'],
    'protection' => ['protection', 'protection'],
    'retirement' => ['retirement', 'retirement'],
]);

it('falls through to path_choice when `from` is unknown', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForJourneyMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'totally_unknown_source']);

    $response->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    expect($user->onboarding_fyn_path)->toBeNull();
    expect($user->onboarding_fyn_selection)->toBeNull();
});

it('falls through to path_choice when `from` is missing', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForJourneyMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start');

    $response->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    expect($user->onboarding_fyn_path)->toBeNull();
    expect($user->onboarding_fyn_selection)->toBeNull();
});

it('honours runtime-added journey-map entries (config-driven, no controller change)', function () {
    // The whole point of this invariant — adding a new entry source
    // must require only a config edit.
    config(['onboarding.journey_map' => array_merge(
        (array) config('onboarding.journey_map', []),
        ['estate' => 'estate']
    )]);

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForJourneyMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'estate'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
    expect($user->onboarding_fyn_selection)->toBe('estate');
});
