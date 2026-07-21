<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;

/**
 * Sprint 0.11.6 — INV-2.5.3.
 *
 * `HasAiChat::summariseToolResult` must always preserve `entity_id` and
 * `entity_type` keys when present, even if other keys would otherwise
 * push them past the 5-key truncation cap. The audit chain (S0.12) and
 * the downstream observers depend on these two keys to correlate a
 * direct-write tool call back to the persisted row — losing them in the
 * summary breaks audit traceability.
 */
function invokeSummariseToolResult(array $result): string
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'summariseToolResult');
    $reflection->setAccessible(true);

    return $reflection->invoke($agent, $result);
}

describe('HasAiChat::summariseToolResult preserves entity_id (S0.11.6 / INV-2.5.3)', function () {
    it('returns "empty result" for an empty array', function () {
        expect(invokeSummariseToolResult([]))->toBe('empty result');
    });

    it('preserves entity_id when it is the 6th key (past the 5-key cap)', function () {
        $summary = invokeSummariseToolResult([
            'success' => true,
            'created' => true,
            'name' => 'My SIPP',
            'persisted_fields' => ['provider', 'pot_value'],
            'message' => 'Created your SIPP.',
            'entity_id' => 42,
            'entity_type' => 'dc_pension',
        ]);

        expect($summary)->toContain('entity_id: 42')
            ->and($summary)->toContain('entity_type: dc_pension');
    });

    it('preserves entity_type when it is the 7th key', function () {
        $summary = invokeSummariseToolResult([
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
            'e' => 5,
            'f' => 6,
            'entity_type' => 'savings_account',
        ]);

        expect($summary)->toContain('entity_type: savings_account');
    });

    it('still includes entity_id when it is the first key (no regression)', function () {
        $summary = invokeSummariseToolResult([
            'entity_id' => 7,
            'entity_type' => 'mortgage',
            'success' => true,
        ]);

        expect($summary)->toContain('entity_id: 7')
            ->and($summary)->toContain('entity_type: mortgage');
    });

    it('does not duplicate entity_id when present in the first 5 keys', function () {
        $summary = invokeSummariseToolResult([
            'entity_id' => 99,
            'entity_type' => 'goal',
            'name' => 'Buy a house',
        ]);

        expect(substr_count($summary, 'entity_id: 99'))->toBe(1)
            ->and(substr_count($summary, 'entity_type: goal'))->toBe(1);
    });

    it('preserves the existing 5-key cap for non-priority keys', function () {
        $summary = invokeSummariseToolResult([
            'k1' => 1,
            'k2' => 2,
            'k3' => 3,
            'k4' => 4,
            'k5' => 5,
            'k6' => 6,
            'k7' => 7,
        ]);

        // k1..k5 included; k6/k7 skipped (no entity_id/entity_type to bypass).
        expect($summary)->toContain('k1: 1')
            ->and($summary)->toContain('k5: 5')
            ->and($summary)->not->toContain('k6: 6')
            ->and($summary)->not->toContain('k7: 7');
    });

    it('preserves boolean and string entity values', function () {
        $summary = invokeSummariseToolResult([
            'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5,
            'entity_id' => 12,
            'entity_type' => 'estate_asset',
        ]);

        expect($summary)->toContain('entity_id: 12')
            ->and($summary)->toContain('entity_type: estate_asset');
    });
});
