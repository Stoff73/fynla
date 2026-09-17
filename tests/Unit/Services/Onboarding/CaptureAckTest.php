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

    // Was STATE_CAMPAIGN_DOB, which has since been given the personal ack — so
    // this asserted null against a state that now acks, and sat red.
    it('still returns null for states without a deterministic ack', function () {
        $user = User::factory()->create();

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_ADD_MORE))->toBeNull();
    });
});

/**
 * The personal ack used to glue two fragments that each carried their own
 * leading " and ", so a partial capture produced a sentence Fyn said out loud:
 * "Thanks — I've noted you're and single." (CSJ 2026-09-17).
 */
describe('buildCaptureAck — personal details', function () {
    it('reads correctly when only the marital status was captured', function () {
        $user = User::factory()->create(['date_of_birth' => null, 'marital_status' => 'single']);

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_CAMPAIGN_DOB))
            ->toBe("Thanks — I've noted you're single.");
    });

    it('reads correctly when only the date of birth was captured', function () {
        $user = User::factory()->create(['date_of_birth' => '1985-04-12', 'marital_status' => null]);

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_BASE_PERSONAL))
            ->toBe("Thanks — I've noted you're born on 12 April 1985.");
    });

    it('joins both when the whole form was captured', function () {
        $user = User::factory()->create(['date_of_birth' => '1985-04-12', 'marital_status' => 'married']);

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_BASE_PERSONAL))
            ->toBe("Thanks — I've noted you're born on 12 April 1985 and married.");
    });

    it('claims nothing about the user when neither field was captured', function () {
        $user = User::factory()->create(['date_of_birth' => null, 'marital_status' => null]);

        expect(invokeCaptureAck($user, OnboardingStateMachine::STATE_BASE_PERSONAL))
            ->toBe("Thanks — I've noted that.");
    });
});
