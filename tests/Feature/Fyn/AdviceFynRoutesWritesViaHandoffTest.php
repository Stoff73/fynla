<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\QueryClassifier;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S0.5.r — wire the advice → capture handoff.
 *
 * Pins the canonical Two-Fyn contract: write intents in advice mode flow
 * through the synthetic `delegate_to_capture` SSE event into
 * OnboardingChatDirector::handleInlineCapture, and the user never sees
 * the handoff happen.
 *
 * INV-2.4.1: zero handoff/persona_state_change events on the user-visible
 *            stream.
 * INV-2.6.x: tool_use / entity_created from the inline-capture LLM call
 *            DO surface (the user sees the record being created).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

function fynStubChatStream(string $persona): Closure
{
    return match ($persona) {
        'advice_savings' => function () {
            yield ['type' => 'handoff', 'handoff_type' => 'delegate_to_capture', 'payload' => [
                'reason' => 'user wants to add a Cash ISA',
                'entity_types' => ['savings_account'],
                'fields_needed' => ['institution', 'balance', 'interest_rate'],
            ]];
        },
        'inline_savings' => function () {
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield ['type' => 'entity_created',
                'entity_type' => 'savings_account',
                'entity_id' => 99,
                'name' => 'Nationwide Cash ISA'];
            yield ['type' => 'content', 'text' => 'Saved your Nationwide Cash ISA — £5,000 at 4.5%.'];
            yield ['type' => 'done'];
        },
        'advice_what_if' => function () {
            yield ['type' => 'handoff', 'handoff_type' => 'delegate_to_capture', 'payload' => [
                'reason' => 'user wants a what-if scenario',
                'entity_types' => ['what_if_scenario'],
            ]];
        },
        'inline_what_if' => function () {
            yield ['type' => 'tool_use', 'tool' => 'create_what_if_scenario'];
            yield ['type' => 'navigation', 'route_path' => '/planning/what-if/42'];
            yield ['type' => 'content', 'text' => 'Here is the scenario.'];
            yield ['type' => 'done'];
        },
    };
}

function fynBindMocks(string $adviceStub, string $inlineStub): void
{
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->andReturn(['primary' => QuerySchemas::DATA_ENTRY, 'related' => []]);
    app()->instance(QueryClassifier::class, $classifier);

    $agent = Mockery::mock(CoordinatingAgent::class);
    // Flag-gated collaborator call under FYN_PROMPT_ARCH=unified (now the
    // default): handleInlineCapture carries the onboarding focus for the
    // turn so the CAPTURE bucket is selected. Zero-call-satisfied under
    // legacy — non-weakening (other expectations stay strict).
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function (...$args) use ($adviceStub, $inlineStub) {
            // personaOverride is the 9th positional arg (index 8).
            $persona = $args[8] ?? null;
            $stream = match ($persona) {
                'advice' => fynStubChatStream($adviceStub),
                'data_capture' => fynStubChatStream($inlineStub),
                default => function () {
                    yield ['type' => 'done'];
                },
            };

            return $stream();
        });
    app()->instance(CoordinatingAgent::class, $agent);
}

it('AdviceFyn intercepts delegate_to_capture handoff and routes through handleInlineCapture invisibly', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Advice',
    ]);

    fynBindMocks('advice_savings', 'inline_savings');

    /** @var AdviceFyn $advice */
    $advice = app(AdviceFyn::class);

    $events = [];
    foreach ($advice->handle(
        $user,
        $conversation,
        'Add a Cash ISA at Nationwide, balance £5,000, 4.5% interest',
    ) as $event) {
        $events[] = $event;
    }

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    // INV-2.4.1: synthetic handoff event must NOT reach the user.
    expect($types)->not->toContain('handoff');
    expect($types)->not->toContain('persona_state_change');

    // The inline-capture chain runs and its events surface to the user.
    expect($types)->toContain('tool_use');
    expect($types)->toContain('entity_created');
    expect(array_count_values($types)['done'] ?? 0)->toBe(1)
        ->and($types[array_key_last($types)])->toBe('done');
});

it('AdviceFyn routes create_what_if_scenario through the handoff (no analytics carve-out)', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Advice',
    ]);

    fynBindMocks('advice_what_if', 'inline_what_if');

    /** @var AdviceFyn $advice */
    $advice = app(AdviceFyn::class);

    $events = [];
    foreach ($advice->handle(
        $user,
        $conversation,
        'What if I retired at 60 with my SIPP?',
    ) as $event) {
        $events[] = $event;
    }

    $types = array_map(fn (array $e) => $e['type'] ?? '', $events);

    // Synthetic handoff stays internal.
    expect($types)->not->toContain('handoff');

    // Inline-capture call dispatches create_what_if_scenario and surfaces
    // the navigation event so the UI routes to the scenario view.
    expect($types)->toContain('tool_use');
    expect($types)->toContain('navigation');
    expect(array_count_values($types)['done'] ?? 0)->toBe(1)
        ->and($types[array_key_last($types)])->toBe('done');
});
