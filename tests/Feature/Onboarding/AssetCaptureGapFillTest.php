<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B-1 — deterministic gap-fill for multi-entity asset_capture turns.
 *
 * The xAI/Anthropic models still drop a tool call on multi-entity messages
 * ("Aviva life £300k AND Vitality critical illness £100k" emits only one
 * create_protection_policy despite prompt instructions). AssetCaptureEntity-
 * Extractor parses the original user message server-side and the director
 * yields synthetic fill_form events for the entities the LLM missed. This
 * test pins that behaviour — without it, the fix could silently regress and
 * the top-priority bug would come back.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('synthesises fill_form events for entities the LLM dropped on protection', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Gap',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
        'onboarding_fyn_selection' => 'protection',
        'onboarding_fyn_context' => ['visited_focuses' => ['protection']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Simulate the real-world bug: user sends a two-policy message, LLM
    // only emits ONE fill_form (the Aviva life) — Vitality CI is dropped
    // entirely. Nothing else in the delegated stream references Vitality.
    $llmDroppedSecondEntity = [
        ['type' => 'tool_use', 'tool' => 'create_protection_policy', 'status' => 'running'],
        [
            'type' => 'fill_form',
            'entity_type' => 'protection_policy',
            'route' => '/protection',
            'fields' => [
                'policyType' => 'life',
                'provider' => 'Aviva',
                'coverage_amount' => 300000.0,
                'premium_frequency' => 'monthly',
            ],
            'mode' => 'create',
            'entity_id' => null,
        ],
        ['type' => 'tool_use', 'tool' => 'create_protection_policy', 'status' => 'complete'],
        ['type' => 'content', 'text' => "Got it — both recorded."],
        ['type' => 'done', 'message_id' => 1],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($llmDroppedSecondEntity) {
            foreach ($llmDroppedSecondEntity as $event) {
                yield $event;
            }
        });

    // executeTool is called by the gap-fill path. It must receive the
    // extractor-generated input for Vitality CI, not the Aviva life (already
    // emitted by the LLM). Return the standard fill_form-shaped handler
    // response so the director can synthesise the downstream fill_form event.
    $mock->shouldReceive('executeTool')
        ->once()
        ->with(
            'create_protection_policy',
            Mockery::on(function (array $input): bool {
                return ($input['policy_type'] ?? null) === 'standalone_ci'
                    && ($input['provider'] ?? null) === 'Vitality'
                    && (float) ($input['sum_assured'] ?? 0) === 100000.0;
            }),
            Mockery::type(User::class)
        )
        ->andReturn([
            'action' => 'fill_form',
            'entity_type' => 'protection_policy',
            'route' => '/protection',
            'fields' => [
                'policyType' => 'criticalIllness',
                'provider' => 'Vitality',
                'coverage_amount' => 100000.0,
                'premium_frequency' => 'monthly',
            ],
            'mode' => 'create',
        ]);

    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage(
        $user,
        $conversation,
        'I have Aviva life insurance £300,000 and Vitality critical illness £100,000'
    ) as $event) {
        $received[] = $event;
    }

    // TWO fill_form events must reach the downstream queue — one from the
    // LLM, one synthesised by the gap-fill. The frontend sees both and
    // runs them through aiFormFill in order.
    $fillForms = array_values(array_filter(
        $received,
        fn (array $e): bool => ($e['type'] ?? null) === 'fill_form'
    ));
    expect($fillForms)->toHaveCount(2);

    // Provider names must preserve order — Aviva life first (LLM), then
    // Vitality CI (gap-fill).
    expect($fillForms[0]['fields']['provider'])->toBe('Aviva');
    expect($fillForms[0]['fields']['policyType'])->toBe('life');
    expect($fillForms[1]['fields']['provider'])->toBe('Vitality');
    expect($fillForms[1]['fields']['policyType'])->toBe('criticalIllness');

    // State still advances to add_more — the gap-fill runs inside the same
    // asset_capture turn, it does not short-circuit the director's normal
    // post-delegation flow.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ADD_MORE);
});

