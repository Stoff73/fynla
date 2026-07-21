<?php

declare(strict_types=1);

use App\Models\AiAuditEvent;
use App\Models\User;
use App\Services\AI\AuditChainService;
use Illuminate\Support\Facades\DB;

/**
 * S0.12 — verifyChain detects post-insert mutation of any hashed field.
 */
describe('AuditChainService tamper detection (S0.12)', function () {
    beforeEach(function () {
        $this->service = app(AuditChainService::class);
        $this->user = User::factory()->create();

        // Build a 5-row chain to give tamper detection something meaningful
        // to walk past before reaching the broken row.
        for ($i = 0; $i < 5; $i++) {
            $this->service->append([
                'user_id' => $this->user->id,
                'tool_name' => 'list_records',
                'operation' => 'read',
                'status' => 'dispatched',
                'input_summary' => ['idx' => $i],
            ]);
        }
    });

    it('detects mutation of input_summary on a historical row', function () {
        $target = AiAuditEvent::query()->orderBy('id')->skip(2)->first();
        expect($target)->not->toBeNull();

        // Mutate `input_summary` directly via the query builder so the model
        // doesn't re-derive any hashes for us.
        DB::table('ai_audit_events')
            ->where('id', $target->id)
            ->update(['input_summary' => json_encode(['idx' => 999, 'tampered' => true])]);

        $result = $this->service->verifyChain();

        expect($result['chain_valid'])->toBeFalse()
            ->and($result['broken_at'])->toBe((int) $target->id)
            ->and($result['row_count'])->toBe(2);
    });

    it('detects mutation of tool_name on a historical row', function () {
        $target = AiAuditEvent::query()->orderBy('id')->skip(1)->first();

        DB::table('ai_audit_events')
            ->where('id', $target->id)
            ->update(['tool_name' => 'create_savings_account']);

        $result = $this->service->verifyChain();

        expect($result['chain_valid'])->toBeFalse()
            ->and($result['broken_at'])->toBe((int) $target->id);
    });

    it('detects mutation of entity_id on a historical row', function () {
        $target = AiAuditEvent::query()->orderBy('id')->first();

        DB::table('ai_audit_events')
            ->where('id', $target->id)
            ->update(['entity_id' => 999_999]);

        $result = $this->service->verifyChain();

        expect($result['chain_valid'])->toBeFalse()
            ->and($result['broken_at'])->toBe((int) $target->id);
    });

    it('detects deletion of a row by reporting the very next row as broken', function () {
        // Deleting a middle row leaves the row after it pointing at a
        // prev_hash that no longer matches the chain reconstructed from
        // its (now-earlier) predecessor.
        $deleteTarget = AiAuditEvent::query()->orderBy('id')->skip(2)->first();
        $expectedBreakId = (int) AiAuditEvent::query()->orderBy('id')->skip(3)->first()->id;

        DB::table('ai_audit_events')->where('id', $deleteTarget->id)->delete();

        $result = $this->service->verifyChain();

        expect($result['chain_valid'])->toBeFalse()
            ->and($result['broken_at'])->toBe($expectedBreakId);
    });
});
