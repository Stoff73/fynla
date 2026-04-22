<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\QueryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the capture-mode timeout in FynPersonaOrchestrator.
 *
 * After fyn.capture_max_turns (default 6) user turns inside capturing
 * state, the orchestrator must force-return to advice with an
 * explanatory content event instead of hanging the user in capture
 * forever.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('forces a return to advice when turns_in_capture hits the cap', function () {
    config(['fyn.capture_max_turns' => 3]);

    $user = User::factory()->create(['is_preview_user' => false]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => [
            'current' => 'capturing',
            'capture_context' => ['reason' => 'SIPP', 'entity_types' => ['pension']],
            'turns_in_capture' => 3,
            'pending_advice_question' => 'retirement?',
        ],
    ]);

    // Invoker must NOT be called — timeout short-circuits before invocation.
    $invoker = Mockery::mock(FynPersonaInvoker::class);
    $invoker->shouldNotReceive('invoke');

    $orchestrator = new FynPersonaOrchestrator($invoker, app(QueryClassifier::class));

    $received = [];
    foreach ($orchestrator->dispatch($user, $conversation, 'still trying to add my pension') as $event) {
        $received[] = $event;
    }

    $stateChange = collect($received)->firstWhere('type', 'persona_state_change');
    expect($stateChange['current'] ?? null)->toBe('advice');

    $content = collect($received)->firstWhere('type', 'content');
    expect($content['text'] ?? '')->toContain("Let me come back");

    $conversation->refresh();
    expect($conversation->persona_state['current'] ?? null)->toBe('advice');
});
