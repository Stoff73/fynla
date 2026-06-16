<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\ProposedProcedureAmendment;
use App\Models\ProposedSemanticFact;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\Loop\FynLoop;
use App\Services\AI\Loop\SessionMode;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CoALA Phase 6 Component B — queued, never-auto-applied procedure-amendment proposals.
 *
 * These tests drive FynLoop::run() through the real loop using the same harness as
 * FynLoopPlannerTest: FynStreamHarness scripts the Anthropic planner calls + reasoner
 * turns without a network call, so the full dispatch path is exercised.
 *
 * The staged models (ProposedSemanticFact / ProposedProcedureAmendment) must arrive with
 * status='pending' and must never be promoted or written to the procedural corpus.
 */
beforeEach(function () {
    TierConfiguration::create(tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
});

function learnDispatchUser(): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Learn dispatch test',
        'message_count' => 0,
    ]);

    return [$user, $conversation];
}

it('stages a ProposedProcedureAmendment when the planner emits learn store=procedural', function () {
    config(['fyn.learning_enabled' => true]);
    [$user, $conversation] = learnDispatchUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'procedural',
            'payload' => [
                'procedure_id' => 'recommendation-routing',
                'problem_observed' => 'looped on same action without progress',
                'proposed_fix' => 'add a guard that detects repeated same-action cycles',
                'rationale' => 'three consecutive identical retrieve actions suggest the rubric is mis-applied',
                'failure_type' => 'reasoning_loop_exhaustion',
            ],
        ])
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn('I have noted a potential improvement.')
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'test message', null, null),
        preserve_keys: false,
    );

    expect(ProposedProcedureAmendment::where('procedure_id', 'recommendation-routing')
        ->where('status', 'pending')
        ->where('conversation_id', $conversation->id)
        ->count()
    )->toBe(1);
});

it('stages with correct payload fields when procedure amendment is created', function () {
    config(['fyn.learning_enabled' => true]);
    [$user, $conversation] = learnDispatchUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'procedural',
            'payload' => [
                'procedure_id' => 'handoff-routing',
                'problem_observed' => 'capture deflected when it should have proceeded',
                'proposed_fix' => 'check entity_types before classify',
                'rationale' => 'empirical from eval run',
                'failure_type' => 'classification_error',
            ],
        ])
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'test message', null, null),
        preserve_keys: false,
    );

    $row = ProposedProcedureAmendment::where('procedure_id', 'handoff-routing')->first();

    expect($row)->not->toBeNull()
        ->and($row->problem_observed)->toBe('capture deflected when it should have proceeded')
        ->and($row->proposed_fix)->toBe('check entity_types before classify')
        ->and($row->rationale)->toBe('empirical from eval run')
        ->and($row->failure_type)->toBe('classification_error')
        ->and($row->status)->toBe('pending')
        ->and($row->reviewed_at)->toBeNull()
        ->and($row->reviewed_by)->toBeNull();
});

it('does NOT stage a ProposedProcedureAmendment when learning_enabled is false', function () {
    config(['fyn.learning_enabled' => false]);
    [$user, $conversation] = learnDispatchUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'procedural',
            'payload' => [
                'procedure_id' => 'recommendation-routing',
                'problem_observed' => 'some issue',
                'proposed_fix' => 'some fix',
                'rationale' => 'some rationale',
                'failure_type' => 'reasoning_loop_exhaustion',
            ],
        ])
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'test message', null, null),
        preserve_keys: false,
    );

    expect(ProposedProcedureAmendment::count())->toBe(0);
});

it('stages a ProposedSemanticFact when the planner emits learn store=semantic', function () {
    config(['fyn.learning_enabled' => true]);
    [$user, $conversation] = learnDispatchUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'semantic',
            'payload' => [
                'fact_id' => 'retires-2041',
                'title' => 'Plans to retire in 2041',
                'body' => 'User stated they intend to retire in 2041.',
            ],
        ])
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'I plan to retire in 2041', null, null),
        preserve_keys: false,
    );

    expect(ProposedSemanticFact::where('user_id', $user->id)
        ->where('fact_id', 'retires-2041')
        ->where('status', 'pending')
        ->count()
    )->toBe(1);
});

it('does NOT stage a ProposedSemanticFact when learning_enabled is false', function () {
    config(['fyn.learning_enabled' => false]);
    [$user, $conversation] = learnDispatchUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', [
            'action_type' => 'learn',
            'store' => 'semantic',
            'payload' => [
                'fact_id' => 'retires-2041',
                'title' => 'Plans to retire in 2041',
                'body' => 'User stated they intend to retire in 2041.',
            ],
        ])
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'I plan to retire in 2041', null, null),
        preserve_keys: false,
    );

    expect(ProposedSemanticFact::where('user_id', $user->id)->count())->toBe(0);
});
