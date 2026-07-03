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

    // Synthetic reentry-enabled campaign injected only for this test suite.
    // Must NOT appear in config/onboarding.php — a later task owns that entry.
    config()->set('onboarding.campaign_map.pensioncheck', [
        'selection' => 'pensioncheck',
        'entry' => 'base_work',
        'reentry' => true,
    ]);
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

/**
 * Task A6 — funnel_answers.campaign fallback (G2).
 *
 * When the user arrives at /api/ai-chat/onboarding/start without a `from=`
 * parameter but carries durable funnel_answers stamped by the savetax funnel,
 * the controller must key the campaign off funnel_answers['campaign'] rather
 * than hard-coding 'savetax'. Legacy rows that predate the stamp default to
 * 'savetax'.
 */
it('routes to the campaign named in funnel_answers.campaign when no from= is supplied', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        'funnel_answers' => ['campaign' => 'pensioncheck', 'employment' => 'full-time'],
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $token = $user->createToken('test')->plainTextToken;

    // No `from=` — the controller must read funnel_answers['campaign'] instead.
    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('pensioncheck');
    expect($user->onboarding_fyn_step)->toBe('base_work');
});

it('falls back to savetax when funnel_answers carries no campaign key (legacy row)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        // Pre-stamp funnel_answers: has employment/income but no campaign key.
        'funnel_answers' => ['employment' => 'full-time', 'income' => 'upto_50270'],
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('savetax');
    expect($user->onboarding_fyn_step)->toBe('base_work');
});

it('has a valid state id for every configured campaign entry', function () {
    $campaignMap = config('onboarding.campaign_map', []);
    // states() is the authoritative in-code state table (inCodeStates() is private).
    $validStates = array_keys(OnboardingStateMachine::states());

    expect($campaignMap)->toBeArray()->not->toBeEmpty();

    foreach ($campaignMap as $key => $value) {
        expect($value)->toBeArray("campaign_map entry '{$key}' must be array-shaped, not a bare string");
        expect($value)->toHaveKeys(['selection', 'entry', 'reentry']);
        expect($validStates)->toContain($value['entry']);
    }
});
