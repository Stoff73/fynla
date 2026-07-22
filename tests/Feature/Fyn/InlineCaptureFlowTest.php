<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\ValueObjects\CaptureContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Two-Fyn collapse (Sprint 0.3): the old post-onboarding orchestrator + invoker
 * are gone. Inline data-capture now lives on OnboardingChatDirector::handleInlineCapture.
 *
 * This test pins the behaviour that survives the collapse:
 *   1. Onboarding layout / quick-reply events are stripped before reaching
 *      the frontend (handoff invisibility, INV-2.4.1 / INV-2.4.2).
 *   2. fill_form events pass through so the frontend can queue them.
 *   3. content / done events pass through unchanged.
 *   4. No `persona_state_change` event is ever emitted (INV-2.4.1).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('strips onboarding_layout_change and quick_replies, passes fill_form and content through, emits no persona_state_change', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'onboarding',
        'title' => 'Onboarding',
    ]);

    $agent = Mockery::mock(CoordinatingAgent::class);
    // Flag-gated collaborator call under FYN_PROMPT_ARCH=unified (now the
    // default): handleInlineCapture carries the onboarding focus for the
    // turn so the CAPTURE bucket is selected. Zero-call-satisfied under
    // legacy — non-weakening (other expectations stay strict).
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield ['type' => 'onboarding_layout_change', 'layout' => 'capture'];
            yield ['type' => 'content', 'text' => 'Got it — recorded your SIPP.'];
            yield ['type' => 'fill_form', 'entity_type' => 'dc_pension', 'fields' => ['provider' => 'Scottish Widows']];
            yield ['type' => 'quick_replies', 'replies' => ['Yes', 'No']];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    /** @var OnboardingChatDirector $director */
    $director = app(OnboardingChatDirector::class);

    $context = new CaptureContext(
        reason: 'user volunteered a pension mid-onboarding',
        entityTypes: ['dc_pension'],
    );

    $events = [];
    foreach ($director->handleInlineCapture($user, $conversation, 'I have a Scottish Widows SIPP £50k', $context) as $event) {
        $events[] = $event;
    }

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    // Layout + quick-reply events stripped.
    expect($types)->not->toContain('onboarding_layout_change');
    expect($types)->not->toContain('quick_replies');

    // Content, fill_form, done pass through.
    expect($types)->toContain('content');
    expect($types)->toContain('fill_form');
    expect($types)->toContain('done');

    // Handoff invisibility — no persona_state_change ever emitted.
    expect($types)->not->toContain('persona_state_change');
});

it('consumes a failed capture write result and emits deterministic failure text', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($conversation) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Saved your Cash ISA.',
                'persona' => 'data_capture',
            ]);
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'error' => true,
                'message' => 'Account type is required.',
            ];
            yield ['type' => 'content', 'text' => 'Saved your Cash ISA.'];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my account',
        new CaptureContext(
            reason: 'user wants to add a savings account',
            entityTypes: ['savings_account'],
        ),
    ), false);

    expect(collect($events)->pluck('type'))->not->toContain('capture_write_result');

    $failures = collect($events)->filter(
        fn (array $event): bool => ($event['type'] ?? null) === 'content'
            && str_contains((string) ($event['text'] ?? ''), "couldn't save")
    );
    expect($failures)->toHaveCount(1)
        ->and($failures->first()['text'])->toContain('Account type is required')
        ->and(collect($events)->pluck('text')->filter()->implode(' '))->not->toContain('Saved your Cash ISA')
        ->and($conversation->messages()->where('content', 'like', '%Saved your Cash ISA%')->exists())->toBeFalse()
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->count())->toBe(1);

    $types = collect($events)->pluck('type')->values();
    expect($types->filter(fn (string $type): bool => $type === 'done'))->toHaveCount(1)
        ->and($types->last())->toBe('done')
        ->and($types->search('content'))->toBeLessThan($types->search('done'));
});

it('preserves a side-question answer when the capture write fails', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $answerText = 'The Personal Savings Allowance depends on your Income Tax band.';
    $falseSaveText = 'Saved your Cash ISA.';

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($conversation, $answerText, $falseSaveText) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $answerText.$falseSaveText,
                'persona' => 'data_capture',
            ]);
            yield ['type' => 'content', 'text' => $answerText];
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'error' => true,
                'message' => 'Account type is required.',
            ];
            yield ['type' => 'content', 'text' => $falseSaveText];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my ISA. What is the Personal Savings Allowance?',
        new CaptureContext(
            reason: 'user asks a question while adding a savings account',
            entityTypes: ['savings_account'],
        ),
    ), false);

    $streamText = collect($events)->pluck('text')->filter()->implode('');
    $persistedText = (string) $conversation->messages()
        ->where('role', 'assistant')
        ->latest('id')
        ->value('content');

    expect($streamText)->toContain($answerText)
        ->and($streamText)->toContain("I couldn't save that")
        ->and($streamText)->not->toContain($falseSaveText)
        ->and($persistedText)->toContain($answerText)
        ->and($persistedText)->toContain("I couldn't save that")
        ->and($persistedText)->not->toContain($falseSaveText)
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->count())->toBe(1);

    $types = collect($events)->pluck('type')->values();
    expect($types->filter(fn (string $type): bool => $type === 'done'))->toHaveCount(1)
        ->and($types->last())->toBe('done');
});

