<?php

declare(strict_types=1);

use App\Console\Commands\EvalRecordCommand;

/**
 * Unit test for EvalRecordCommand::extractToolCallsFromEvents.
 *
 * Regression test for the P0.2 defect surfaced in maxAuditEval.md §3.2:
 * the extractor read the SSE event key `name`, but `HasAiChat` actually
 * emits the key `tool` for tool_use events — so every captured
 * `tool_calls[*].name` was the literal string "unknown". This test loads
 * a real recorded fixture and asserts that the names round-trip back to
 * the actual tool names.
 */
function callExtractToolCalls(array $events): array
{
    $reflection = new ReflectionClass(EvalRecordCommand::class);
    $method = $reflection->getMethod('extractToolCallsFromEvents');

    return $method->invoke($reflection->newInstanceWithoutConstructor(), $events);
}

function loadFixtureEvents(string $path): array
{
    $events = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded) && ! isset($decoded['__meta'])) {
            $events[] = $decoded;
        }
    }

    return $events;
}

it('extracts the actual tool names from recorded SSE events (regression: P0.2)', function () {
    $fixturePath = dirname(__DIR__, 3).'/Feature/Fyn/Eval/fixtures/anthropic/claude-haiku-4-5-20251001/mitchell_advice_protection_cover.jsonl';
    expect(file_exists($fixturePath))->toBeTrue('fixture missing — re-record to populate');

    $events = loadFixtureEvents($fixturePath);
    $toolCalls = callExtractToolCalls($events);

    expect($toolCalls)->not->toBeEmpty()
        ->and(collect($toolCalls)->pluck('name')->unique()->values()->all())
        ->toContain('get_module_analysis')
        ->toContain('list_records')
        ->and(collect($toolCalls)->pluck('name')->all())
        ->not->toContain('unknown');
});

it('reads the SSE key `tool` not `name`', function () {
    // SSE event shape per HasAiChat:439-443 — the key is `tool`, never `name`.
    $events = [
        ['type' => 'tool_use', 'tool' => 'get_module_analysis', 'status' => 'running'],
        ['type' => 'content', 'text' => 'thinking…'],
        ['type' => 'tool_use', 'tool' => 'list_records', 'status' => 'running'],
    ];

    $toolCalls = callExtractToolCalls($events);

    expect($toolCalls)->toHaveCount(2)
        ->and($toolCalls[0]['name'])->toBe('get_module_analysis')
        ->and($toolCalls[1]['name'])->toBe('list_records');
});

it('falls back to "unknown" when neither key is present', function () {
    $events = [
        ['type' => 'tool_use', 'status' => 'running'],
    ];

    $toolCalls = callExtractToolCalls($events);

    expect($toolCalls)->toHaveCount(1)
        ->and($toolCalls[0]['name'])->toBe('unknown');
});
