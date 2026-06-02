<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\QueryClassifier;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;

uses(RefreshDatabase::class);

/**
 * April30Updates F-1 / INV-2.4.5 — handoff payload validation.
 *
 * The LLM occasionally emits delegate_to_capture with a malformed payload
 * (entity_types missing, wrong type, etc.). Pre-fix behaviour: silently
 * dropped via try/catch on CaptureContext::fromArray, log a warning,
 * `continue` the loop. The user sees a half-response and never knows
 * their request failed.
 *
 * Post-fix: HandoffPayloadValidator runs before CaptureContext, hard
 * malformations yield a `handoff_error` SSE event + `done`, and the
 * Advice generator terminates so the user gets a single conversational
 * "I couldn't pick up that request — could you try again?" message.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

function bindAdviceStreamWithPayload(array $payload): void
{
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->andReturn(['primary' => QuerySchemas::DATA_ENTRY, 'related' => []]);
    app()->instance(QueryClassifier::class, $classifier);

    // The planner (FynLoop item 5) runs before the reasoner on every advice
    // turn. Pin the provider to Anthropic and bind an empty scripted client so
    // the real Planner's call returns no tool input and degrades to a default
    // reason — fast, deterministic, no network — routing the turn straight to the
    // scripted reasoner below. (The suite's default provider is xAI via the env,
    // whose real client would otherwise make a live call here.)
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));

    $agent = Mockery::mock(CoordinatingAgent::class);
    // Flag-gated collaborator call under FYN_PROMPT_ARCH=unified (now the
    // default): handleInlineCapture carries the onboarding focus for the
    // turn so the CAPTURE bucket is selected. Zero-call-satisfied under
    // legacy — non-weakening (other expectations stay strict).
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use ($payload) {
            $stream = function () use ($payload) {
                yield ['type' => 'handoff', 'handoff_type' => 'delegate_to_capture', 'payload' => $payload];
            };

            return $stream();
        });
    app()->instance(CoordinatingAgent::class, $agent);
}

it('emits handoff_error when delegate_to_capture payload omits entity_types entirely', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Advice',
    ]);

    // Hard malformed: entity_types missing.
    bindAdviceStreamWithPayload(['reason' => 'user wants something']);

    $advice = app(AdviceFyn::class);
    $events = iterator_to_array($advice->handle($user, $conversation, 'add it'), false);

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    expect($types)->toContain('handoff_error');
    expect($types)->toContain('done');
    expect($types)->not->toContain('handoff'); // Synthetic event still stripped.

    $errorEvent = collect($events)->firstWhere('type', 'handoff_error');
    expect($errorEvent['reason'])->toBe('missing_or_invalid_entity_types');
    expect($errorEvent['message'])->toContain("couldn't pick up");
});

it('emits handoff_error when entity_types is present but not an array', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Advice',
    ]);

    bindAdviceStreamWithPayload([
        'reason' => 'add a cash isa',
        'entity_types' => 'savings_account', // string, not array
    ]);

    $advice = app(AdviceFyn::class);
    $events = iterator_to_array($advice->handle($user, $conversation, 'add it'), false);

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    expect($types)->toContain('handoff_error');
    expect($types)->toContain('done');
});

it('does NOT emit handoff_error when only reason is missing — recovers via CaptureContext synthesis', function (): void {
    // Soft malformed: reason missing but entity_types present. CaptureContext::fromArray
    // synthesises a reason and the handoff continues. The user-visible stream should
    // therefore NOT contain handoff_error. This is the conservative recovery path —
    // bad LLM output gets routed through anyway, only telemetry (Log::notice) records it.

    $user = User::factory()->create(['onboarding_completed' => true]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Advice',
    ]);

    // Need a bind that yields a handoff THEN a capture stream.
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->andReturn(['primary' => QuerySchemas::DATA_ENTRY, 'related' => []]);
    app()->instance(QueryClassifier::class, $classifier);

    // The planner (FynLoop item 5) runs before the reasoner on every advice
    // turn. Pin the provider to Anthropic and bind an empty scripted client so
    // the real Planner's call returns no tool input and degrades to a default
    // reason — fast, deterministic, no network — routing the turn straight to the
    // scripted reasoner below. (The suite's default provider is xAI via the env,
    // whose real client would otherwise make a live call here.)
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));

    $agent = Mockery::mock(CoordinatingAgent::class);
    // Flag-gated collaborator call under FYN_PROMPT_ARCH=unified (now the
    // default): handleInlineCapture carries the onboarding focus for the
    // turn so the CAPTURE bucket is selected. Zero-call-satisfied under
    // legacy — non-weakening (other expectations stay strict).
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (...$args) {
            $persona = $args[8] ?? null;
            $stream = match ($persona) {
                'advice' => function () {
                    yield ['type' => 'handoff', 'handoff_type' => 'delegate_to_capture', 'payload' => [
                        // No `reason` key.
                        'entity_types' => ['savings_account'],
                    ]];
                },
                'data_capture' => function () {
                    yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
                    yield ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 1, 'name' => 'Test'];
                    yield ['type' => 'done'];
                },
                default => function () {
                    yield ['type' => 'done'];
                },
            };

            return $stream();
        });
    app()->instance(CoordinatingAgent::class, $agent);

    $advice = app(AdviceFyn::class);
    $events = iterator_to_array($advice->handle($user, $conversation, 'add a cash isa'), false);

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    expect($types)->not->toContain('handoff_error');
    expect($types)->not->toContain('handoff');
    expect($types)->toContain('entity_created');
});
