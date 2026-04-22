<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;
use App\Services\AI\HandoffContract;
use App\Services\AI\QueryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * End-to-end coverage of the capture_complete handoff.
 *
 * When data-capture Fyn emits capture_complete, the orchestrator must:
 *   1. Emit a capture_complete SSE event (payload = summary + records).
 *   2. Emit a persona_state_change event back to 'advice'.
 *   3. Reset the persisted persona_state.
 *   4. If there was a pending_advice_question, re-invoke advice Fyn
 *      with a bridge prompt so Fyn answers the original question.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('emits capture_complete SSE, resets state, and re-invokes advice with the pending question', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => [
            'current' => 'capturing',
            'capture_context' => ['reason' => 'SIPP needed', 'entity_types' => ['pension']],
            'turns_in_capture' => 1,
            'pending_advice_question' => 'Is my retirement on track?',
        ],
    ]);

    $invoker = Mockery::mock(FynPersonaInvoker::class);

    // First call — data_capture. Emits some content, then capture_complete handoff.
    $invoker->shouldReceive('invoke')
        ->once()
        ->withArgs(fn (string $persona) => $persona === FynPersonaRegistry::PERSONA_DATA_CAPTURE)
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => 'Got it — recorded your SIPP.'];
            yield ['type' => 'done'];
        });

    // lastHandoff MUST return capture_complete so the orchestrator emits
    // the capture_complete SSE and re-invokes advice. Sequential returns
    // handle the data_capture → advice-reinvocation flow.
    $invoker->shouldReceive('lastHandoff')
        ->zeroOrMoreTimes()
        ->andReturn(
            [
                'handoff_type' => HandoffContract::CAPTURE_COMPLETE,
                'payload' => [
                    'summary' => 'Added Scottish Widows SIPP £50k',
                    'records_created' => [['type' => 'pension', 'id' => 42]],
                ],
            ],
            null,
        );

    // Second call — advice re-invocation with the bridge prompt.
    $invoker->shouldReceive('invoke')
        ->once()
        ->withArgs(function (string $persona, $user, $conversation, string $message) {
            return $persona === FynPersonaRegistry::PERSONA_ADVICE
                && str_contains($message, 'Is my retirement on track?')
                && str_contains($message, 'Scottish Widows SIPP');
        })
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => 'Based on your new SIPP…'];
            yield ['type' => 'done'];
        });

    $orchestrator = new FynPersonaOrchestrator($invoker, app(QueryClassifier::class));

    $received = [];
    foreach ($orchestrator->dispatch($user, $conversation, 'I have a Scottish Widows SIPP worth £50k') as $event) {
        $received[] = $event;
    }

    $captureComplete = collect($received)->firstWhere('type', 'capture_complete');
    expect($captureComplete)->not->toBeNull();
    expect($captureComplete['summary'])->toBe('Added Scottish Widows SIPP £50k');
    expect($captureComplete['records_created'])->toBe([['type' => 'pension', 'id' => 42]]);

    $stateChange = collect($received)->firstWhere(fn (array $e) => ($e['type'] ?? null) === 'persona_state_change' && ($e['current'] ?? null) === 'advice');
    expect($stateChange)->not->toBeNull();

    $conversation->refresh();
    expect($conversation->persona_state['current'])->toBe('advice');
    // defaultState() resets turns_in_capture to 0, not null.
    expect($conversation->persona_state['turns_in_capture'] ?? null)->toBe(0);
});
