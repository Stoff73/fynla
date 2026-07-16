<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\ProposedProcedureAmendment;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\Loop\FynLoop;
use App\Services\AI\Loop\SessionMode;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CoALA Phase 6 Component B — cycle-cap exhaustion triggers a closing planner
 * consult that may propose a procedure amendment (FR-M Phase 6).
 *
 * When a turn exhausts its cycle cap via repeated learn no-ops, FynLoop gives the
 * planner one final, failure-framed consult. If the planner responds with
 * learn store=procedural, the amendment is staged (status='pending'). The whole
 * path is flag-gated (fyn.learning_enabled) — disabled means no extra consult,
 * behaviour byte-identical to the pre-Phase-6 loop.
 *
 * Cap mechanics used in tests:
 *   config(['fyn.cycle_cap.advice' => 2])  → the for-loop runs exactly 2 cycles.
 *   Two scripted learn turns → each continue 2 and consume the cap.
 *   Post-loop code runs → closing consult is made (one more toolTurn in the harness).
 *
 * Disabled-flag case: flag check is BEFORE plan(), so no extra scripted turn is
 * needed — the closing consult is skipped entirely.
 */
beforeEach(function () {
    TierConfiguration::updateOrCreate(['tier' => 'free'], tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
});

function failureContextUser(): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Failure context test',
        'message_count' => 0,
    ]);

    return [$user, $conversation];
}

it('proposes a procedure amendment when a turn exhausts the cycle cap', function () {
    config(['fyn.learning_enabled' => true, 'fyn.cycle_cap.advice' => 2]);
    [$user, $conversation] = failureContextUser();

    // Script the harness:
    //   Turn 1 (cycle 1): learn episodic → continue 2 (consumes cycle 1)
    //   Turn 2 (cycle 2): learn episodic → continue 2 (consumes cycle 2, cap hit, for-loop ends)
    //   Turn 3 (closing consult): the extra plan() call in post-loop code → learn store=procedural
    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'episodic',
            'payload' => ['summary' => 'cycle 1 learn'],
        ])
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'episodic',
            'payload' => ['summary' => 'cycle 2 learn'],
        ])
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'procedural',
            'payload' => [
                'procedure_id' => 'recommendation-routing',
                'problem_observed' => 'turn exhausted cycle cap without answering',
                'proposed_fix' => 'add a guard that detects repeated same-action cycles',
                'rationale' => 'three consecutive learn no-ops indicate the rubric is mis-applied',
                'failure_type' => 'reasoning_loop_exhaustion',
            ],
        ])
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'test message', null, null),
        preserve_keys: false,
    );

    expect(
        ProposedProcedureAmendment::where('procedure_id', 'recommendation-routing')
            ->where('status', 'pending')
            ->where('conversation_id', $conversation->id)
            ->count()
    )->toBe(1);
});

it('does not consult on failure when learning is disabled', function () {
    config(['fyn.learning_enabled' => false, 'fyn.cycle_cap.advice' => 2]);
    [$user, $conversation] = failureContextUser();

    // With learning disabled, the closing consult is skipped entirely (flag check
    // is BEFORE plan()), so only the 2 cap-exhausting learn turns are scripted.
    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'episodic',
            'payload' => ['summary' => 'cycle 1 learn'],
        ])
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'episodic',
            'payload' => ['summary' => 'cycle 2 learn'],
        ])
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'test message', null, null),
        preserve_keys: false,
    );

    expect(ProposedProcedureAmendment::count())->toBe(0);
});