it('does not report a resolved write failure after the corrected retry lands', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $successText = 'Saved your Cash ISA after correcting the account type.';

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($conversation, $successText) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $successText,
                'persona' => 'data_capture',
            ]);
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'tool_call_id' => 'retry-failed',
                'model_iteration' => 1,
                'landed' => false,
                'message' => 'Account type is required.',
            ];
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'tool_call_id' => 'retry-success',
                'model_iteration' => 2,
                'retry_of_tool_call_id' => 'retry-failed',
                'landed' => true,
                'message' => null,
            ];
            yield [
                'type' => 'entity_created',
                'entity_type' => 'savings_account',
                'entity_id' => 42,
                'name' => 'Cash ISA',
            ];
            yield ['type' => 'content', 'text' => $successText];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my Cash ISA',
        new CaptureContext(
            reason: 'user wants to add a savings account',
            entityTypes: ['savings_account'],
        ),
    ), false);

    $streamText = collect($events)->pluck('text')->filter()->implode('');
    expect($streamText)->toContain($successText)
        ->and($streamText)->not->toContain("couldn't save")
        ->and(collect($events)->where('type', 'capture_complete'))->toHaveCount(1)
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->exists())->toBeFalse()
        ->and($conversation->messages()->where('content', $successText)->exists())->toBeTrue();

    $types = collect($events)->pluck('type')->values();
    expect($types->filter(fn (string $type): bool => $type === 'done'))->toHaveCount(1)
        ->and($types->last())->toBe('done');
});

it('keeps the earliest safe-content cutoff when a correlated retry also fails', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $answerText = 'The Personal Savings Allowance depends on your Income Tax band.';
    $falseSaveText = 'Saved your Halifax ISA.';

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($conversation, $answerText, $falseSaveText) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $answerText.$falseSaveText,
                'persona' => 'data_capture',
            ]);
            yield ['type' => 'content', 'text' => $answerText];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'tool_call_id' => 'failed-first',
                'model_iteration' => 1,
                'landed' => false,
                'message' => 'Account type is required.',
            ];
            yield ['type' => 'content', 'text' => $falseSaveText];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'tool_call_id' => 'failed-retry',
                'model_iteration' => 2,
                'retry_of_tool_call_id' => 'failed-first',
                'landed' => false,
                'message' => 'Institution is required.',
            ];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my ISA. What is the Personal Savings Allowance?',
        new CaptureContext(
            reason: 'user asks a question while adding a savings account',
            entityTypes: ['savings_account'],
        ),
    ), false);

    $streamText = collect($events)->pluck('text')->filter()->implode('');
    $persistedText = (string) $conversation->messages()
        ->where('role', 'assistant')
        ->latest('id')
        ->value('content');

    expect($streamText)->toContain($answerText)
        ->and($streamText)->toContain('Institution is required')
        ->and($streamText)->not->toContain($falseSaveText)
        ->and($persistedText)->toContain($answerText)
        ->and($persistedText)->toContain('Institution is required')
        ->and($persistedText)->not->toContain($falseSaveText)
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->count())->toBe(1);
});

it('keeps a same-batch same-tool partial failure in either result order', function (array $landedResults) {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($conversation, $landedResults) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Saved both savings accounts.',
                'persona' => 'data_capture',
            ]);

            foreach ($landedResults as $index => $landed) {
                $callId = 'batch-call-'.($index + 1);
                yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
                if ($landed) {
                    yield [
                        'type' => 'entity_created',
                        'entity_type' => 'savings_account',
                        'entity_id' => 100 + $index,
                        'name' => 'Nationwide Saver',
                    ];
                }
                yield [
                    'type' => 'capture_write_result',
                    'tool' => 'create_savings_account',
                    'tool_call_id' => $callId,
                    'model_iteration' => 1,
                    'landed' => $landed,
                    'message' => $landed ? null : 'Halifax account type is required.',
                ];
            }

            yield ['type' => 'content', 'text' => 'Saved both savings accounts.'];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my Halifax ISA and Nationwide saver',
        new CaptureContext(
            reason: 'user wants to add two savings accounts',
            entityTypes: ['savings_account'],
        ),
    ), false);

    $streamText = collect($events)->pluck('text')->filter()->implode('');
    expect($streamText)->toContain("I couldn't save that")
        ->and($streamText)->toContain('Halifax account type is required')
        ->and($streamText)->not->toContain('Saved both savings accounts')
        ->and(collect($events)->where('type', 'capture_complete'))->toHaveCount(1)
        ->and(collect($events)->pluck('type'))->not->toContain('capture_write_result')
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->count())->toBe(1);

    $types = collect($events)->pluck('type')->values();
    expect($types->filter(fn (string $type): bool => $type === 'done'))->toHaveCount(1)
        ->and($types->last())->toBe('done');
})->with([
    'failed result before successful sibling' => [[false, true]],
    'successful sibling before failed result' => [[true, false]],
]);

