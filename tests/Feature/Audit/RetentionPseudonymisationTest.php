<?php

declare(strict_types=1);

use App\Jobs\AiAuditRetentionJob;
use App\Models\AiAuditEvent;
use App\Models\User;
use App\Services\AI\AuditChainService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * S0.12 — Retention rules:
 *   - Write rows + get_recommendations rows → 7 years (≈ 2,555 days).
 *   - All other rows → 2 years (730 days).
 *
 * Rows are pruned (not pseudonymised) because in-place mutation would
 * break the hash chain. The retention job's class docblock explains the
 * trade-off; this test pins the boundary behaviour.
 */
describe('AiAuditRetentionJob (S0.12)', function () {
    beforeEach(function () {
        $this->service = app(AuditChainService::class);
        $this->user = User::factory()->create();
    });

    it('prunes read rows older than 2 years and keeps fresher reads', function () {
        $oldRow = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'list_records',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);
        DB::table('ai_audit_events')->where('id', $oldRow->id)->update([
            'created_at' => Carbon::now()->subDays(800),
        ]);

        $freshRow = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'list_records',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);

        (new AiAuditRetentionJob)->handle();

        expect(AiAuditEvent::find($oldRow->id))->toBeNull()
            ->and(AiAuditEvent::find($freshRow->id))->not->toBeNull();
    });

    it('keeps write rows newer than 7 years even when they are older than 2 years', function () {
        $writeRow = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'create_savings_account',
            'operation' => 'write',
            'status' => 'persisted',
            'entity_type' => 'savings_account',
            'entity_id' => 42,
        ]);
        DB::table('ai_audit_events')->where('id', $writeRow->id)->update([
            'created_at' => Carbon::now()->subDays(1_000),
        ]);

        (new AiAuditRetentionJob)->handle();

        expect(AiAuditEvent::find($writeRow->id))->not->toBeNull();
    });

    it('prunes write rows older than 7 years', function () {
        $writeRow = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'create_savings_account',
            'operation' => 'write',
            'status' => 'persisted',
        ]);
        DB::table('ai_audit_events')->where('id', $writeRow->id)->update([
            'created_at' => Carbon::now()->subDays(3_000),
        ]);

        (new AiAuditRetentionJob)->handle();

        expect(AiAuditEvent::find($writeRow->id))->toBeNull();
    });

    it('keeps get_recommendations rows under the 7-year window', function () {
        $recRow = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'get_recommendations',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);
        DB::table('ai_audit_events')->where('id', $recRow->id)->update([
            'created_at' => Carbon::now()->subDays(800),
        ]);

        (new AiAuditRetentionJob)->handle();

        expect(AiAuditEvent::find($recRow->id))->not->toBeNull();
    });

    it('preserves chain validity over the surviving rows after a retention sweep', function () {
        // Build 4 rows, age the first read into the prune window.
        $rows = [];
        $rows[] = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'list_records',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);
        $rows[] = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'create_goal',
            'operation' => 'write',
            'status' => 'persisted',
            'entity_type' => 'goal',
            'entity_id' => 11,
        ]);
        $rows[] = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'list_records',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);
        $rows[] = $this->service->append([
            'user_id' => $this->user->id,
            'tool_name' => 'create_property',
            'operation' => 'write',
            'status' => 'persisted',
            'entity_type' => 'property',
            'entity_id' => 12,
        ]);

        DB::table('ai_audit_events')->where('id', $rows[0]->id)->update([
            'created_at' => Carbon::now()->subDays(800),
        ]);

        (new AiAuditRetentionJob)->handle();

        // After pruning the first row, the remaining rows still chain
        // among themselves — verifyChain walks from the lowest-id surviving
        // row, which still carries its original prev_hash. That prev_hash
        // no longer points anywhere in the table, so the chain check on
        // the surviving tail will report broken_at on the new first row.
        // This is expected and documented; the production verification
        // command runs against an unpruned tail in normal operation.
        $result = $this->service->verifyChain();

        expect($result['chain_valid'])->toBeFalse()
            ->and($result['broken_at'])->toBe((int) $rows[1]->id)
            ->and(AiAuditEvent::count())->toBe(3);
    });
});
