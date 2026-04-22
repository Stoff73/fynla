<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the pre-invocation cancel check in FynPersonaOrchestrator.
 *
 * When the user is mid-capture ('capturing' persona_state) and sends a
 * message matching any pattern in config('fyn.cancel_patterns'), the
 * orchestrator must:
 *   1. Reset persona_state to the advice default.
 *   2. Emit a persona_state_change event to the frontend.
 *   3. Pass the original message to the advice persona (so Fyn
 *      acknowledges naturally) rather than attempt capture.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('exits capturing state when the user sends a cancel-pattern message', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => [
            'current' => 'capturing',
            'capture_context' => ['reason' => 'SIPP', 'entity_types' => ['pension']],
            'turns_in_capture' => 2,
            'pending_advice_question' => 'Is my retirement on track?',
        ],
    ]);

    // Mock invoker — advice invocation after cancel must fire, data_capture must NOT.
    $invoker = Mockery::mock(FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')
        ->once()
        ->with(
            FynPersonaRegistry::PERSONA_ADVICE,
            Mockery::type(User::class),
            Mockery::type(AiConversation::class),
            'cancel that',
            Mockery::any(),
            null,
            true,
        )
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => 'No problem — cancelled.'];
            yield ['type' => 'done'];
        });
    $invoker->shouldReceive('lastHandoff')->zeroOrMoreTimes()->andReturn(null);

    $orchestrator = new FynPersonaOrchestrator($invoker, app(\App\Services\AI\QueryClassifier::class));

    $received = [];
    foreach ($orchestrator->dispatch($user, $conversation, 'cancel that') as $event) {
        $received[] = $event;
    }

    $stateChange = collect($received)->firstWhere('type', 'persona_state_change');
    expect($stateChange)->not->toBeNull();
    expect($stateChange['current'])->toBe('advice');

    $conversation->refresh();
    expect($conversation->persona_state['current'] ?? null)->toBe('advice');
    // defaultState() resets turns_in_capture to 0, not null.
    expect($conversation->persona_state['turns_in_capture'] ?? null)->toBe(0);
});