it('does not report failure after a complete correlated retry batch lands', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $successText = 'Saved your house-deposit and emergency-fund goals.';

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($conversation, $successText) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $successText,
                'persona' => 'data_capture',
            ]);
            foreach (['house', 'emergency'] as $name) {
                yield [
                    'type' => 'capture_write_result',
                    'tool' => 'create_goal',
                    'tool_call_id' => $name.'-failed',
                    'model_iteration' => 1,
                    'landed' => false,
                    'message' => ucfirst($name).' target date is required.',
                ];
            }
            foreach (['house', 'emergency'] as $index => $name) {
                yield [
                    'type' => 'entity_created',
                    'entity_type' => 'goal',
                    'entity_id' => 200 + $index,
                    'name' => ucfirst($name).' goal',
                ];
                yield [
                    'type' => 'capture_write_result',
                    'tool' => 'create_goal',
                    'tool_call_id' => $name.'-retry',
                    'model_iteration' => 2,
                    'retry_of_tool_call_id' => $name.'-failed',
                    'landed' => true,
                    'message' => null,
                ];
            }
            yield ['type' => 'content', 'text' => $successText];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my house-deposit and emergency-fund goals',
        new CaptureContext(
            reason: 'user wants to add two goals',
            entityTypes: ['goal'],
        ),
    ), false);

    $streamText = collect($events)->pluck('text')->filter()->implode('');
    expect($streamText)->toContain($successText)
        ->and($streamText)->not->toContain("couldn't save")
        ->and(collect($events)->where('type', 'capture_complete'))->toHaveCount(1)
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->exists())->toBeFalse();

    $types = collect($events)->pluck('type')->values();
    expect($types->filter(fn (string $type): bool => $type === 'done'))->toHaveCount(1)
        ->and($types->last())->toBe('done');
});

it('consumes a duplicate capture write result without presenting it as a failure', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield [
                'type' => 'capture_write_result',
                'tool' => 'create_savings_account',
                'landed' => false,
                'message' => null,
            ];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'Add my existing account again',
        new CaptureContext(
            reason: 'user repeats an existing savings account',
            entityTypes: ['savings_account'],
        ),
    ), false);

    expect(collect($events)->pluck('type'))->not->toContain('capture_write_result')
        ->and(collect($events)->pluck('text')->filter()->implode(' '))->not->toContain("couldn't save")
        ->and($conversation->messages()->where('metadata->capture_write_failed', true)->exists())->toBeFalse();
});

/**
 * Capture-turn framing regression (deflection fix, June13 §6c).
 *
 * handleInlineCapture is DEFINITIONALLY a capture turn — a write intent the
 * deterministic classifier or the LLM delegate_to_capture path has already
 * cleared. It must NEVER be framed as advice: a null unifiedFocus makes
 * injectUnifiedTurnContext pick mode='advice', FynContextSelector drops the
 * CAPTURE bucket, FynCaptureTurnInstructions are never injected, and the
 * capture-turn model falls back to the security refusal ("I can only help
 * with financial planning questions…") instead of calling create_*.
 *
 * The bug: inferFocusesFromEntityTypes only mapped protection/savings/
 * retirement/investment, so property, mortgage, liability, goal, life_event,
 * and every estate entity (asset, will, trust, power_of_attorney, gift,
 * chattel, business_interest) derived a null focus → advice framing → deflect.
 *
 * This pins that a non-null focus reaches the loop (via setUnifiedOnboardingFocus)
 * for every captureable entity type.
 */
it('frames the inline capture as a capture turn (non-null focus) for every captureable entity type', function (string $entityType) {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'advice',
        'title' => 'Advice',
    ]);

    $capturedFocus = null;
    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')
        ->andReturnUsing(function ($focus) use (&$capturedFocus) {
            if ($focus !== null) {
                $capturedFocus = $focus;
            }
        });
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => 'Recorded.'];
            yield ['type' => 'done'];
        });

    app()->instance(CoordinatingAgent::class, $agent);

    /** @var OnboardingChatDirector $director */
    $director = app(OnboardingChatDirector::class);

    $context = new CaptureContext(
        reason: "user is adding their own {$entityType}",
        entityTypes: [$entityType],
    );

    iterator_to_array(
        $director->handleInlineCapture($user, $conversation, "Please add my {$entityType}", $context),
        false,
    );

    expect($capturedFocus)->not->toBeNull(
        "Capture turn for entity '{$entityType}' was framed as advice (null focus) — the capture instructions are dropped and the model deflects."
    );
})->with([
    'goal' => ['goal'],
    'life_event' => ['life_event'],
    'property' => ['property'],
    'mortgage' => ['mortgage'],
    'liability' => ['liability'],
    'asset' => ['asset'],
    'estate_gift' => ['estate_gift'],
    'chattel' => ['chattel'],
    'trust' => ['trust'],
    'will' => ['will'],
    'power_of_attorney' => ['power_of_attorney'],
    'business_interest' => ['business_interest'],
    'family_member' => ['family_member'],
    // sanity: the already-working module types must keep their focus
    'savings_account' => ['savings_account'],
    'pension' => ['pension'],
]);
