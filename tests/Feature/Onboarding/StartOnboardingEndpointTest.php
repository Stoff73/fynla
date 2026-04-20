<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PRD FR-M9 + FR-M11 — POST /api/ai-chat/onboarding/start
 * endpoint short-circuit branches.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md
 *
 *   200 (SSE) — fresh, non-preview user → conversation_created event
 *   401       — unauthenticated
 *   403       — preview user (controller check — requires FR-M9 middleware exclusion to be reached)
 *   409       — already-completed user
 *   503       — kill switch off
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('POST /api/ai-chat/onboarding/start', function () {
    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/ai-chat/onboarding/start')
            ->assertUnauthorized();
    });

    it('returns 403 for preview users with reason=preview_mode (FR-M9)', function () {
        $user = User::factory()->create(['is_preview_user' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/ai-chat/onboarding/start');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'preview_mode',
            ]);

        // FR-M9 guard — if the PreviewWriteInterceptor swallowed the POST
        // and returned its fake-success envelope, `preview_mode` would be
        // true at the top level. Here we want the CONTROLLER's 403, not
        // the middleware's 200.
        expect($response->json('preview_mode'))->toBeNull();
    });

    it('returns 409 when the user has already completed onboarding', function () {
        $user = User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/ai-chat/onboarding/start')
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'reason' => 'already_completed',
            ]);
    });

    it('returns 503 when the kill switch is off', function () {
        config(['onboarding.fyn_flow_enabled' => false]);

        $user = User::factory()->create(['is_preview_user' => false]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/ai-chat/onboarding/start')
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'reason' => 'disabled',
            ]);
    });

    it('streams SSE with conversation_created for a fresh non-preview user', function () {
        $user = User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => false,
            'onboarding_fyn_step' => null,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/ai-chat/onboarding/start');

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

        $body = $response->streamedContent();
        expect($body)->toContain('"type":"conversation_created"');

        $user->refresh();
        expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    });
});