it('does not synthesise duplicates when the LLM emitted every entity', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'NoGap',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
        'onboarding_fyn_selection' => 'protection',
        'onboarding_fyn_context' => ['visited_focuses' => ['protection']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Healthy path — LLM emits both fill_form events correctly.
    $llmEmittedBoth = [
        ['type' => 'tool_use', 'tool' => 'create_protection_policy', 'status' => 'running'],
        [
            'type' => 'fill_form',
            'entity_type' => 'protection_policy',
            'route' => '/protection',
            'fields' => [
                'policyType' => 'life',
                'provider' => 'Aviva',
                'coverage_amount' => 300000.0,
            ],
            'mode' => 'create',
        ],
        ['type' => 'tool_use', 'tool' => 'create_protection_policy', 'status' => 'complete'],
        ['type' => 'tool_use', 'tool' => 'create_protection_policy', 'status' => 'running'],
        [
            'type' => 'fill_form',
            'entity_type' => 'protection_policy',
            'route' => '/protection',
            'fields' => [
                'policyType' => 'criticalIllness',
                'provider' => 'Vitality',
                'coverage_amount' => 100000.0,
            ],
            'mode' => 'create',
        ],
        ['type' => 'tool_use', 'tool' => 'create_protection_policy', 'status' => 'complete'],
        ['type' => 'content', 'text' => 'Both recorded.'],
        ['type' => 'done', 'message_id' => 2],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($llmEmittedBoth) {
            foreach ($llmEmittedBoth as $event) {
                yield $event;
            }
        });

    // CRITICAL: executeTool MUST NOT be called — the gap-fill path should
    // decide both entities were already covered by the LLM's emissions and
    // synthesize nothing. If this expectation fires, the gap-fill would be
    // creating duplicate DB records on every healthy turn.
    $mock->shouldNotReceive('executeTool');

    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage(
        $user,
        $conversation,
        'I have Aviva life insurance £300k and Vitality critical illness £100k'
    ) as $event) {
        $received[] = $event;
    }

    // Still exactly 2 fill_form events — the ones the LLM emitted, no more.
    $fillForms = array_values(array_filter(
        $received,
        fn (array $e): bool => ($e['type'] ?? null) === 'fill_form'
    ));
    expect($fillForms)->toHaveCount(2);
});

it('is a no-op when the user message contains no extractable entity', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Empty',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
        'onboarding_fyn_selection' => 'protection',
        'onboarding_fyn_context' => ['visited_focuses' => ['protection']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // User says "I don't have any" — the LLM emits no tool calls; the
    // extractor finds no entities; the gap-fill must emit nothing too.
    $llmEmptyTurn = [
        ['type' => 'content', 'text' => "Got it — nothing to add."],
        ['type' => 'done', 'message_id' => 3],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($llmEmptyTurn) {
            foreach ($llmEmptyTurn as $event) {
                yield $event;
            }
        });

    $mock->shouldNotReceive('executeTool');

    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage(
        $user,
        $conversation,
        "I don't have any protection policies"
    ) as $event) {
        $received[] = $event;
    }

    $fillForms = array_filter(
        $received,
        fn (array $e): bool => ($e['type'] ?? null) === 'fill_form'
    );
    expect($fillForms)->toBe([]);
});

it('skips gap-fill entirely for unsupported focuses (estate)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Estate',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
        'onboarding_fyn_selection' => 'estate',
        'onboarding_fyn_context' => ['visited_focuses' => ['estate']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    $llmEmittedOnce = [
        ['type' => 'tool_use', 'tool' => 'create_asset', 'status' => 'running'],
        [
            'type' => 'fill_form',
            'entity_type' => 'estate_asset',
            'route' => '/estate',
            'fields' => ['asset_name' => 'Vase', 'asset_type' => 'other', 'current_value' => 5000],
            'mode' => 'create',
        ],
        ['type' => 'tool_use', 'tool' => 'create_asset', 'status' => 'complete'],
        ['type' => 'done', 'message_id' => 4],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($llmEmittedOnce) {
            foreach ($llmEmittedOnce as $event) {
                yield $event;
            }
        });

    // Estate has too many sub-tool types (asset, liability, gift, trust,
    // chattel, business) for the simple gap-fill extractor to cover safely.
    // The director must short-circuit rather than risk mis-classifying.
    $mock->shouldNotReceive('executeTool');

    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage(
        $user,
        $conversation,
        'a Ming vase worth £5,000 and a £10,000 credit card debt'
    ) as $event) {
        $received[] = $event;
    }

    $fillForms = array_values(array_filter(
        $received,
        fn (array $e): bool => ($e['type'] ?? null) === 'fill_form'
    ));
    expect($fillForms)->toHaveCount(1); // Only the LLM's one, no gap-fill for estate.
});
