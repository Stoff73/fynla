<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task A4 — campaign re-entry gate on POST /api/ai-chat/onboarding/start.
 *
 * Pins four behaviours:
 *   1. Completed user + no `from`           → 409 already_completed  (preserved)
 *   2. Completed user + from=savetax        → 409 already_completed  (reentry=false, preserved)
 *   3. Completed user + from=pensioncheck   → 200 SSE + stamps       (reentry=true, new)
 *   4. Completed re-entry user mid-campaign → 200 SSE resume event   (no second conversation)
 *
 * The `pensioncheck` campaign is synthetic here — it must NOT be added to
 * config/onboarding.php; a later task owns that config change.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();

    // Synthetic reentry-enabled campaign injected only for this test suite.
    config()->set('onboarding.campaign_map.pensioncheck', [
        'selection' => 'pensioncheck',
        'entry' => 'base_work',
        'reentry' => true,
    ]);
});

/**
 * Create a completed user (onboarding_completed=true, step=null, active_campaign=null)
 * with AI chat consent granted, applying any additional column overrides.
 */
function makeCompletedReentryUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        'active_campaign' => null,
    ], $overrides));

    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

describe('POST /api/ai-chat/onboarding/start — re-entry gate (Task A4)', function () {

    it('returns 409 for a completed user with no from= parameter', function () {
        $user = makeCompletedReentryUser();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/ai-chat/onboarding/start')
            ->assertStatus(409)
            ->assertJson(['success' => false, 'reason' => 'already_completed']);
    });

    it('returns 409 for a completed user when from=savetax (reentry=false)', function () {
        $user = makeCompletedReentryUser();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/ai-chat/onboarding/start', ['from' => 'savetax'])
            ->assertStatus(409)
            ->assertJson(['success' => false, 'reason' => 'already_completed']);
    });

    it('returns 200 SSE and stamps all fields for a completed user re-entering a reentry-enabled campaign', function () {
        $user = makeCompletedReentryUser();

        // Assert DB invariant: completed users always have step and active_campaign null.
        // (Today impossible to have a stale non-null step with null active_campaign,
        // but we pin the current reality here rather than assuming it.)
        expect($user->onboarding_fyn_step)->toBeNull();
        expect($user->active_campaign)->toBeNull();

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/ai-chat/onboarding/start', ['from' => 'pensioncheck']);

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

        $body = $response->streamedContent();
        expect($body)->toContain('"type":"conversation_created"');

        $user->refresh();

        // onboarding_completed must NOT be touched.
        expect($user->onboarding_completed)->toBeTrue();

        // Campaign stamps written by the re-entry path.
        expect($user->active_campaign)->toBe('pensioncheck');
        expect($user->onboarding_fyn_path)->toBe('campaign');
        expect($user->onboarding_fyn_selection)->toBe('pensioncheck');
        expect($user->onboarding_fyn_step)->toBe('base_work');

        // Conversation has both metadata flags.
        $conversation = AiConversation::where('user_id', $user->id)->latest('id')->first();
        expect($conversation)->not->toBeNull();
        expect($conversation->metadata['source'])->toBe('fyn_onboarding');
        expect($conversation->metadata['campaign'])->toBe('pensioncheck');
    });

    it('returns resume SSE event for a completed re-entry user who is already mid-campaign', function () {
        // User is completed and already stamped mid-campaign (e.g. from a prior re-entry).
        $user = makeCompletedReentryUser([
            'active_campaign' => 'pensioncheck',
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'pensioncheck',
            'onboarding_fyn_step' => 'base_work',
        ]);

        // Existing onboarding conversation from the prior re-entry start call.
        AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
            'metadata' => ['source' => 'fyn_onboarding', 'campaign' => 'pensioncheck'],
        ]);

        $countBefore = AiConversation::where('user_id', $user->id)->count();

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/ai-chat/onboarding/start', ['from' => 'pensioncheck']);

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

        $body = $response->streamedContent();
        expect($body)->toContain('"type":"resume"');
        expect($body)->toContain('"current_step":"base_work"');

        // The resume branch must not create a second conversation.
        expect(AiConversation::where('user_id', $user->id)->count())->toBe($countBefore);
    });
});
