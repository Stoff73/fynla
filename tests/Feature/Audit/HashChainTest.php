<?php

declare(strict_types=1);

use App\Models\AiAuditEvent;
use App\Models\User;
use App\Services\AI\AuditChainService;

/**
 * S0.12 — Hash chain integrity over an append-and-walk loop.
 */
describe('AuditChainService hash chain (S0.12)', function () {
    it('appends a chain of 100 events that verify clean', function () {
        $service = app(AuditChainService::class);
        $user = User::factory()->create();

        $previousHash = str_repeat('0', 64);

        for ($i = 0; $i < 100; $i++) {
            $row = $service->append([
                'user_id' => $user->id,
                'tool_name' => 'list_records',
                'operation' => 'read',
                'status' => 'dispatched',
                'input_summary' => ['index' => $i, 'preview' => false],
            ]);

            expect($row->prev_hash)->toBe($previousHash)
                ->and($row->row_hash)->toMatch('/^[0-9a-f]{64}$/')
                ->and($row->signature)->toMatch('/^[0-9a-f]{64}$/');

            $previousHash = $row->row_hash;
        }

        $result = $service->verifyChain();

        expect($result['chain_valid'])->toBeTrue()
            ->and($result['row_count'])->toBe(100)
            ->and($result['tip_hash'])->toBe($previousHash);
    });

    it('starts the chain from the canonical zero hash', function () {
        $service = app(AuditChainService::class);
        $user = User::factory()->create();

        $row = $service->append([
            'user_id' => $user->id,
            'tool_name' => 'navigate_to_page',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);

        expect($row->prev_hash)->toBe(str_repeat('0', 64));
    });

    it('returns row_count 0 and a zero tip for an empty table', function () {
        AiAuditEvent::query()->delete();
        $service = app(AuditChainService::class);

        $result = $service->verifyChain();

        expect($result)->toMatchArray([
            'chain_valid' => true,
            'row_count' => 0,
            'tip_hash' => str_repeat('0', 64),
        ]);
    });
});
