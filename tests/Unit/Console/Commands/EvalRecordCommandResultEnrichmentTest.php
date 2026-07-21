<?php

declare(strict_types=1);

use App\Console\Commands\EvalRecordCommand;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Regression for P0.3 — `EvalDeltaBuilder::detectResultPathFromString` was
 * fed empty-string `result` for every tool call because the SSE stream's
 * `tool_use` events carry only {type, tool, input}, never the actual tool
 * result. Detector returned 'success' on '' so every recording graded
 * happy-path regardless of what really happened.
 *
 * The fix back-fills `result` from `ai_messages.metadata.tool_calls`
 * (the in-process record HasAiChat persists when it executes the tool).
 * These tests exercise the back-fill helper directly via reflection.
 */
function callEnrichToolCallsWithResults(array $sseCalls, int $conversationId): array
{
    $reflection = new ReflectionClass(EvalRecordCommand::class);
    $method = $reflection->getMethod('enrichToolCallsWithResults');

    return $method->invoke($reflection->newInstanceWithoutConstructor(), $sseCalls, $conversationId);
}

it('back-fills result from ai_messages.metadata.tool_calls.result_summary', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
    ]);
    AiMessage::create([
        'conversation_id' => $conv->id,
        'role' => 'assistant',
        'content' => 'irrelevant',
        'metadata' => [
            'tool_calls' => [
                [
                    'tool' => 'get_module_analysis',
                    'input' => ['module' => 'protection'],
                    'result_summary' => 'module: protection, success: false, message: Profile incomplete',
                ],
            ],
        ],
    ]);

    $sseCalls = [
        ['name' => 'get_module_analysis', 'args' => [], 'result' => null],
    ];

    $enriched = callEnrichToolCallsWithResults($sseCalls, $conv->id);

    expect($enriched[0]['result'])->toContain('success: false')
        ->and($enriched[0]['result'])->toContain('Profile incomplete');
});

it('aligns duplicate tool names by occurrence order', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
    ]);
    AiMessage::create([
        'conversation_id' => $conv->id,
        'role' => 'assistant',
        'content' => '',
        'metadata' => [
            'tool_calls' => [
                ['tool' => 'list_records', 'input' => [], 'result_summary' => 'accounts_count: 0'],
                ['tool' => 'list_records', 'input' => [], 'result_summary' => 'accounts_count: 3'],
            ],
        ],
    ]);

    $sseCalls = [
        ['name' => 'list_records', 'args' => [], 'result' => null],
        ['name' => 'list_records', 'args' => [], 'result' => null],
    ];

    $enriched = callEnrichToolCallsWithResults($sseCalls, $conv->id);

    expect($enriched[0]['result'])->toBe('accounts_count: 0')
        ->and($enriched[1]['result'])->toBe('accounts_count: 3');
});

it('returns the calls untouched when the conversation has no assistant message', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
    ]);
    // No AiMessage rows for this conversation.

    $sseCalls = [['name' => 'foo', 'args' => [], 'result' => null]];

    $enriched = callEnrichToolCallsWithResults($sseCalls, $conv->id);

    expect($enriched[0]['result'])->toBeNull();
});

it('returns the calls untouched when conversation_id is 0', function (): void {
    $sseCalls = [['name' => 'foo', 'args' => [], 'result' => null]];

    $enriched = callEnrichToolCallsWithResults($sseCalls, 0);

    expect($enriched)->toBe($sseCalls);
});
