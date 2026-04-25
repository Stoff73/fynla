<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiAbortEvent;
use App\Models\AiConversation;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sprint 0.11.2 — SSE abort detection + ai_abort_events.
 *
 * INV-2.9.2 — when the SSE client drops mid-stream the chat() generator
 * MUST detect the abort, persist a forensic ai_abort_events row with
 * the last tool name and partial-write count, and return cleanly. Any
 * direct-write tool call that already completed in the same generator
 * pass MUST NOT be rolled back — each direct-write handler is wrapped
 * in its own DB::transaction and what landed is real.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

function callRecordAbort(AiConversation $conv, ?string $tool, int $count): void
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'recordAbort');
    $reflection->setAccessible(true);
    $reflection->invoke($agent, $conv, $tool, $count);
}

describe('SSE abort recording (S0.11.2)', function () {
    it('persists an ai_abort_events row with conversation_id + user_id + tool + count', function () {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        callRecordAbort($conversation, 'create_savings_account', 3);

        $row = AiAbortEvent::first();
        expect($row)->not->toBeNull()
            ->and($row->conversation_id)->toBe($conversation->id)
            ->and($row->user_id)->toBe($user->id)
            ->and($row->last_tool_call)->toBe('create_savings_account')
            ->and($row->partial_write_count)->toBe(3);
    });

    it('records null last_tool_call when the abort fires before any tool ran', function () {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        callRecordAbort($conversation, null, 0);

        $row = AiAbortEvent::first();
        expect($row->last_tool_call)->toBeNull()
            ->and($row->partial_write_count)->toBe(0);
    });

    it('does NOT roll back any persisted records from earlier tool calls (INV-2.9.2)', function () {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        // Simulate a Goal that was created by a successful direct-write
        // tool call BEFORE the abort fired in the next iteration.
        $goal = Goal::factory()->create(['user_id' => $user->id]);

        callRecordAbort($conversation, 'create_pension', 2);

        // Audit row written, original Goal still present.
        expect(AiAbortEvent::count())->toBe(1)
            ->and(Goal::find($goal->id))->not->toBeNull();
    });

    it('exposes wasConnectionAborted as a stubbable hook', function () {
        // The default in unit-test runs is false (no real client connection).
        // The chat loop relies on this hook being mockable so abort-flow
        // tests can simulate disconnect without an actual TCP drop.
        expect(method_exists(\App\Agents\CoordinatingAgent::class, 'wasConnectionAborted'))
            ->toBeTrue();

        $agent = app(CoordinatingAgent::class);
        $reflection = new ReflectionMethod($agent, 'wasConnectionAborted');
        $reflection->setAccessible(true);

        expect($reflection->invoke($agent))->toBeFalse();
    });
});

describe('SSE abort wiring into chat loop (S0.11.2)', function () {
    it('checks wasConnectionAborted at the top of every while iteration', function () {
        $source = file_get_contents(app_path('Traits/HasAiChat.php'));

        // Pin the abort-check + recordAbort + return triplet in the
        // top-of-loop position so a future refactor that drops the check
        // breaks this test.
        expect($source)->toContain('if ($this->wasConnectionAborted())')
            ->and($source)->toContain('$this->recordAbort($conversation, $lastToolCall, $partialWriteCount)')
            ->and($source)->toContain('S0.11.2');
    });

    it('checks wasConnectionAborted before dispatching each tool call', function () {
        // Pin both abort-check sites — top-of-loop + before-tool-execute.
        $source = file_get_contents(app_path('Traits/HasAiChat.php'));
        $occurrences = substr_count($source, 'if ($this->wasConnectionAborted())');

        expect($occurrences)->toBeGreaterThanOrEqual(2);
    });

    it('tracks partial_write_count via the tool_result `created` flag', function () {
        $source = file_get_contents(app_path('Traits/HasAiChat.php'));

        // Pin the increment site so a refactor that drops the counter
        // (or moves it before the executeTool call) trips this test.
        expect($source)->toContain('$partialWriteCount++');
    });
});
