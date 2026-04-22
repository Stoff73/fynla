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
 * KYC-gate flow — advice → delegate_to_capture → data_capture → capture_complete → advice.
 *
 * The user asks an advice question Fyn cannot answer without data.
 * Advice Fyn emits delegate_to_capture via the handoff tool. The
 * orchestrator transitions persona_state to 'capturing', runs data_capture
 * in the same HTTP request, and when data_capture emits capture_complete,
 * re-invokes advice with a bridge prompt so the original question is
 * answered with the new data.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('runs advice → data_capture → advice in a single dispatch', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => ['current' => 'advice'],
    ]);

    $invoker = Mockery::mock(FynPersonaInvoker::class);

    $invokeCalls = [];
    $invoker->shouldReceive('invoke')
        ->times(3)
        ->andReturnUsing(function (string $persona) use (&$invokeCalls) {
            $invokeCalls[] = $persona;

            return (function () use ($persona) {
                yield ['type' => 'content', 'text' => 'persona: '.$persona];
                yield ['type' => 'done'];
            })();
        });

    $invoker->shouldReceive('lastHandoff')
        ->andReturn(
            // First advice turn — user asked "is my retirement on track?" → delegate.
            [
                'handoff_type' => HandoffContract::DELEGATE_TO_CAPTURE,
                'payload' => [
                    'reason' => 'no pension data on file',
                    'entity_types' => ['dc_pension'],
                    'fields_needed' => ['scheme_name', 'current_fund_value'],
                ],
            ],
            // Data-capture turn — emits capture_complete.
            [
                'handoff_type' => HandoffContract::CAPTURE_COMPLETE,
                'payload' => [
                    'summary' => 'Added SIPP',
                    'records_created' => [['type' => 'pension', 'id' => 1]],
                ],
            ],
            // Re-invoked advice turn — no further handoff.
            null,
        );

    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')->zeroOrMoreTimes()->andReturn(['primary' => 'advice']);

    $orchestrator = new FynPersonaOrchestrator($invoker, $classifier);

    $received = [];
    foreach ($orchestrator->dispatch(
        $user,
        $conversation,
        'Is my retirement on track?'
    ) as $event) {
        $received[] = $event;
    }

    // Three invocations: advice, data_capture, advice again.
    expect($invokeCalls)->toBe([
        FynPersonaRegistry::PERSONA_ADVICE,
        FynPersonaRegistry::PERSONA_DATA_CAPTURE,
        FynPersonaRegistry::PERSONA_ADVICE,
    ]);

    // One capture_complete SSE event, one final persona_state_change back to advice.
    $captureComplete = collect($received)->firstWhere('type', 'capture_complete');
    expect($captureComplete['summary'])->toBe('Added SIPP');

    $conversation->refresh();
    expect($conversation->persona_state['current'])->toBe('advice');
});
