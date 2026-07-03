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
 * Characterisation test for the campaign_map shape change (Task A1 / G1).
 *
 * Pins two invariants that must hold before AND after the config value shape
 * changes from a bare string to an array carrying selection/entry/reentry:
 *
 *   1. POST /api/ai-chat/onboarding/start with from=savetax (fresh user,
 *      onboarding_completed=false) must set onboarding_fyn_path='campaign',
 *      onboarding_fyn_selection='savetax', and onboarding_fyn_step='base_work'.
 *
 *   2. Every configured entry value in the new map shape must be a recognised
 *      OnboardingStateMachine state id. (Vacuously true for the old string shape,
 *      substantive after Task A1 lands.)
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

it('sets campaign path, selection and step for from=savetax', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'savetax']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('savetax');
    expect($user->onboarding_fyn_step)->toBe('base_work');
});

it('has a valid state id for every configured campaign entry', function () {
    $campaignMap = config('onboarding.campaign_map', []);
    $validStates = array_keys(OnboardingStateMachine::states());

    expect($campaignMap)->toBeArray()->not->toBeEmpty();

    foreach ($campaignMap as $key => $value) {
        if (! is_array($value)) {
            continue;
        }
        expect($value)->toHaveKeys(['selection', 'entry', 'reentry']);
        expect($validStates)->toContain($value['entry']);
    }
});
