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
 * INV-2.2.5 (campaign extension) — entry-source → campaign mapping is
 * config-driven. AiChatController::startOnboarding looks the request
 * `from` value up in config('onboarding.campaign_map') BEFORE
 * config('onboarding.journey_map'). A match pre-selects the campaign
 * (path='campaign', selection=<id>) and lands the user at
 * STATE_BASE_PERSONAL. Adding a new campaign requires only a config
 * change — never a controller change.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function grantAiChatConsentForCampaignMapTest(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

it('exposes the savetax campaign-map entry by default', function () {
    $map = config('onboarding.campaign_map');

    expect($map)->toBeArray();
    expect($map)->toHaveKey('savetax');
    expect($map['savetax'])->toBe('savetax');
});

it('skips path_choice and lands the user at base_personal for a campaign `from` value', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'savetax']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('savetax');
    // SaveTax campaign is income-first: opens at base_work (income details),
    // with employment seeded from the funnel and DOB deferred to pensions.
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK);
});

it('falls through to STATE_PATH_CHOICE for an unknown `from` value', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'unknown-thing'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    expect($user->onboarding_fyn_path)->toBeNull();
});

it('checks campaign_map BEFORE journey_map so a campaign key never gets misread as a journey', function () {
    config()->set('onboarding.campaign_map', ['shared-key' => 'campaign-id']);
    config()->set('onboarding.journey_map', ['shared-key' => 'journey-id']);

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'shared-key'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('campaign-id');
});

it('does not interfere with existing journey_map behaviour', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'protection'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('journey');
    expect($user->onboarding_fyn_selection)->toBe('protection');
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});
