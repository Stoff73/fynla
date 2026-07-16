<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\Goal;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\TaxConfigService;
use Tests\Support\Fyn\FynStreamHarness;

beforeEach(function () {
    // The real chat loop reads the daily token backstop from the tier store
    // (HasAiGuardrails); a factory user resolves to the free tier.
    TierConfiguration::updateOrCreate(['tier' => 'free'], tierConfigFixture('free'));

    // The chat path resolves TaxConfigService, whose store memoises the active
    // config and can latch null before the row exists. Re-create + forget the
    // singleton (the working pattern from CrossModuleIntegrationTest).
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
});

/*
 * CoALA Phase 5 item 4 — stream-mock harness proof.
 *
 * Drives HasAiChat's real tool-use loop with a SCRIPTED Anthropic stream
 * (binds a fake Anthropic\Client whose messages->createStream replays SDK
 * events). This is the harness the FynLoop extraction is TDD'd against, and it
 * retro-fits the e2e coverage gap the handover flagged for items 1-2 (the loop's
 * telemetry persistence + in-loop ground gate had unit coverage only).
 */

it('streams a scripted text-only assistant turn through the real chat loop', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Harness',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->textTurn('Your total net worth is £250,000.')
        ->bind();

    $events = iterator_to_array(
        app(CoordinatingAgent::class)->chat($user, $conversation, 'What is my net worth?'),
        preserve_keys: false,
    );

    $content = collect($events)->where('type', 'content')->pluck('text')->implode('');

    expect($content)->toContain('Your total net worth is £250,000.')
        ->and(collect($events)->contains(fn ($e) => ($e['type'] ?? null) === 'done'))->toBeTrue();

    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
    ]);
});

it('strips a write surface emitted in advice mode inside the loop and never executes it', function () {
    // Closes the e2e gap the handover flagged for item 2: the in-loop ground
    // gate (ActionDispatcher, wired at HasAiChat dispatch) only had unit
    // coverage. Here a write tool is scripted in the advice persona and must be
    // stripped — audited, never executed — before a final text turn.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Harness gate',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', ['provider' => 'Halifax', 'balance' => 5000])
        ->textTurn('I can’t add accounts here — head to the savings module to do that.')
        ->bind();

    iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride(
            $user,
            $conversation,
            'Add a Halifax savings account with £5,000',
            null,   // currentRoute
            null,   // systemPromptOverride
            null,   // allowedTools
            true,   // persistUserMessage
            null,   // toolsListOverride
            'advice', // personaOverride — the read-only state
        ),
        preserve_keys: false,
    );

    // The write surface was rejected before execution: no account created.
    $this->assertDatabaseMissing('savings_accounts', ['user_id' => $user->id]);

    // The blocked attempt is forensically recorded as stripped.
    $this->assertDatabaseHas('ai_audit_events', [
        'user_id' => $user->id,
        'tool_name' => 'create_savings_account',
        'status' => 'stripped',
    ]);
});

it('correlates a corrected capture retry to the failed call from the prior model iteration', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Capture retry harness',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->toolTurn('create_goal', [
            'name' => 'House deposit',
            'target_amount' => 50000,
            'priority' => 'high',
            'goal_type' => 'home_deposit',
        ], 'toolu_failed')
        ->toolTurn('create_goal', [
            'name' => 'House deposit',
            'target_amount' => 50000,
            'target_date' => '2030-01-01',
            'priority' => 'high',
            'goal_type' => 'home_deposit',
        ], 'toolu_retry')
        ->textTurn('Saved your house-deposit goal.')
        ->bind();

    $events = iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride(
            $user,
            $conversation,
            'Add my £50,000 house-deposit goal for 2030',
            null,
            null,
            ['create_goal'],
            true,
            null,
            'data_capture',
        ),
        preserve_keys: false,
    );

    $results = collect($events)->where('type', 'capture_write_result')->values();

    expect($results)->toHaveCount(2)
        ->and($results[0]['landed'])->toBeFalse()
        ->and($results[0]['tool_call_id'])->toBe('toolu_failed')
        ->and($results[0]['model_iteration'])->toBe(1)
        ->and($results[0]['retry_of_tool_call_id'])->toBeNull()
        ->and($results[1]['landed'])->toBeTrue()
        ->and($results[1]['tool_call_id'])->toBe('toolu_retry')
        ->and($results[1]['model_iteration'])->toBe(2)
        ->and($results[1]['retry_of_tool_call_id'])->toBe('toolu_failed');
});

