<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\ContextualConversation\ConversationModeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Contract pinned by the app-map run of 2026-09-14 (MB-23).
 *
 * "Something else" and free text at the front door pause onboarding by nulling
 * users.onboarding_fyn_step; the code comments promise the next message routes
 * to advice Fyn. It does not: ConversationModeResolver::routesToOnboarding()
 * returns true for any conversation whose metadata.source is fyn_onboarding,
 * before it looks at the step, so the paused user's message reaches
 * OnboardingChatDirector::handleUserMessage() with no step and gets
 * "Onboarding state lost". Skipped until the fix lands; it documents the
 * behaviour a fix must produce.
 */
it('routes a paused user typing into the onboarding conversation to advice Fyn', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'protection',
        'onboarding_fyn_context' => ['paused_at_step' => 'base_personal'],
        'active_campaign' => null,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    expect(app(ConversationModeResolver::class)->routesToOnboarding($conversation, $user))->toBeFalse();
});

// 2026-09-19 (csjones, conversation 245): a completed user's message into the
// finished onboarding conversation reached the director with no step and the
// web panel hung. Advice answers it.
it('routes a completed user typing into their finished onboarding conversation to advice Fyn', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'active_campaign' => null,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    expect(app(ConversationModeResolver::class)->routesToOnboarding($conversation, $user))->toBeFalse();
});

it('still routes a user mid-walk into the director', function (): void {
    $user = User::factory()->create(['onboarding_completed' => false, 'onboarding_fyn_step' => 'campaign_bank_accounts', 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax']);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);

    expect(app(ConversationModeResolver::class)->routesToOnboarding($conversation, $user))->toBeTrue();
});
