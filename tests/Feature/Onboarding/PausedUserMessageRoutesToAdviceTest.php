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
})->skip('MB-23: a paused user typing into the onboarding conversation is routed to the director, which has no step. Unskip with the fix.');