it('forces one in-turn retry when a verify edit omits a requested field', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
    ]);
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'goal_name' => 'House deposit',
        'target_amount' => 50000,
        'target_date' => '2030-01-01',
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Verify edit retry harness',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->toolTurn('update_record', [
            'entity_type' => 'goal',
            'entity_id' => $goal->id,
            'fields' => [
                'goal_name' => 'First home deposit',
                'target_amount' => 60000,
            ],
        ], 'toolu_incomplete')
        ->textTurn('I could not apply that change. Please tell me the value again.')
        ->toolTurn('update_record', [
            'entity_type' => 'goal',
            'entity_id' => $goal->id,
            'fields' => [
                'goal_name' => 'First home deposit',
                'target_amount' => 60000,
                'target_date' => '2031-01-01',
            ],
        ], 'toolu_complete')
        ->textTurn('Updated your Barclays Cash ISA.')
        ->bind();

    $agent = app(CoordinatingAgent::class);
    $agent->setVerifyEditScope([
        'tools' => ['update_record'],
        'records' => ['goal' => [$goal->id]],
        'profile_sections' => [],
        'record_fields' => [
            'goal' => [
                'goal_name',
                'target_amount',
                'target_date',
            ],
        ],
        'profile_fields' => [],
        'tool_fields' => [],
    ]);

    $events = iterator_to_array(
        $agent->chatWithPromptOverride(
            $user,
            $conversation,
            'Rename my goal to First home deposit, set the target to £60,000, and the target date to 1 January 2031.',
            null,
            null,
            ['update_record'],
            true,
            null,
            'data_capture',
        ),
        preserve_keys: false,
    );
    $agent->setVerifyEditScope(null);
    $results = collect($events)->where('type', 'capture_write_result')->values();

    expect($results)->toHaveCount(2)
        ->and($results[0]['landed'])->toBeFalse()
        ->and($results[1]['landed'])->toBeTrue()
        ->and($results[1]['retry_of_tool_call_id'])->toBe('toolu_incomplete')
        ->and($goal->fresh()->goal_name)->toBe('First home deposit')
        ->and((float) $goal->fresh()->target_amount)->toBe(60000.0)
        ->and($goal->fresh()->target_date->toDateString())->toBe('2031-01-01');
});

it('leaves a selective retry uncorrelated when two prior same-tool failures are ambiguous', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Ambiguous capture retry harness',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->toolBatchTurn([
            [
                'tool' => 'create_goal',
                'input' => [
                    'name' => 'House deposit',
                    'target_amount' => 50000,
                    'priority' => 'high',
                    'goal_type' => 'home_deposit',
                ],
                'id' => 'toolu_house_failed',
            ],
            [
                'tool' => 'create_goal',
                'input' => [
                    'name' => 'Emergency fund',
                    'target_amount' => 12000,
                    'priority' => 'high',
                    'goal_type' => 'emergency_fund',
                ],
                'id' => 'toolu_emergency_failed',
            ],
        ])
        ->toolTurn('create_goal', [
            'name' => 'Emergency fund',
            'target_amount' => 12000,
            'target_date' => '2030-01-01',
            'priority' => 'high',
            'goal_type' => 'emergency_fund',
        ], 'toolu_selective_retry')
        ->textTurn('Saved your emergency-fund goal.')
        ->bind();

    $events = iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride(
            $user,
            $conversation,
            'Add my house-deposit and emergency-fund goals',
            null,
            null,
            ['create_goal'],
            true,
            null,
            'data_capture',
        ),
        preserve_keys: false,
    );

    $results = collect($events)->where('type', 'capture_write_result')->values();

    expect($results)->toHaveCount(3)
        ->and($results[0]['landed'])->toBeFalse()
        ->and($results[1]['landed'])->toBeFalse()
        ->and($results[2]['landed'])->toBeTrue()
        ->and($results[2]['model_iteration'])->toBe(2)
        ->and($results[2]['retry_of_tool_call_id'])->toBeNull();
});

it('correlates a complete same-tool retry batch to all prior failures', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Complete capture retry batch harness',
        'message_count' => 0,
    ]);

    $invalidGoals = [
        [
            'tool' => 'create_goal',
            'input' => [
                'name' => 'House deposit',
                'target_amount' => 50000,
                'priority' => 'high',
                'goal_type' => 'home_deposit',
            ],
            'id' => 'toolu_house_failed',
        ],
        [
            'tool' => 'create_goal',
            'input' => [
                'name' => 'Emergency fund',
                'target_amount' => 12000,
                'priority' => 'high',
                'goal_type' => 'emergency_fund',
            ],
            'id' => 'toolu_emergency_failed',
        ],
    ];
    $correctedGoals = [
        [
            'tool' => 'create_goal',
            'input' => array_merge($invalidGoals[0]['input'], ['target_date' => '2030-01-01']),
            'id' => 'toolu_house_retry',
        ],
        [
            'tool' => 'create_goal',
            'input' => array_merge($invalidGoals[1]['input'], ['target_date' => '2030-01-01']),
            'id' => 'toolu_emergency_retry',
        ],
    ];

    FynStreamHarness::fake()
        ->toolBatchTurn($invalidGoals)
        ->toolBatchTurn($correctedGoals)
        ->textTurn('Saved both goals.')
        ->bind();

    $events = iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride(
            $user,
            $conversation,
            'Add my house-deposit and emergency-fund goals',
            null,
            null,
            ['create_goal'],
            true,
            null,
            'data_capture',
        ),
        preserve_keys: false,
    );

    $results = collect($events)->where('type', 'capture_write_result')->values();

    expect($results)->toHaveCount(4)
        ->and($results[2]['landed'])->toBeTrue()
        ->and($results[2]['retry_of_tool_call_id'])->toBe('toolu_house_failed')
        ->and($results[3]['landed'])->toBeTrue()
        ->and($results[3]['retry_of_tool_call_id'])->toBe('toolu_emergency_failed');
});
