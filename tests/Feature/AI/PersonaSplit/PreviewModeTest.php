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
 * Covers the preview-mode short-circuit in FynPersonaOrchestrator.
 *
 * Preview users cannot persist records (every write tool is filtered
 * out by AiToolDefinitions::getTools). The orchestrator must short-
 * circuit before entering capturing state and emit the preview_cta SSE
 * event so the frontend can surface the sign-up button inline.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('emits preview_cta instead of entering capturing state for preview users on classifier fast-path', function () {
    config(['fyn.classifier_fast_path_enabled' => true]);

    $previewUser = User::factory()->create(['is_preview_user' => true]);

    $conversation = AiConversation::create([
        'user_id' => $previewUser->id,
        'status' => 'active',
        'model_used' => 'orchestrator',
        'title' => 'Test',
        'persona_state' => ['current' => 'advice'],
    ]);

    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->zeroOrMoreTimes()
        ->andReturn(['primary' => \App\Constants\QuerySchemas::DATA_ENTRY]);

    $invoker = Mockery::mock(FynPersonaInvoker::class);
    // CRITICAL — preview users must never invoke data_capture.
    $invoker->shouldNotReceive('invoke');

    $orchestrator = new FynPersonaOrchestrator($invoker, $classifier);

    $received = [];
    foreach ($orchestrator->dispatch($previewUser, $conversation, 'add my ISA £10k') as $event) {
        $received[] = $event;
    }

    $cta = collect($received)->firstWhere('type', 'preview_cta');
    expect($cta)->not->toBeNull();
});
