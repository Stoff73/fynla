<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\FynPersonaRegistry;
use App\Services\AI\QueryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the classifier fast-path in FynPersonaOrchestrator.
 *
 * Clear data-entry messages (short, no advice-shaped signals, classified
 * DATA_ENTRY) skip advice Fyn and go straight to data_capture. This
 * matters because the alternative — running every "add my SIPP" style
 * message through advice first — spends a full LLM round-trip on a
 * decision the rule-based classifier can make in microseconds.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('routes short data-entry messages straight to data_capture without invoking advice', function () {
    config(['fyn.classifier_fast_path_enabled' => true]);

    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => ['current' => 'advice'],
    ]);

    // Classifier mock — return DATA_ENTRY for the short message.
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->once()
        ->andReturn(['primary' => QuerySchemas::DATA_ENTRY]);

    // Invoker mock — data_capture must be invoked, advice must NOT.
    $invoker = Mockery::mock(FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')
        ->once()
        ->withArgs(function (string $persona) {
            return $persona === FynPersonaRegistry::PERSONA_DATA_CAPTURE;
        })
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => 'Got it.'];
            yield ['type' => 'done'];
        });
    $invoker->shouldReceive('lastHandoff')->zeroOrMoreTimes()->andReturn(null);

    $orchestrator = new FynPersonaOrchestrator($invoker, $classifier);

    $received = [];
    foreach ($orchestrator->dispatch($user, $conversation, 'add my Halifax ISA £10k') as $event) {
        $received[] = $event;
    }

    expect(collect($received)->contains(fn (array $e) => ($e['type'] ?? null) === 'content'))->toBeTrue();
});

it('falls back to advice when classifier returns non-DATA_ENTRY', function () {
    config(['fyn.classifier_fast_path_enabled' => true]);

    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => ['current' => 'advice'],
    ]);

    // classify may or may not fire — "what protection should I have" gets
    // short-circuited by QuerySchemas::isAdviceShaped before the classifier
    // call. Either way the behaviour (advice persona, not data_capture) is
    // what we're asserting.
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->zeroOrMoreTimes()
        ->andReturn(['primary' => QuerySchemas::PROTECTION_COVER]);

    $invoker = Mockery::mock(FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')
        ->once()
        ->withArgs(function (string $persona) {
            return $persona === FynPersonaRegistry::PERSONA_ADVICE;
        })
        ->andReturnUsing(function () {
            yield ['type' => 'content', 'text' => "Here's the protection advice…"];
            yield ['type' => 'done'];
        });
    $invoker->shouldReceive('lastHandoff')->zeroOrMoreTimes()->andReturn(null);

    $orchestrator = new FynPersonaOrchestrator($invoker, $classifier);

    $received = [];
    foreach ($orchestrator->dispatch($user, $conversation, 'what protection should I have') as $event) {
        $received[] = $event;
    }

    expect($received)->not->toBeEmpty();
});

it('skips the fast-path entirely when the flag is disabled', function () {
    config(['fyn.classifier_fast_path_enabled' => false]);

    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => ['current' => 'advice'],
    ]);

    // Classifier must NOT be called when the flag is off — advice persona
    // gets every turn, and the delegate_to_capture handoff is the only
    // route into data_capture.
    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldNotReceive('classify');

    $invoker = Mockery::mock(FynPersonaInvoker::class);
    $invoker->shouldReceive('invoke')
        ->once()
        ->withArgs(function (string $persona) {
            return $persona === FynPersonaRegistry::PERSONA_ADVICE;
        })
        ->andReturnUsing(function () {
            yield ['type' => 'done'];
        });
    $invoker->shouldReceive('lastHandoff')->zeroOrMoreTimes()->andReturn(null);

    $orchestrator = new FynPersonaOrchestrator($invoker, $classifier);

    foreach ($orchestrator->dispatch($user, $conversation, 'add my ISA') as $_) {
        // drain
    }
});
