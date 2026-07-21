<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * buildCaptureAck — the deterministic per-state acknowledgement emitted after
 * a grouped_extract capture so transitions don't feel abrupt. The Gift Aid
 * capture is the one campaign state with neither a delegated-LLM ack nor an
 * immediate strategy turn after it, so it gets its own entry.
 *
 * Reflection on the private method follows the repo's established pattern
 * (see AckDedupeTest / OffScriptAnswerFilterTest).
 */
function invokeCaptureAck(User $user, string $stateId): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildCaptureAck');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, $stateId, []);
}

describe('buildCaptureAck — charitable giving', function () {
    it('acks a recorded Gift Aid amount', function () {
        $user = User::factory()->create(['annual_charitable_donations' => 1200]);

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_CAMPAIGN_CHARITABLE_GIVING))
            ->toBe('Recorded — around £1,200 a year through Gift Aid.');
    });

    it('acks a "none" answer without inventing a figure', function () {
        $user = User::factory()->create(['annual_charitable_donations' => 0]);

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_CAMPAIGN_CHARITABLE_GIVING))
            ->toBe('Got it — no Gift Aid donations.');
    });

    it('still returns null for states without a deterministic ack', function () {
        $user = User::factory()->create();

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_CAMPAIGN_DOB))->toBeNull();
    });
});
